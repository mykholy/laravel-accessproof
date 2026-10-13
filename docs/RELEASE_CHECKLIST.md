# Release verification record

This document distinguishes automated checks from operational reviews. It does not certify a Laravel application as secure.

## Automated gates

For every release commit, GitHub Actions checks:

- PHP 8.3 and 8.4 with Laravel 12 and 13.
- Composer dependency resolution and package metadata validation.
- PHPUnit Feature Tests including intentionally vulnerable test endpoints.
- PHPStan static analysis and Pint formatting.
- MySQL 8 and PostgreSQL 16 database test runs.
- Installation and HTTP smoke tests in a separate Laravel 13 application.

The [CI workflow](../.github/workflows/tests.yml) is the source of truth for the latest run. Releases are created only after a successful main-branch run with a matching current commit.

## Reviewed guarantees and boundaries

- AccessProof is installed as a developer dependency and uses in-process Laravel HTTP tests.
- Each probe sends one request in a testing environment only.
- Owners must have positive control tests; a 404 alone is ambiguous.
- Watched persisted models and row counts cover only explicitly declared state.
- Model reads bypass Eloquent global scopes but use the watched model's configured SQL connection.
- Database-per-tenant context must be established by the host application.
- External I/O, queued jobs and transient writes are outside automatic observation.
- The host's test database must be disposable; APP_ENV alone cannot guarantee isolation.

## Before changing VERSION for the next release

- [ ] Review the public API and backwards compatibility.
- [ ] Review new regression tests for false positives and false negatives.
- [ ] Run the CI matrix successfully on the intended release commit.
- [ ] Re-check README examples on a clean host application.
- [ ] Verify the Packagist repository and published tagged version.
- [ ] Check release notes, issue tracker and security-reporting instructions.

Historical tags remain immutable; publish a new patch release for fixes.
