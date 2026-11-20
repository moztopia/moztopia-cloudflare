# Cloudflare

`Moztopia\Cloudflare\Cloudflare\Cloudflare` is the container-injectable service behind the facade. It resolves the active request and configuration on every call, retains no visitor snapshot, and performs no outbound requests. `parts/CloudflareHeaders.php` is the internal header validation and mapping implementation; consumers should use the service or facade. Tests live in `tests/Cloudflare/`.

The Laravel provider supplies a resolver for the current container, so a singleton warmed before an Octane request does not retain the worker's boot-time container. The constructor also accepts an explicit container for isolated harnesses; in that mode it deliberately reads only that container.
