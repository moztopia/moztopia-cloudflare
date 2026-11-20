# Moztopia Cloudflare

`moztopia/cloudflare` gives Laravel applications credential-free access to Cloudflare visitor information and request metadata. Use it in controllers, services and Blade without making network requests.

```php
use Moztopia\Cloudflare\Facades\Cloudflare;

$visitor = Cloudflare::visitor();

$visitor->countryCode;   // "TH", when supplied and trusted
$visitor->continentCode; // "AS"
$visitor->region;        // Region/state name
$visitor->regionCode;
$visitor->city;
$visitor->timezone;      // "Asia/Bangkok"

$context = Cloudflare::requestContext();

$context->trusted;
$context->clientIp;
$context->rayId;
$context->scheme;        // "http" or "https"
```

All location and metadata values are nullable. Installing the package, resolving the facade and reading context perform no outbound requests. Location is an IP-based estimate; the consuming application decides how to use it.

## Requirements and installation

- Laravel 12 or 13.
- PHP 8.4.27+ within the 8.4 line, or 8.5.12+ within the 8.5 line.
- Cloudflare-proxied traffic through a known, controlled ingress.

This repository is preparing its initial `0.1.0` version. It has not yet published a stable package release. Once that version is available through your configured Composer repository:

```sh
composer require moztopia/cloudflare:^0.1
php artisan vendor:publish --tag=cloudflare-config
```

Composer discovery registers `CloudflareServiceProvider` and the `Cloudflare` facade alias. If the host disables discovery, register `Moztopia\Cloudflare\CloudflareServiceProvider` in the application's providers and import the fully qualified facade in PHP or Blade.

No migrations, queue workers, scheduler, credentials or HTTP client configuration are required.

## Configure trusted ingress

The default `config/cloudflare.php` accepts no ingress addresses. This makes all values unknown until the application explicitly identifies the immediate peers allowed to supply Cloudflare headers:

```php
return [
    'trusted_proxies' => [
        '192.0.2.10',       // Replace with your actual ingress address.
        '2001:db8::/48',    // Replace with your actual controlled ingress range.
    ],
];
```

The addresses above are documentation examples. Use real IPv4/IPv6 addresses or CIDR ranges for your topology. `*` is unsupported. The package checks the connection's `REMOTE_ADDR`, never a visitor-supplied forwarded IP. Invalid entries are ignored. Clear or rebuild the host's configuration cache after changing configuration.

