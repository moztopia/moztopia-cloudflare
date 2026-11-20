# Cloudflare

`Moztopia\Cloudflare\Cloudflare\Cloudflare` is the container-injectable service behind the facade. It resolves the active request and configuration on every call, retains no visitor snapshot, and performs no outbound requests. `parts/CloudflareHeaders.php` is the internal header validation and mapping implementation; consumers should use the service or facade. Tests live in `tests/Cloudflare/`.
