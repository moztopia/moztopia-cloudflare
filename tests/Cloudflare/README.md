# Cloudflare integration tests

Pest/Testbench tests cover provider discovery and publishing, explicit ingress trust, nullable header parsing, Blade usage and request/container isolation. `CoexistenceTest.php` boots the context provider with representative API-wrapper fixtures in both provider orders; these synthetic checks do not claim real third-party package installation.

Namespaced configuration tests verify legacy fallback, explicit empty/invalid values failing closed, canonical publishing without overwriting a companion file and configuration-cache precedence. Manifest tests verify the collision-resistant alias independently of the common legacy alias. Real consuming-package verification is tracked separately in the project wiki.

`TestingTest.php` verifies native facade mocks through typed routed injection, real parser/Blade fallbacks with known/missing/malformed/untrusted fixtures, explicit-empty trust and fresh-application mock teardown. The fixture itself adds no application state or production fake/reset API; its independent copying and readonly behavior are covered in `tests/RequestFixture/`.

Tests use documentation addresses and synthetic token strings, prevent ordinary HTTP requests, and restore temporary containers/publication maps/files. Run `composer test` from the package root in a supported PHP environment. The [wiki](https://github.com/moztopia/moztopia-cloudflare/wiki/Development-and-verification) is the contributor reference.
