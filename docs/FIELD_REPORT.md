# Field report: AccessProof in a multi-tenant SaaS

*October 2026 — anonymized application integration*

AccessProof started with deliberately vulnerable fixtures inside the package's own test suite. We then installed its published release as a dev-only dependency in a separate Laravel SaaS application's GitHub Actions runner and tested the application's existing routes, middleware, policies and database models. This report describes the test design and lessons. It does not identify the private repository, users, customers or production hosts.

## Environment and results

| Item | Scope |
| --- | --- |
| Application | Private, multi-tenant Laravel CRM/SaaS |
| Package | `mykholy/laravel-accessproof:^1.2` |
| Data | Synthetic users, tenants, conversations, requests and tickets |
| Isolation | Laravel test environment and disposable SQLite database |
| CI integration | Independent job in the application's existing test workflow |
| Final suite | **28 passing tests, 69 assertions** |
| Regressions reproduced before fixes | **6 authorization failures** across tenant-level and user-level boundaries |

The counts come from the private application's GitHub Actions logs. They are reported for transparency, not offered as independently reproducible public audit evidence. A passing suite proves only the actor-resource-action cases that were explicitly tested.

## What the tests found

The first set of scenarios exercised tenant isolation. Two tests reproduced cases where a request-supplied tenant identifier was trusted more than the authenticated actor:

- A ticket-list query accepted a foreign `tenant_id` and returned another tenant's fixture data.
- A ticket-create request accepted a foreign `tenant_id` and persisted a ticket in the wrong tenant.

After those fixes, the team ran a second set of scenarios **within the same tenant**. Four more tests reproduced overbroad access:

- One customer could read another customer's ticket.
- A customer-facing list exposed another customer's ticket title.
- An unassigned employee could read a ticket assigned to a different employee.
- One customer could update another customer's ticket.

The application changes enforced tenant identity from the authenticated principal, then checked record-level visibility and separate permissions for modifying actions. Re-running the suite confirmed the authorization checks without breaking the tested owner/assignee controls.

These are application-level findings, **not vulnerabilities in AccessProof itself**, and no claim is made about any untested endpoint or production deployment.

## Representative tests

The examples below use generic model and route names. They are intentionally **illustrative** rather than verbatim extracts from the private codebase.

### Same-tenant BOLA: the route must work for one customer and deny another

```php
AccessProof::compareRead($this)
    ->owner($ticketOwner, 'sanctum')
    ->outsider($otherCustomer, 'sanctum')
    ->getJson('/api/tickets/'.$ticket->getKey())
    ->verify();
```

Both actors belong to the **same tenant**. A tenant-wide check alone would allow both; the positive owner control also prevents a nonexistent route from producing a misleading pass.

### Cross-tenant list leaks can return HTTP 200

```php
AccessProof::request($this)
    ->as($tenantAUser, 'sanctum')
    ->getJson('/api/tickets?tenant_id='.$tenantB->getKey())
    ->send()
    ->assertAllowed()
    ->assertBodyDoesNotContain('SYNTHETIC_TENANT_B_MARKER');
```

A list endpoint may correctly respond with 200 and still reveal foreign data. A distinctive synthetic marker provides an explicit disclosure assertion; it must never be a real customer's secret.

### Denied writes must preserve state

```php
AccessProof::request($this)
    ->as($otherCustomer, 'sanctum')
    ->watch($ticket, ['tenant_id', 'subject', 'status'])
    ->putJson('/api/tickets/'.$ticket->getKey(), [
        'subject' => 'unauthorized replacement',
    ])
    ->send()
    ->assertDenied()
    ->assertUnchanged();
```

A status assertion alone cannot prove that the write was blocked before a record changed. For new rows or bulk changes, use `watchRows()` or `watchCount()` as appropriate.

## Practical lessons

1. **Tenant boundaries and object permissions are separate controls.** A correct `tenant_id` filter does not prove that a customer owns the object or an employee is assigned to it.
2. **Use positive controls.** Without an owner success case, a broken route can look like a successful denial.
3. **Inspect response contents.** For list endpoints, a 200 status is expected; data isolation must be asserted separately.
4. **Watch persisted state on denied writes.** Cover the target row and related scoped rows where the action might insert or replace data.
5. **Preserve existing authorization flows.** A fix should be tested against allowed owners and agents, not just forbidden outsiders.
6. **Keep tests out of production.** The application used disposable fixtures and a dev-only dependency; none of these tests called a live customer endpoint.

## Boundaries

This work is a targeted regression-testing pilot, **not** a comprehensive penetration test, route crawler, certification, or proof of production deployment. AccessProof cannot observe remote effects such as external messaging, asynchronous jobs, or side effects outside watched database state. See [the main documentation](../README.md#safety-and-limitations) for additional caveats.

For reproducible public examples, consult [the scenario cookbook](SCENARIOS.md) and [package tests](../tests). If you find an authorization false positive or an unsupported scenario, open a minimal synthetic reproduction in [GitHub Issues](https://github.com/mykholy/laravel-accessproof/issues).
