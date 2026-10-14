# Laravel AccessProof v1.2.1

This backwards-compatible patch focuses on **field-tested documentation and installation assurance**. The public PHP API and runtime implementation are unchanged from v1.2.0.

## Independent SaaS verification

The published package was installed as a Composer dev dependency in a separate private multi-tenant Laravel SaaS application and executed against actual application routes with disposable fixtures.

- Nine authorization defects were reproduced in the host application before fixes: two tenant-selection issues, four same-tenant object/role issues, and three cross-tenant related-record references.
- After application fixes, **34 tests / 86 assertions passed on SQLite** and again on **MySQL 8**.
- The host repository and customer systems remain private; this is a scoped regression-testing field report, not a comprehensive security certification.

See the [anonymized field report](docs/FIELD_REPORT.md) and updated [scenario cookbook](docs/SCENARIOS.md).

## Publication quality

A dedicated independent Laravel 13 consumer job now installs the current **stable Packagist package**, while another consumer job installs the checked-out source. The fixture also exercises `watchRows()` with actual SQLite records.

## Install

```bash
composer require --dev mykholy/laravel-accessproof:^1.2
```

Supported: PHP 8.3+, Laravel 12/13; verified with PHP 8.3/8.4, SQLite, MySQL 8, PostgreSQL 16 and an independent Laravel 13 application.

Use only with isolated test databases and synthetic fixtures. AccessProof neither installs authorization policies nor crawls routes or monitors remote side effects.
