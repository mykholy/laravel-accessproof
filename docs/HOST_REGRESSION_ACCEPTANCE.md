# Host regression and security acceptance

This checklist supplements the package's existing tests and does not assert that any external host has passed acceptance.

## Authorization regression acceptance

Run an unauthorized cross-tenant read and write against a real Laravel host. Assert denial **and** unchanged persisted fields; also inspect events, jobs and other side effects. Include an authorized control request to ensure the route works. The package does not install policies or prove complete route coverage.

## Verification record

Record the exact package tag and source SHA, PHP and Laravel versions, database driver/version, test command, sanitized pass/fail output, and relevant host commit. Do not publish secrets or real customer data.

## Laravel framework security baseline (October 2026)

For any host using Laravel's built-in token-based API guard, review [GHSA-f9x3-7vq9-cph6](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6). The advisory identifies patched framework versions **12.68.0+** and **13.29.0+**. Check the host's resolved Composer lockfile and guard configuration; this is not evidence that this package itself contains the vulnerability.

## Completion policy

- Add executable regression tests and link passing CI evidence before claiming code remediation.
- Distinguish package-level behavior from host-owned authentication, authorization, external provider and deployment responsibilities.
- Do not claim independent security approval or production readiness without independently supplied evidence.
