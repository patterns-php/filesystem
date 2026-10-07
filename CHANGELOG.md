# Changelog

All notable changes to `patterns/filesystem` are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) ·
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
