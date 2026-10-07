<?php

declare(strict_types=1);

namespace Patterns;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * FileStats - the shape IStatable::stat() returns.
 *
 * A plain, immutable value: four facts about a path at a moment, plus the
 * ability to read them back and to persist them. Not a Unit - it has no
 * identity and no version of its own - and not a ValueObject subclass either:
 * it needs neither the base class nor the dependency, so it stands alone.
 *
 * Four facts only. `mode`, `isSymbolicLink` and `atime` are deliberately
 * absent: they are POSIX detail, and a shape that half the backends implementing
 * IStatable cannot fill is a lie told in the type system.
 *
 * `modified` is stored as an ISO-8601 string rather than a DateTimeImmutable so
 * that JSON and a round trip through persisted data agree on one representation.
 */
final class FileStats implements JsonSerializable
{
    private function __construct(
        private readonly string $path,
        private readonly int $size,
        private readonly string $modified,
        private readonly bool $isDir,
    ) {
    }

    /**
     * @param array<string, mixed> $props
     */
    public static function create(array $props): self
    {
        $path = (string) ($props['path'] ?? '');

        if ($path === '') {
            throw new InvalidArgumentException('FileStats: path is required');
        }

        return new self(
            $path,
            (int) ($props['size'] ?? 0),
            self::moment($props['modified'] ?? null),
            (bool) ($props['isDir'] ?? false),
        );
    }

    public function path(): string
    {
        return $this->path;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function modified(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->modified);
    }

    public function isDir(): bool
    {
        return $this->isDir;
    }

    /**
     * @return array{path: string, size: int, modified: string, isDir: bool}
     */
    public function toArray(): array
    {
        return [
            'path'     => $this->path,
            'size'     => $this->size,
            'modified' => $this->modified,
            'isDir'    => $this->isDir,
        ];
    }

    /**
     * @return array{path: string, size: int, modified: string, isDir: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Accept whatever a backend has at hand: an epoch, a date string, or a date
     * object. One representation comes out.
     */
    private static function moment(mixed $modified): string
    {
        if ($modified instanceof DateTimeInterface) {
            return $modified->format(DATE_ATOM);
        }

        if (is_int($modified)) {
            return (new DateTimeImmutable())->setTimestamp($modified)->format(DATE_ATOM);
        }

        if (is_string($modified) && $modified !== '') {
            $date = new DateTimeImmutable($modified);

            return $date->format(DATE_ATOM);
        }

        throw new InvalidArgumentException('FileStats: modified is required');
    }
}
