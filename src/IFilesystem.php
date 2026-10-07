<?php

declare(strict_types=1);

namespace Patterns;

/**
 * IFilesystem - the Filesystem pattern.
 *
 * Code writes files. WHERE they land is a deployment detail: a local disk in
 * development, S3 in production, memory in tests. This interface is that
 * sentence turned into a contract - the seven things every backend must be able
 * to do, and nothing else.
 *
 * Ported from @synet/patterns' IFileSystem, then cut back to what a *port* can
 * honestly promise:
 *
 *   - The *Sync suffix   an implementation's era, not a contract. PHP has one
 *                        call model; the suffix would only fossilise it.
 *   - chmod()            POSIX authority is not a filesystem fact. Object
 *                        stores have no permission bits, so promising chmod
 *                        here would make the contract unsatisfiable for half
 *                        the backends it claims to describe. Adapters that do
 *                        have it (Units\Fs\Adapters\Local) expose it as their
 *                        own method.
 *   - clear()            derivable: readDir() then deleteFile() each.
 *   - stat()             NOT every backend can describe a path. See IStatable:
 *                        a capability an adapter adds when it can, rather than
 *                        a promise every adapter then has to fake.
 *
 * "Filesystem" is one word, the way PHP spells it in FilesystemIterator, and
 * the way Symfony, Laravel, Flysystem and Composer all spell it.
 *
 * FAILURES THROW. An I/O failure is not a value to branch on inside the port -
 * a throwing port is satisfiable by any native client, and a caller who wants a
 * Result can wrap the call. Returning Result here would force every adapter to
 * build one, which is policy, not shape.
 *
 * Depend on this, never on a concrete adapter. The local disk, S3, memory and
 * the Filesystem unit itself are interchangeable behind it.
 */
interface IFilesystem
{
    /**
     * Does this path exist?
     */
    public function exists(string $path): bool;

    /**
     * Read a file as text.
     *
     * @throws \RuntimeException when the path cannot be read
     */
    public function readFile(string $path): string;

    /**
     * Write text to a file, creating parents as needed.
     *
     * @throws \RuntimeException when the path cannot be written
     */
    public function writeFile(string $path, string $data): void;

    /**
     * Delete a file. Deleting a directory is deleteDir().
     *
     * @throws \RuntimeException when the path cannot be deleted
     */
    public function deleteFile(string $path): void;

    /**
     * The entries directly inside a directory.
     *
     * Names, not paths - the same shape as readdir(). Whether a backend can
     * distinguish a file from a "directory" is its own business.
     *
     * @return list<string>
     * @throws \RuntimeException when the directory cannot be read
     */
    public function readDir(string $path): array;

    /**
     * Make sure a directory exists. Idempotent, and recursive.
     *
     * An object store has no directories to create; an adapter there is allowed
     * to treat this as already satisfied, as long as it says so.
     *
     * @throws \RuntimeException when the directory cannot be created
     */
    public function ensureDir(string $path): void;

    /**
     * Delete a directory and everything inside it.
     *
     * @throws \RuntimeException when the directory cannot be removed
     */
    public function deleteDir(string $path): void;
}
