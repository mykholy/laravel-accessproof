# AccessProof: developer field guide

A pragmatic way to add **authorization regression checks** to an existing Laravel 12/13 application's PHPUnit feature tests. This guide is for engineers introducing tests to a real application, not for declaring that a security assessment is complete.

## Decision guide

| Risk to prove | Primary assertion | Independent control |
| --- | --- | --- |
| Another tenant reads an order (BOLA/IDOR) | `compareRead()->owner(...)->outsider(...)->getJson(...)->verify()` | Authorized owner must get the expected 2xx |
| Outsider updates a record before returning 403 | `request()->as(...)->watch(...)->patchJson(...)->send()->assertDenied()->assertUnchanged()` | Query the affected business row independently |
| Denied POST creates or deletes records | `watchCount(...)` plus `assertUnchanged()` | Check any other relevant tables/effects |
| Delete + recreate with unchanged count | `watchRows(...)` plus `assertUnchanged()` | Select a bounded predicate and stable key |
| Denied response includes sensitive fixture marker | `assertBodyDoesNotContain('SYNTHETIC_MARKER')` | Verify the route works for its owner |

## Minimal practical adoption

Run `composer require --dev mykholy/laravel-accessproof:^1.2` and create a standard Laravel feature test:

```php
use Mykholy\AccessProof\AccessProof;

// $owner, $outsider and $order must be separately created
// by YOUR app's factories and authorization rules.
$uri = '/api/orders/'.$order->getKey();

AccessProof::compareRead($this)
    ->owner($owner)
    ->outsider($outsider)
    ->getJson($uri)
    ->verify();

AccessProof::request($this)
    ->as($outsider)
    ->watch($order, ['status', 'tenant_id'])
    ->patchJson($uri, ['status' => 'paid'])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

Use separate tests if your authentication state or side effects could interfere. Always verify a positive, authorized request; a 404 alone is not evidence of secure object authorization.

## Common causes of misleading tests

- **Route not registered:** a 404 might satisfy `assertDenied()`; first prove the owner succeeds.
- **Unwatched side effect:** watching an order row does not detect an extra audit, transfer, webhook or queue message; add ordinary app assertions.
- **Incorrect test database:** `APP_ENV=testing` is only a guard; check that DB/Redis/services are disposable before running.
- **Net-zero write:** `watchCount()` sees only count differences; use bounded `watchRows()` for identity/value snapshots (maximum 500 matching records).
- **Missing tenant context:** authentication isn't equivalent to tenant authorization; fixtures need both identities.

## Definition of a useful PR

A failed authorization test must be reproducible, identify **actor, resource, route, intended tenant boundary and effect**, and fail if either the response or watched state is wrong. Avoid real user data or secrets in tests/issues.

Further reading: [Scenario cookbook](SCENARIOS.md) · [Architecture and trust boundaries](ARCHITECTURE.md) · [Field report](FIELD_REPORT.md) · [Release checklist](RELEASE_CHECKLIST.md).
