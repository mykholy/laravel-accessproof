# Laravel AccessProof v1.0.1

A documentation and regression-testing maintenance update, with no changes to the public API.

## Improvements

- Thorough README with install instructions, tested scenarios, sample PHPUnit feature tests and full API reference.
- New test cookbook for ownership, cross-tenant access, denied writes and response-body leaks.
- Clarified test-environment safety, unobserved side effects and 404 false positives.
- Updated package description, discovery keywords and maintainer/support links.
- A new negative regression fixture ensures that a 403 response containing protected data is caught by the leak assertion.

## Compatibility

- PHP 8.3+; Laravel 12/13.
- PHPUnit, PHPStan, Pint, MySQL 8, PostgreSQL 16 and independent consumer installation are exercised in CI.

## Installation

~~~bash
composer require --dev mykholy/laravel-accessproof:^1.0
~~~

See [README](README.md) for usage and limitations. AccessProof is an explicit test library, not an automatic security scanner.
