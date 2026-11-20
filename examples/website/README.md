# Moztopia Cloudflare example website

A real Laravel application consuming the package in this repository through a Composer path dependency. Explore visitor location, application-level UI decisions and accepted request metadata without a Cloudflare account.

## Run

From a complete checkout of this repository:

```sh
cd examples/website
docker compose up
```

Open **http://localhost:8080**. Docker Compose builds the image on the first run. It installs the locked PHP/npm dependencies and builds the browser assets inside Docker. You need Docker and Compose; host PHP, Composer and Node are unnecessary.

The runtime is pinned to **PHP 8.5.13**. Laravel serves pages and assets directly. There is no external database, Redis, tunnel, credential, migration or environment-file prerequisite.

To choose another loopback port:

```sh
EXAMPLE_PORT=8081 docker compose up
```

Stop with Ctrl+C, or use `docker compose down` after starting with `docker compose up -d`. After changing source, rebuild with `docker compose up --build`. The image contains a snapshot of both the example and the root package; no unrelated application services are used.

## Pages and harnesses

- **Visitor context** shows country, continent, region, city, timezone and unknown-value handling.
- **UI examples** demonstrates a country-dependent delivery notice and an event formatted in the visitor timezone. These are illustrative application rules, not package-owned delivery policies.
- **Request inspector** shows trust, visitor IP, Ray ID, scheme and the selected incoming Cloudflare headers. Cookies, authorization and environment values are excluded.

The request-source selector offers Bangkok, London, Dubai, missing location headers, Tor, untrusted ingress, malformed headers and a live request. Every simulated scenario passes a synthetic request through the actual `moztopia/cloudflare` service. Its dedicated container/configuration cannot change the application's live trust policy. Sample IPs are documentation addresses.

The mode badge always distinguishes **Simulated scenario** from **Live request**. Simulation never claims to locate the person using the example. Localhost normally has no Cloudflare context; unknown values in live mode are expected.

## Live Cloudflare requests

If you put this example behind a controlled Cloudflare ingress, enable the applicable Cloudflare location headers and supply the immediate ingress addresses in `CLOUDFLARE_TRUSTED_PROXIES` as a comma-separated IPv4/IPv6/CIDR list. Its default is empty. Do not configure a broad or arbitrary proxy range just to make the live panel populate.

```sh
CLOUDFLARE_TRUSTED_PROXIES=192.0.2.10 docker compose up --force-recreate
```

Replace that documentation address with your real controlled ingress. This does not set up Cloudflare or enable settings. See the root package README for the ingress-provenance contract and header meanings. All example HTML uses `private, no-store` to prevent one visitor's location-dependent output from being cached for another.

## Interface behavior

The layout starts at phone widths and expands to a desktop sidebar. Light and dark themes are applied immediately and remembered locally when browser storage is available. Language selection preserves the current page/scenario and is remembered in a non-sensitive locale cookie.

English, Thai and Arabic are enabled, with English fallback and Arabic RTL support. The shared planned language set is recorded in `config/example.php`; planned-only languages are not offered in the picker. Location never automatically selects a language or overwrites explicit preferences. Initial Thai/Arabic translations should receive human language review before wider publication.

## Verification

The image includes its test and formatting tools:

```sh
docker compose run --rm website composer test
docker compose run --rm website composer lint
docker compose run --rm website composer validate --strict
docker compose run --rm website php --version
```

The asset stage checks TypeScript and builds with esbuild. The application tests exercise real routes, scenario isolation, live trust, private caching, escaping, country/timezone decisions, language selection, RTL markup and catalog completeness. The root package has its own separate Pest suite.

The example keeps its Composer and npm lockfiles. Its numeric Composer path version follows the root `VERSION`; this is a local development source, not a separately published package.

## Repository and distribution boundary

The complete repository is required because the example's Composer repository points to `../../`. Keep the example inside `examples/website/`; it is self-contained in its runtime, not a copyable independent package.

The owner explicitly exempts runnable example apps from the usual tunnel-only/no-published-port policy: straightforward clone-and-run access is the purpose here. Compose publishes only `127.0.0.1:8080` by default. That exception does not configure or change another application's ingress.

`examples/` is excluded from package source archives through both the root `.gitattributes` and Composer archive configuration. A Git clone retains the example for contributors. The library's autoloading and runtime dependencies remain independent of this application.

The example's own source is licensed under the repository's Apache-2.0 license. Third-party dependencies retain their own licenses.
