<?php

declare(strict_types=1);

namespace Patterns;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * FileStats - the shape IStatable::stat() returns.
 *
 * A ValueObject, not a Unit: it is data about a moment, it does nothing, and it
 * has no version of its own. Two stats that describe the same path the same way
 * are the same value.
 *
 * Four facts only. `mode`, `isSymbolicLink` and `atime` are deliberately
 * absent: they are POSIX detail, and a shape that half the backends implementing
 * IStatable cannot fill is a lie told in the type system.
 *
 * `modified` is stored as an ISO-8601 string rather than a DateTimeImmutable so
 * that equality, JSON and a round trip through persisted provenance all agree
 * on one representation.
 */
final class FileStats extends ValueObject
{
    /**
     * @param array<string, mixed> $props
     */
    private function __construct(array $props)
    {
        parent::__construct($props);
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

        return new self([
            'path'     => $path,
            'size'     => (int) ($props['size'] ?? 0),
            'modified' => self::moment($props['modified'] ?? null),
            'isDir'    => (bool) ($props['isDir'] ?? false),
        ]);
    }

    public function path(): string
    {
        return $this->props['path'];
    }

    public function size(): int
    {
        return $this->props['size'];
    }

    public function modified(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->props['modified']);
    }

    public function isDir(): bool
    {
        return $this->props['isDir'];
    }

    /**
     * @return array{path: string, size: int, modified: string, isDir: bool}
     */
    public function toArray(): array
    {
        return $this->toProps();
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
