# Authorization scenario cookbook

AccessProof verifies behavior the host test explicitly invokes. Use these examples as a planning checklist, not as a claim of automatic coverage.

## Model the access boundary first

For each sensitive endpoint, identify:

1. **Actor:** guest, owner, colleague, outsider, or platform administrator.
2. **Resource:** a record belonging to the same tenant or another tenant.
3. **Action:** read, list, create, update, delete, export or download.
4. **Expectation:** HTTP 200/201 for authorized requests; HTTP 401/403/404 for blocked requests according to the application contract.
5. **Side effects:** which persisted records must remain unchanged after denial.

For a tenant-scoped `Order` resource, a compact test matrix might be:

| Actor | Resource tenant | GET | PATCH | DELETE |
| --- | --- | --- | --- | --- |
| Tenant A owner | A | Allowed | Allowed | Allowed |
| Tenant A colleague without access | A | Denied | Denied | Denied |
| Tenant A user | B | Denied | Denied | Denied |
| Guest | A/B | Unauthenticated | Unauthenticated | Unauthenticated |

Fill in the real policy for your application. Do not assume every endpoint uses the same HTTP status.

## 1. Pair denied and allowed reads

Use two different users against **the same URI**. The owner control prevents a nonexistent route from masquerading as a secure 404.

```php
$uri = '/api/orders/'.$order->getKey();

AccessProof::request($this)
    ->as($owner)
    ->getJson($uri)
    ->send()
    ->assertAllowed();

AccessProof::request($this)
    ->as($outsider)
    ->getJson($uri)
    ->send()
    ->assertDenied();
```

## 2. A denied update must not mutate data

```php
AccessProof::request($this)
    ->as($outsider)
    ->watch($order, ['status', 'tenant_id'])
    ->patchJson('/api/orders/'.$order->getKey(), ['status' => 'paid'])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

If the API rejects unauthorized writes with 404, use `assertDenied()`. If the contract requires 403, use `assertForbidden()`.

## 3. A denied create must not add records

```php
AccessProof::request($this)
    ->as($outsider)
    ->watchCount('orders', ['tenant_id' => $targetTenantId])
    ->postJson('/api/orders', ['tenant_id' => $targetTenantId])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

This is a row-count assertion. It cannot catch a denied create that inserts and deletes the same number of rows.

## 4. Test response disclosure with a fixture marker

Set a protected attribute of an order to a unique synthetic value. Then test that the response, including a 403 response with an error payload, never contains it.

```php
AccessProof::request($this)
    ->as($outsider)
    ->getJson('/api/orders/'.$order->getKey())
    ->send()
    ->assertDenied()
    ->assertBodyDoesNotContain('TEST_SECRET_42');
```

The method performs a substring check of the raw response body; it does not parse semantics or check response headers.

## 5. Guest vs authenticated

Do not confuse authentication and authorization. Guests often receive 401, while authenticated outsiders receive 403 or 404.

```php
AccessProof::request($this)
    ->getJson('/api/orders/'.$order->getKey())
    ->send()
    ->assertUnauthenticated();
```

Write the guest case in a separate PHPUnit test method. Laravel `actingAs()` persists on the host test case after a probe.

## 6. Multi-tenant applications

AccessProof does not resolve tenancy or switch databases for you. Your application must establish the correct tenant context before running each probe.

- **Shared-table tenancy:** use factories with different tenant IDs; watch specific rows and scoped row counts.
- **Separate-database tenancy:** ensure the host test switches the correct connection before taking snapshots; the watched Eloquent model uses its bound connection.
- **Queues and async workflows:** test them separately in your application's integration suite; AccessProof does not observe asynchronous effects.

## Common false positives

- A 404 caused by a missing route. Fix with an authorized user control.
- A denial that modifies a row you forgot to watch. Identify business-critical writes first.
- A create/delete pair that leaves row count unchanged. `watchCount()` alone is insufficient.
- A guest test after `as()`. Use a fresh test method.
- Testing against production through an incorrectly configured `APP_ENV=testing` database connection. Use dedicated disposable connections and fixtures.

## What to include in a bug report

Include your PHP/Laravel versions, the exact assertion, an isolated route/controller/policy fixture, and expected/actual responses. Remove production data, passwords and API keys.
