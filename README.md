# Patterns — Filesystem

**Where your files land is a deployment detail. This is the contract that makes it one.**

Seven operations that a local disk, an object store and an array in a test can all
honour — plus one optional capability for the backends that can describe a path.
The code that writes a file stops caring which of them it is talking to.

Part of the **Patterns** collection: small, focused building blocks. One pattern,
one package.

- Package: `patterns/filesystem`
- Port: `Patterns\IFilesystem`
- Capability: `Patterns\IStatable`
- Shape: `Patterns\FileStats`
- Depends on: `patterns/value-object` (so `FileStats` is a real ValueObject)
- PHP: `>=8.1`

---

## Install

```bash
composer require patterns/filesystem
```

**Contracts only — no implementation ships.** A local disk, S3 through the
official SDK, memory in a test: each is a small class of its own, and none of them
belongs in the pattern.

## The problem

Something writes a file. In development it lands on a disk, in production in a
bucket, in a test it should land nowhere at all — and the code that writes it has
to look the same in all three.

Without a contract, "where files live" leaks into every call site: `file_put_contents()`
in one, `$s3Client->putObject()` in another, `sys_get_temp_dir()` in a third. Then a
test needs a disk, a bucket needs credentials, and the decision can no longer be made
in one place.

## The pattern

```php
namespace Patterns;

interface IFilesystem
{
    public function exists(string $path): bool;
    public function readFile(string $path): string;
    public function writeFile(string $path, string $data): void;
    public function deleteFile(string $path): void;
    public function readDir(string $path): array;      // list<string>, names not paths
    public function ensureDir(string $path): void;
    public function deleteDir(string $path): void;
}
```

Seven operations. A path is always a string. **Failures throw** — a missing file is
not a value to branch on inside the port, and a caller who wants a `Result` can wrap
the call. An adapter that returned one would force every other adapter to build one,
which is policy, not shape.

## Why exactly these seven

Everything else was left out on purpose:

| Left out | Because |
| --- | --- |
| `chmod()` | Permission bits are POSIX authority. Object stores have none, so a port promising it cannot be satisfied by half the backends it describes. |
| `clear()` | `readDir()` plus `deleteFile()` in a loop. Anything derivable is not a port's business. |
| `copyFile()`, `copyDirectory()` | Composable from read and write. Copying a million files is an infrastructure decision, not an interface. |
| `stat()` | Not every backend can answer. See below. |
| `readFileSync()`, `writeFileAsync()` | An implementation's era, not a contract. |
| Caching | A correctness hazard wearing an optimisation's coat. A cached `exists()` is the one answer you cannot afford to be wrong. |
| Retries, backoff, timeouts | Transport policy. |
| Streams and `resource` handles | The port promises text. A stream is an optimisation with its own lifecycle. |
| Globbing, symlinks, `mkdir -p` folklore | Local filesystem trivia, not a contract. |
| Fetching an HTTP URL | It is not a file operation. When a "filesystem" quietly reaches the network, you can no longer reason about it — pass an HTTP client instead. |

## Describing a path is a capability, not a promise

A local disk can answer "how big is this, and when did it change". S3 can. A
write-only sink cannot, and a memory backend may not care. PHP has no optional
interface methods, so the honest answer is a second, one-method interface:

```php
namespace Patterns;

interface IStatable
{
    public function stat(string $path): FileStats;
}
```

Adapters that can, implement it. Callers that need it, ask:

```php
use Patterns\IStatable;

if ($backend instanceof IStatable) {
    $size = $backend->stat('reports/2026.json')->size();
}
```

A facade that wraps an adapter can only be as capable as the adapter it was handed.
Saying so at runtime — one clear error — beats returning zeros that look like answers.

## FileStats

Four facts, and no more:

```php
$stats = FileStats::create([
    'path'     => 'reports/2026.json',
    'size'     => 18432,
    'modified' => '2026-10-06T08:00:00+00:00',
    'isDir'    => false,
]);

$stats->path();               // 'reports/2026.json'
$stats->size();               // 18432
$stats->modified();           // DateTimeImmutable
$stats->isDir();              // false
$stats->toArray();            // back to the array above
```

`mode`, `isSymbolicLink` and `atime` are deliberately absent: they are POSIX detail,
and a shape that half the backends implementing `IStatable` cannot fill is a lie told
in the type system.

`modified` is stored as an ISO-8601 string rather than a date object, so equality,
JSON and a round trip through persisted data all agree on one representation. Whatever
the backend hands over — an epoch, a date string, a `DateTimeInterface` — `create()`
normalizes it.

## Implementing it

An in-memory backend is the whole port in about fifty lines, and it is the proof that
the contract is small:

```php
namespace App\Storage;

use Patterns\IFilesystem;
use RuntimeException;

final class MemoryFilesystem implements IFilesystem
{
    /** @var array<string, string> */
    private array $files = [];

    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function readFile(string $path): string
    {
        return $this->files[$path] ?? throw new RuntimeException('no such file: ' . $path);
    }

    public function writeFile(string $path, string $data): void
    {
        $this->files[$path] = $data;
    }

    public function deleteFile(string $path): void
    {
        unset($this->files[$path]);
    }

    public function readDir(string $path): array
    {
        $prefix = $path === '' || str_ends_with($path, '/') ? $path : $path . '/';
        $entries = [];

        foreach (array_keys($this->files) as $key) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            $entries[] = explode('/', substr($key, strlen($prefix)))[0];
        }

        $entries = array_values(array_unique($entries));
        sort($entries);

        return $entries;
    }

    public function ensureDir(string $path): void
    {
        // Nothing to do: a key's hierarchy exists because the key does.
    }

    public function deleteDir(string $path): void
    {
        $prefix = $path === '' || str_ends_with($path, '/') ? $path : $path . '/';

        foreach (array_keys($this->files) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->files[$key]);
            }
        }
    }
}
```

The same four lines describe the shape of every other adapter:

1. Implement `IFilesystem` — seven methods, no base class, no trait.
2. Throw on failure. Swap the message, keep the contract.
3. Never reach for the network from a path. That is a different port.
4. Implement `IStatable` only if you really can describe a path.
5. Return bare names from `readDir()` — the same shape as `readdir()` — so one loop
   works over every backend.

## A note on the name

`Filesystem`, one word — the way PHP spells it in `FilesystemIterator`, and the way
Symfony, Laravel, Flysystem and Composer all spell it. `FileSystem` is the older,
Java-flavoured spelling, and mixing the two in one codebase is a permanent papercut.

## Testing

```bash
composer test
# or
vendor/bin/phpunit -c phpunit.xml
```

24 tests, 97 assertions. The suite runs without `composer install` — `tests/bootstrap.php`
bridges to the sibling `patterns/value-object` when it is not installed. It pins the
shape of both contracts (add a method and it fails), proves the port is satisfiable by
an array with no base class, and checks that `FileStats` normalizes and round-trips.

## License

MIT
