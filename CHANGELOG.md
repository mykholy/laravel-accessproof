# Changelog

All notable user-facing changes are documented here.

## 1.1.0 — 2026-10-13

- Added `compareRead()` to verify owner-access and outsider-denial against the same GET route.
- Added optional 2xx owner status and protected response marker checks.
- Added negative regression tests for missing routes, public resources and denied responses that still leak private data.
- Exercised the paired API in a clean Laravel consumer app and documented its boundaries.

## 1.0.1 — 2026-10-13

- Expanded README with copyable owner/outsider tests, persisted-state assertions, API reference and explicit safety boundaries.
- Added a practical authorization scenario guide and GitHub repository profile metadata instructions.
- Clarified Packagist installation and support metadata.
- Added a regression fixture proving that a 403 response can still leak protected data.

## 1.0.0 — 2026-10-13

- Introduced the single-use JSON HTTP authorization probe API.
- Added watched model snapshots and scoped row-count assertions.
- Added explicit allowed, forbidden, denied, unauthenticated and response-marker assertions.
- Established a PHP 8.3/8.4 and Laravel 12/13 matrix, MySQL/PostgreSQL coverage and an independent Laravel application installation smoke test.

See [GitHub Releases](https://github.com/mykholy/laravel-accessproof/releases) for tagged release notes.
