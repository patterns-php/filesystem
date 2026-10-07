<?php

declare(strict_types=1);

namespace Patterns;

/**
 * IStatable - the optional ability to describe a path.
 *
 * Kept OUT of IFilesystem on purpose. A port must be satisfiable by every
 * adapter that claims it, and describing a path is not something every backend
 * can do: a local disk can, S3 can, a write-only sink cannot, and a memory
 * adapter may simply not care. Forcing stat() into the port would make those
 * adapters lie - usually by returning zeros, which is worse than saying no.
 *
 * So it is a capability, not a requirement:
 *
 *   if ($adapter instanceof IStatable) { $size = $adapter->stat($path)->size(); }
 *
 * Local and S3 implement it. The Filesystem unit implements it too, and refuses
 * at runtime when the adapter it was handed cannot - one clear error instead of
 * a plausible wrong answer.
 */
interface IStatable
{
    /**
     * Facts about a path.
     *
     * @throws \RuntimeException when the path cannot be inspected
     */
    public function stat(string $path): FileStats;
}
