# Laravel AccessProof

**Test what users can actually access — and what denied requests might still change.**

[![Tests](https://github.com/mykholy/laravel-accessproof/actions/workflows/tests.yml/badge.svg)](https://github.com/mykholy/laravel-accessproof/actions/workflows/tests.yml)
[![Latest release](https://img.shields.io/github/v/release/mykholy/laravel-accessproof)](https://github.com/mykholy/laravel-accessproof/releases)
[![Packagist](https://img.shields.io/packagist/v/mykholy/laravel-accessproof)](https://packagist.org/packages/mykholy/laravel-accessproof)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)](composer.json)
[![License](https://img.shields.io/github/license/mykholy/laravel-accessproof)](LICENSE)

AccessProof is a small, test-only Laravel package for **runtime authorization regression testing**. It runs real in-process HTTP requests using your application's routes, authentication and authorization rules, then asserts the expected response and, where requested, the persisted database state.

It is particularly useful for catching **broken object-level authorization (BOLA/IDOR)**, cross-tenant record access and writes that occur **before** a controller returns a denial.

**AccessProof does not create permissions or policies.** It tests the behavior of the ones your application already has. It is not a route crawler or a security certification tool.

## Why it exists

Consider a controller that updates an order and *then* responds with HTTP 403. A status-only test passes, even though the record was changed. AccessProof can combine the response assertion with a database snapshot:

```php
AccessProof::request($this)
    ->as($outsider)
    ->watch($order, ['tenant_id', 'status'])
    ->patchJson('/api/orders/'.$order->getKey(), ['status' => 'paid'])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

The test fails if the response is not 403/404 **or** the watched record changed. No special application middleware is installed.

## Installation

Requires PHP **8.3+** and Laravel **12 or 13**.

```bash
composer require --dev mykholy/laravel-accessproof:^1.0
```

Install it in **require-dev**, not in production dependencies. No configuration, migration, service provider or Artisan command is required. Use it from your application's Laravel Feature Tests (PHPUnit).

## Quick start: an ownership regression test

This example assumes your application already has an `Order` model, a `User` model, factories and a protected `GET /api/orders/{id}` route. Adapt the fields and endpoints to your project:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Mykholy\AccessProof\AccessProof;
use Tests\TestCase;

final class OrderAuthorizationTest extends TestCase
{
    public function test_a_user_cannot_read_another_tenants_order(): void
    {
        $owner = User::factory()->create(['tenant_id' => 20]);
        $outsider = User::factory()->create(['tenant_id' => 10]);
        $order = Order::factory()->create(['tenant_id' => 20]);

        $uri = '/api/orders/'.$order->getKey();

        // Positive control: prove this route exists and the owner can use it.
        AccessProof::request($this)
            ->as($owner)
            ->getJson($uri)
            ->send()
            ->assertAllowed();

        // Cross-tenant control: the other tenant must be denied.
        AccessProof::request($this)
            ->as($outsider)
            ->getJson($uri)
            ->send()
            ->assertDenied();
    }
}
```

**Why the positive control matters:** `assertDenied()` accepts 403 or 404, because some applications intentionally return 404 for inaccessible records. A nonexistent route can also return 404. Always verify the same route with an authorized user in a separate control.

## Check unauthorized writes

### Updates and deletes of existing rows

Call `watch()` before `send()`. You can name specific columns, or omit the list to watch all persisted columns:

```php
AccessProof::request($this)
    ->as($outsider)
    ->watch($order, ['status', 'tenant_id'])
    ->patchJson('/api/orders/'.$order->getKey(), ['status' => 'paid'])
    ->send()
    ->assertForbidden()
    ->assertUnchanged();
```

`assertUnchanged()` performs unscoped SQL reads of the watched row after the request. It can detect updates or deletion of that row even if a tenant global scope would normally hide it. It does **not** watch every record in the database.

### Creates and deletes that change a row count

Use `watchCount()` to check the size of a selected set. It is useful when a denied POST must not insert rows:

```php
AccessProof::request($this)
    ->as($outsider)
    ->watchCount('orders', ['tenant_id' => $targetTenantId])
    ->postJson('/api/orders', [
        'tenant_id' => $targetTenantId,
        'status' => 'pending',
    ])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

A count watch detects **net row-count changes only**. It cannot detect updates or an insert plus delete that cancel each other out. The optional third argument selects a named Laravel database connection.

### Response-body disclosure

If a resource contains a unique synthetic marker, assert that it never appears in an unauthorized response:

```php
AccessProof::request($this)
    ->as($outsider)
    ->getJson('/api/orders/'.$order->getKey())
    ->send()
    ->assertDenied()
    ->assertBodyDoesNotContain('ACCESSPROOF_TEST_SECRET_42');
```

Use a deliberately created fixture marker, not real credentials or customer data.

## API reference

| Method | Behavior |
| --- | --- |
| `AccessProof::request($this)` | Begin a single-use probe from a Laravel PHPUnit Feature TestCase |
| `as($user, $guard = null)` | Authenticate the given user (optionally with a named guard) |
| `withHeaders(['X-Key' => 'value'])` | Include request headers |
| `getJson($uri)` | Configure a GET request |
| `postJson($uri, $data)` | Configure a POST request |
| `putJson($uri, $data)` | Configure a PUT request |
| `patchJson($uri, $data)` | Configure a PATCH request |
| `deleteJson($uri, $data)` | Configure a DELETE request |
| `watch($model, $columns = [])` | Snapshot an existing Eloquent record |
| `watchCount($table, $where = [], $connection = null)` | Snapshot the matching SQL row count |
| `send()` | Execute one in-process request and obtain a probe result |
| `assertAllowed($status = 200)` | Assert an expected authorized status |
| `assertDenied()` | Assert HTTP 403 or 404 |
| `assertForbidden()` | Assert HTTP 403 specifically |
| `assertUnauthenticated()` | Assert HTTP 401 specifically |
| `assertBodyDoesNotContain($marker)` | Ensure the raw response body omits a marker |
| `assertUnchanged()` | Ensure every watched row/count is unchanged |
| `response()` | Access Laravel's underlying `TestResponse` |

Each probe accepts exactly **one request** and cannot be reused after `send()`. Probe configuration must happen before sending.

## Safety and limitations

- **Test environment only.** AccessProof refuses to send requests unless Laravel reports `APP_ENV=testing`. That check cannot detect a misconfigured database connection. **Never point the testing environment at a production database.**
- **Explicit scenarios only.** It does not discover all routes or prove full authorization coverage; you must write tests for meaningful actor/resource/action combinations.
- **HTTP response is not the whole story.** Always pair denial assertions with owner controls and, for writes, relevant persisted-state watches.
- **No asynchronous or external side-effect monitoring.** Queues, remote APIs, emails, files and other databases are not observed unless explicitly checked by your host test.
- **Watch what matters.** An unwatched row, a transient mutation later reverted, or two count changes that net to zero can escape detection.
- **Authentication persists in the host test case.** `as()` uses Laravel's `actingAs()`. Put guest tests in a fresh test method instead of assuming another probe automatically logs out.

See [Testing scenarios](docs/SCENARIOS.md) and [Architecture / trust boundaries](docs/ARCHITECTURE.md) for practical guidance.

## Compatibility and quality checks

| PHP | Laravel | CI |
| --- | --- | --- |
| 8.3 | 12 | Automated |
| 8.4 | 12 | Automated |
| 8.3 | 13 | Automated |
| 8.4 | 13 | Automated |

The CI suite also exercises SQLite, MySQL 8 and PostgreSQL 16, and installs the package in an independent Laravel 13 application. [View the current CI results](https://github.com/mykholy/laravel-accessproof/actions/workflows/tests.yml).

For local development:

```bash
git clone https://github.com/mykholy/laravel-accessproof.git
cd laravel-accessproof
composer install
composer test
composer analyse
composer format-check
```

## Support and contributing

Bug reports, feature requests and reproducible authorization scenarios are welcome in [GitHub Issues](https://github.com/mykholy/laravel-accessproof/issues). Pull requests should include a regression test that fails when the issue is present.

For security-sensitive reports, read [SECURITY.md](SECURITY.md) before posting publicly. See [CONTRIBUTING.md](CONTRIBUTING.md) and the [changelog](CHANGELOG.md).

Maintained by **[Ahmed El-Kholy](https://github.com/mykholy)** · [LinkedIn](https://www.linkedin.com/in/mykholy/) · [Releases](https://github.com/mykholy/laravel-accessproof/releases)

MIT © 2026 Ahmed El-Kholy.
