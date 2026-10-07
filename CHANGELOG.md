# Changelog

All notable changes to `patterns/filesystem` are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) ·
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-07

### Changed

- `Patterns\FileStats` is now a **plain immutable class** instead of a
  `Patterns\ValueObject` subclass. It was carrying a base class and a package
  dependency for behaviour it can state in ten lines of its own: four readonly
  properties, typed accessors, `toArray()` and `jsonSerialize()`.
- The package therefore has **no dependencies at all** — `patterns/value-object`
  is no longer required. Published a day after 1.0.0 with no known consumers, so
  this ships as a minor rather than a major; note that `FileStats` no longer
  answers to `instanceof ValueObject` and no longer inherits `equals()`.

[1.1.0]: https://github.com/patterns-php/filesystem/releases/tag/v1.1.0

## [1.0.0] - 2026-10-07

### Added

- `Patterns\IFilesystem` — the port: seven operations every storage backend can
  honour (`exists`, `readFile`, `writeFile`, `deleteFile`, `readDir`, `ensureDir`,
  `deleteDir`). Failures throw; a path is always a string.
- `Patterns\IStatable` — the capability: `stat()`, opted into by the backends that
  can describe a path, so the port never asks anything a backend cannot answer.
- `Patterns\FileStats` — the shape `stat()` returns: `path`, `size`, `modified`,
  `isDir`. A ValueObject, so equality and JSON round-trip work.
- A contract test suite that pins the shape of both interfaces, proves the port is
  satisfiable by an in-memory class with no base class, and checks that `FileStats`
  normalizes an epoch, a date string and a date object into one representation.

Deliberately not in this pattern: `chmod`, `clear`, `copyFile`, `copyDirectory`,
`searchAndDestroy`, `stat` (see `IStatable`), `*Sync`/`*Async` suffixes, caching and
retries. See the README for the reasoning behind each.

[1.0.0]: https://github.com/patterns-php/filesystem/releases/tag/v1.0.0
