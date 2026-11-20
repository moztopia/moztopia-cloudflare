# Changelog

## 0.9.0 — Cirrus — 2026-11-20

- Promote the first functional package and example website to `main` as the owner-selected Cirrus release.
- Provide trusted, nullable Cloudflare visitor and request context in controllers, services and Blade without credentials or outbound requests.
- Include the self-contained, mobile-first example with isolated scenario harnesses, English/Thai/Arabic, RTL and light/dark themes. Run `docker compose up` in `examples/website` and open http://localhost:8080; its runtime pins PHP 8.5.13.
- Preserve the 0.2.0 runtime API and supported PHP/Laravel versions; no consumer migration is required. Align the example's local Composer dependency with 0.9.0.
- Keep Cloudflare ingress explicitly trusted and visitor-specific responses private. Live Cloudflare routing has not been tested; registry publication and hosted deployment are not part of this release.

## 0.2.0 — 2026-11-20

- Add a self-contained Laravel example website with scenario harnesses, responsive pages and localhost Docker startup on PHP 8.5.13.
- Exclude example applications from package source archives.

## 0.1.0 — 2026-11-20

- Add credential-free visitor location and request metadata for Laravel.
- Add an auto-discovered service provider, facade and publishable ingress configuration.
- Support controllers, dependency injection and Blade without outbound requests.
- Use Apache-2.0 licensing.
