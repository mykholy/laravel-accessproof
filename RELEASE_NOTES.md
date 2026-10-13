# Laravel AccessProof v1.1.0

A backwards-compatible feature release that closes a common authorization-testing blind spot: a 404 response can come from a missing route, not from a correct access policy.

## New: paired read boundary verification

```php
AccessProof::compareRead($this)
    ->owner($owner)
    ->outsider($outsider)
    ->getJson('/api/orders/'.$order->getKey())
    ->withoutBodyMarker('PRIVATE_TEST_MARKER')
    ->verify();
```

This tests the exact same URI with an authorized owner (HTTP 200 by default) and an unauthorized outsider (HTTP 403 or 404); the optional marker must not appear in the outsider response. Use `expectedOwnerStatus(204)` for a different successful 2xx contract.

## Test coverage

New intentionally failing fixtures for non-existent routes, exposed resources and denied responses that still leak protected markers. Exercised in the independent Laravel application as well as SQLite, MySQL 8 and PostgreSQL 16 CI matrix.

## Boundaries

The new helper only handles GET requests. Use the explicit watched database assertions for mutating requests. Authentication and tenant switching remain the host application's responsibility. It does not crawl routes, enumerate tenants or certify application security.

## Install

```bash
composer require --dev mykholy/laravel-accessproof:^1.1
```

See [README](README.md) and [Scenario cookbook](docs/SCENARIOS.md) for examples and safety requirements.