For a direct Cloudflare-to-origin connection, configure the applicable [Cloudflare IP ranges](https://www.cloudflare.com/ips/). For Cloudflare Tunnel or another proxy hop, configure the immediate controlled proxy that reaches Laravel, and ensure it only forwards trustworthy Cloudflare metadata. Origin access restrictions and any header stripping belong to that ingress. A Cloudflare header or a trusted proxy alone does not authenticate a visitor.

The package does not alter Laravel's `TrustProxies` middleware or automatically reuse its trusted-proxy list. That middleware may trust load balancers that have not established Cloudflare header provenance. Configure Laravel's own proxy settings separately where the host needs forwarded URL or IP handling. If the web server rewrites `REMOTE_ADDR` to the visitor's IP, preserve an independently verifiable ingress peer before adopting this configuration; do not trust arbitrary visitor IPs to compensate.

## Enable the available Cloudflare headers

Enable **IP Geolocation** for `CF-IPCountry`. Enable the **Add visitor location headers** Managed Transform for additional fields such as continent, region, city and timezone. Reading these headers needs no Cloudflare API token. The package does not enable Cloudflare settings itself.

The mappings are:

- `countryCode`: `CF-IPCountry`, validated against the Symfony country-code dataset.
- `continentCode`: `CF-IPContinent` (`AF`, `AN`, `AS`, `EU`, `NA`, `OC` or `SA`).
- `region`, `regionCode`, `city`: `CF-Region`, `CF-Region-Code`, `CF-IPCity`.
- `timezone`: `CF-Timezone`, validated against PHP timezone identifiers.
- `clientIp`: a valid IPv4/IPv6 value in `CF-Connecting-IP`.
- `rayId`: a hexadecimal Ray identifier with a three-letter data-centre suffix from `CF-Ray`.
- `scheme`: the `http` or `https` scheme in `CF-Visitor` JSON.

Cloudflare's special `CF-IPCountry` values `XX` (unknown) and `T1` (Tor) are exposed as `countryMarker`. In either case `countryCode` is `null`; a marker is not a country. Missing headers, multiple header values, invalid encodings, control characters and malformed structured fields return `null`. Location values are bounded to 256 bytes. Free-form city and region names are preserved as UTF-8 text and should be escaped when rendered.

`requestContext()->trusted` describes whether the configured immediate peer was accepted. It can be `true` with missing metadata; it is not an availability check for every Cloudflare product. Worker subrequests and Pseudo IPv4 can affect the meaning of `CF-Connecting-IP`; see Cloudflare's documentation for your topology.

References: [Managed Transforms](https://developers.cloudflare.com/rules/transform/managed-transforms/reference/#add-visitor-location-headers), [Cloudflare request headers](https://developers.cloudflare.com/fundamentals/reference/http-headers/).

## Blade and controllers

The discovered facade alias works directly in Blade:

```blade
@php($visitor = Cloudflare::visitor())

@if ($visitor->countryCode === 'TH')
    <p>Delivery options for Thailand</p>
@endif

@if ($visitor->region)
    <p>Estimated region: {{ $visitor->region }}</p>
@endif
```

Alternatively, pass the snapshot from a controller:

```php
use Moztopia\Cloudflare\Facades\Cloudflare;

return view('delivery', ['visitor' => Cloudflare::visitor()]);
```

For dependency injection, request `Moztopia\Cloudflare\Cloudflare\Cloudflare` from Laravel's container and call the same `visitor()` and `requestContext()` methods. The returned `Visitor` and `RequestContext` objects are immutable. The service looks up the active request on each call rather than retaining a visitor in a singleton. Existing snapshots remain snapshots when the request changes, including in long-lived workers.

Unknown location should produce the application's normal fallback. Country does not automatically select a language, currency, timezone or delivery policy. The package changes none of those settings.

## Shared-cache behavior

Location-dependent HTML must not be shared indiscriminately between visitors. Ensure Cloudflare/full-page caches either bypass these personalized responses or use a deliberately configured variation key that matches the application's geographic decision. A Blade conditional alone does not create a distinct CDN cache entry. Cache hits served entirely by Cloudflare do not execute Laravel.

## Scope

This first version focuses on application-facing context. It does not probe `/cdn-cgi/trace`, administer Cloudflare accounts or discover every enabled product. A future trace client can be a separate diagnostic API: a request sent by Laravel describes the server's connection, rather than the incoming visitor.

## Development

```sh
composer install
composer validate --strict
composer test
composer lint
```

Pest uses Orchestra Testbench with synthetic requests; ordinary tests need no Cloudflare account, live domain or network access. Dependencies and `composer.lock` are not tracked for this library, so each supported framework line should be resolved and tested deliberately. Runnable consuming applications should track their own dependency lockfiles. No CI or publication workflow is configured by this initial package scaffold.

Initial verification exercised Laravel 12.73.0 / Testbench 10.12.0 and Laravel 13.43.0 / Testbench 11.3.0 on both PHP 8.4.27 and 8.5.12. All four combinations passed 38 tests with 84 assertions. Composer validation and Pint also passed. These are synthetic integration checks; a live Cloudflare ingress was not exercised.

The canonical prepared source version is in `VERSION`. Integration and publication follow the repository's owning [GitHub Project](https://github.com/orgs/moztopia/projects/67).

## License

[Apache License 2.0](LICENSE).
