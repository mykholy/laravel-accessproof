# Design and trust boundaries

The package is a companion to Laravel Feature Tests. The host TestCase owns application and database lifecycle. AccessProof does not install authentication, middleware, routes or tenant resolution.

PendingProbe captures an actor, HTTP request, headers and watched models/counts, then sends exactly one request using the Laravel in-process test client.

ModelSnapshot uses the watched model's bound SQL connection and a raw query builder, not Eloquent global scopes. TableCountSnapshot uses a named SQL connection or the default. Both capture before the request and compare after it.

Neither snapshot can detect transient changes later reverted, unobserved tables or writes outside watched state. Optional queue and event watches use scoped Laravel fakes during the HTTP request to inspect dispatch attempts; they do not monitor async worker outcomes, external providers, after-response dispatch outside the request, or other processes. Faking events may suppress listeners and alter application behavior. Opt into specific domain events when possible. A 404 may also indicate a missing route. Always add a positive owner control using an authorized actor.

Laravel actingAs changes authentication state in the host TestCase. Guest tests should run in a fresh test case. Always use disposable databases and synthetic fixtures; APP_ENV alone cannot guarantee isolation.
