# Cloudflare integration tests

Pest/Testbench tests cover provider discovery and publishing, explicit ingress trust, nullable header parsing, Blade usage and request/container isolation. `CoexistenceTest.php` boots the context provider with representative API-wrapper fixtures in both provider orders; these synthetic checks do not claim real third-party package installation.

Namespaced configuration tests verify legacy fallback, explicit empty/invalid values failing closed, canonical publishing without overwriting a companion file and configuration-cache precedence. Manifest tests verify the collision-resistant alias independently of the common legacy alias. Real consuming-package verification is tracked separately in the project wiki.

`TestingTest.php` verifies native facade mocks through typed routed injection, real parser/Blade fallbacks with known/missing/malformed/untrusted fixtures, explicit-empty trust and fresh-application mock teardown. The fixture itself adds no application state or production fake/reset API; its independent copying and readonly behavior are covered in `tests/RequestFixture/`.

`GeographyTest.php` checks numeric coordinate bounds and zero, string metro identifiers, malformed/duplicate/unsafe headers, immediate-peer trust, old constructor positions, independent nullable values, request isolation and Blade fallbacks. No geocoding, postal-code enrichment or network is involved.

`DiagnosticsTest.php` checks redacted typed header/ingress states against actual parsing and request/container changes. `DoctorTest.php` exercises the real Artisan command, isolated samples, native language overrides/catalog completeness, stable JSON, unsafe/empty configuration guidance and preservation of the active request/configuration. No public diagnostic route or live probe is involved.

Tests use documentation addresses and synthetic token strings, prevent ordinary HTTP requests, and restore temporary containers/publication maps/files. Run `composer test` from the package root in a supported PHP environment. The [wiki](https://github.com/moztopia/moztopia-cloudflare/wiki/Development-and-verification) is the contributor reference.
