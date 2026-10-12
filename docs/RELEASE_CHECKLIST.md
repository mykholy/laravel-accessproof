# Stable release gates

- [ ] Composer resolution on PHP 8.3/8.4, Laravel 12/13.
- [ ] PHPUnit feature and deliberately failing security fixtures pass.
- [ ] Pint and PHPStan pass without suppressions on release commit.
- [ ] Independent Laravel application runs owner, outsider and guest scenarios.
- [ ] MySQL and PostgreSQL watched-row and count tests.
- [ ] Database-per-tenant caveats validated.
- [ ] README examples executed in a clean test application.
- [ ] Git history checked for secrets, customer data and build artifacts.
- [ ] CI green on the exact release commit.
- [ ] MIT license and Packagist metadata verified.

Do not tag v1.0.0 until all release gates pass.
