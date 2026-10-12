# Laravel AccessProof v1.0.0

The first stable release of the deliberately narrow AccessProof HTTP authorization testing API.

## Included

- In-process JSON GET/POST/PUT/PATCH/DELETE feature-test probes with an explicit authenticated actor.
- Status assertions for allowed, forbidden, denied or unauthenticated requests.
- Response-body marker checks to detect disclosure of synthetic protected values.
- Eloquent model snapshots using unscoped SQL reads to catch watched row updates/deletes after denied requests.
- Scoped row-count watches to catch changes to matching record counts, including unauthorized inserts.
- Explicit test-environment guard and single-use request builders.
- Tests covering intentionally broken authorization boundaries.

## Verified compatibility

- PHP 8.3/8.4 with Laravel 12/13, using matching Orchestra Testbench versions.
- SQLite, MySQL 8 and PostgreSQL 16 integration runs.
- Independent Laravel 13 consumer installation and HTTP authorization fixture.
- Composer validation, PHPUnit, PHPStan and Pint.

## Boundaries

AccessProof verifies only the HTTP interactions and database state a test explicitly declares. A 404 may originate from a missing route. Unwatched records, asynchronous jobs, external services and transient writes are not inspected. The package is not a security certification or an automatic authorization scanner.

## Installation

After registering the GitHub repository with Packagist, install with:

    composer require --dev mykholy/laravel-accessproof:^1.0

The author must perform the initial Packagist repository submission. A GitHub Release does not automatically register a new package with Packagist.

MIT © 2026 Ahmed El-Kholy.
