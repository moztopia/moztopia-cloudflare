# Cloudflare

`Moztopia\Cloudflare\Cloudflare\Cloudflare` is the container-injectable service behind the facade. It resolves the active request and configuration on every call, retains no visitor snapshot, and performs no outbound requests. `parts/CloudflareHeaders.php` is the internal header validation and mapping implementation; consumers should use the service or facade. Tests live in `tests/Cloudflare/`.

The Laravel provider supplies a resolver for the current container, so a singleton warmed before an Octane request does not retain the worker's boot-time container. The constructor also accepts an explicit container for isolated harnesses; in that mode it deliberately reads only that container.

The service is mockable through Laravel's native facade `shouldReceive()` workflow. Fresh typed container resolution shares that mocked binding; an already-held object remains its existing object. Use Laravel's isolated test-application lifecycle for teardown. `Visitor` and `RequestContext` remain final/readonly. [RequestFixture](../RequestFixture/README.md) supplies immutable data for explicit HTTP parser tests without changing live state.

The canonical settings are in `moztopia-cloudflare`. A `null` trusted-proxy setting falls back to the legacy `cloudflare.trusted_proxies`; an explicit array, including an empty one, takes precedence. Invalid explicit values fail closed. Use the `MoztopiaCloudflare` Blade alias and `moztopia-cloudflare-config` publish tag for coexistence. The provider offers the legacy `Cloudflare` alias only when that name is unclaimed, preserving a coinstalled wrapper's alias. The [wiki](https://github.com/moztopia/moztopia-cloudflare/wiki/Getting-started) is the human-facing reference; new identifiers remain unreleased until integration.
