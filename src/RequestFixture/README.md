# RequestFixture

`Moztopia\Cloudflare\RequestFixture\RequestFixture` supplies immutable synthetic HTTP test data. `trusted()` defaults to country `TH`; pass your complete header set to replace it. `missing()` supplies no headers, `malformed()` supplies invalid visitor/request values, and `untrusted()` supplies plausible headers from a different immediate peer. The addresses are reserved documentation addresses, not production proxy recommendations.

Construction never reads or modifies Laravel configuration, the live request, facade state, or networking. Apply the fixture explicitly in a test:

```php
use Moztopia\Cloudflare\RequestFixture\RequestFixture;

$fixture = RequestFixture::trusted(headers: ['CF-IPCountry' => 'TH']);
config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);

$this->withServerVariables($fixture->server)
    ->withHeaders($fixture->headers)
    ->get('/delivery')
    ->assertOk();
```

Use fresh request builders or clear their retained headers between consecutive requests. Laravel's test application lifecycle restores bindings/configuration between tests; the fixture adds no global state or reset API. Header strings and lists of strings are copied independently, including PHP references; mutable objects are rejected. Duplicate, malformed UTF-8 and invalid text remain available for parser tests.

For a view-only test, use an immutable `Visitor` snapshot. For native Laravel mocks, use the fully qualified package facade's `shouldReceive()`; freshly resolved typed dependency injection shares its binding. Already-held service objects remain their existing objects. The [wiki](https://github.com/moztopia/moztopia-cloudflare/wiki/Blade-and-testing) is the human-facing reference; this API remains unreleased until the core integration.
