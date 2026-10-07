<?php

declare(strict_types=1);

namespace Patterns\Tests\Fakes;

use DateTimeImmutable;
use Patterns\FileStats;
use Patterns\IFilesystem;
use Patterns\IStatable;

/**
 * The port plus the capability: everything IFilesystem asks for, forwarded, and
 * a stat() on top. No base class - the capability composes, it does not inherit.
 */
final class FakeStatableFilesystem implements IFilesystem, IStatable
{
    private IFilesystem $inner;

    public function __construct(?IFilesystem $inner = null)
    {
        $this->inner = $inner ?? new FakeFilesystem();
    }

    public function exists(string $path): bool
    {
        return $this->inner->exists($path);
    }

    public function readFile(string $path): string
    {
        return $this->inner->readFile($path);
    }

    public function writeFile(string $path, string $data): void
    {
        $this->inner->writeFile($path, $data);
    }

    public function deleteFile(string $path): void
    {
        $this->inner->deleteFile($path);
    }

    /**
     * @return list<string>
     */
    public function readDir(string $path): array
    {
        return $this->inner->readDir($path);
    }

    public function ensureDir(string $path): void
    {
        $this->inner->ensureDir($path);
    }

    public function deleteDir(string $path): void
    {
        $this->inner->deleteDir($path);
    }

    public function stat(string $path): FileStats
    {
        return FileStats::create([
            'path'     => $path,
            'size'     => strlen($this->inner->readFile($path)),
            'modified' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            'isDir'    => false,
        ]);
    }
}
