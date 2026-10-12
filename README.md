# Laravel AccessProof

Runtime authorization regression tests for Laravel 12 and 13.

AccessProof provides explicit actor, resource and persisted-state assertions inside ordinary Laravel Feature Tests. It does not crawl every route, infer ownership, or certify an application secure.

## Install

Once a tagged version is published on Packagist:

```bash
composer require --dev mykholy/laravel-accessproof
```

Before publication, use a Composer VCS repository pointing to this GitHub project.

## Example

```php
AccessProof::request($this)
    ->as($outsider)
    ->watch($order, ['tenant_id', 'status'])
    ->patchJson('/api/orders/'.$order->getKey(), ['status' => 'paid'])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

For denied creates, use `watchCount('orders', ['tenant_id' => $tenantId])` and `assertUnchanged()`. Counting detects net count changes only, and `watch()` tracks existing rows only. An HTTP 404 can mean a missing route: also exercise a positive owner control. Authentication state persists within the host test case, so use a new test case for guest scenarios.

## Boundaries

Runs only when the host Laravel application environment is `testing`; use a disposable database as this environment flag cannot prevent a misconfigured production connection. Tests invoke in-process Laravel requests, not external HTTP services. Coverage applies only to explicitly exercised scenarios and watched state. Asynchronous side effects, writes to other rows, transient mutations and external APIs are outside the engine's scope.

`assertDenied()` requires status 403 or 404; `assertForbidden()` requires 403; `assertUnauthenticated()` requires 401. `assertBodyDoesNotContain($marker)` searches the raw response body for a unique synthetic marker. `assertAllowed($status)` provides an owner control, and `response()` gives the underlying Laravel TestResponse.

## Development

```bash
composer install
composer test
composer analyse
composer format-check
```

Supported runtimes: PHP 8.3+, Laravel 12/13. Both framework versions must pass the CI matrix before tagging a stable release.

## Support

[Maintainer](https://www.linkedin.com/in/mykholy/) · [Security policy](SECURITY.md) · [Contributing](CONTRIBUTING.md)

MIT © 2026 Ahmed El-Kholy.
