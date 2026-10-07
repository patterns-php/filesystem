<?php

declare(strict_types=1);

namespace Patterns\Tests\Fakes;

use Patterns\IFilesystem;
use RuntimeException;

/**
 * The smallest thing that satisfies the port: an array, and no base class.
 *
 * If this class ever becomes hard to write, the port has grown a policy it
 * should not have. Directories are implicit here, exactly as they are on S3.
 */
final class FakeFilesystem implements IFilesystem
{
    /** @var array<string, string> */
    private array $files;

    /**
     * @param array<string, string> $files
     */
    public function __construct(array $files = [])
    {
        $this->files = $files;
    }

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

    /**
     * @return list<string>
     */
    public function readDir(string $path): array
    {
        $prefix = $path === '' || str_ends_with($path, '/') ? $path : $path . '/';
        $entries = [];

        foreach (array_keys($this->files) as $key) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            $rest = substr($key, strlen($prefix));
            $slash = strpos($rest, '/');

            $entries[] = $slash === false ? $rest : substr($rest, 0, $slash);
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

    /**
     * Not part of the port - a test convenience.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        return $this->files;
    }
}
