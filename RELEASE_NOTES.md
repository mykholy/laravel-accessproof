# Laravel AccessProof v1.2.0

A backwards-compatible feature release for more complete persisted-state regression tests.

## New: watchRows()

```php
AccessProof::request($this)
    ->as($outsider)
    ->watchRows('orders', ['tenant_id' => $targetTenantId], ['status'])
    ->postJson('/api/orders/batch-action')
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

The new watch compares matching row identities and selected persisted values. It catches unauthorized updates or row replacements even when counts stay constant. It requires an explicit equality predicate and a unique single-column key, uses raw query builder reads, and refuses to capture more than 500 matching rows.

The existing `watch()` and `watchCount()` methods remain available and unchanged.

## Package correctness

PHPUnit is now explicitly declared in the runtime dependency set. This is needed because the public API uses PHPUnit assertion classes and test-case type hints.

## Compatibility

The test suite runs with PHP 8.3/8.4, Laravel 12/13, SQLite, MySQL 8, PostgreSQL 16 and an independent Laravel installation. New negative fixtures cover updates to other rows, net-zero row replacements and bounded snapshot validation.

## Install

```bash
composer require --dev mykholy/laravel-accessproof:^1.2
```

AccessProof runs only in Laravel testing environments. It neither discovers routes automatically nor certifies application security.
