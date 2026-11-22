# Changelog

## Unreleased — Cirrus

- Add redacted typed request diagnostics and a namespaced local doctor command with JSON output, isolated synthetic checks and native English/Thai/Arabic copy. No public route, raw visitor logging or network probe is installed; configuration guidance does not infer Cloudflare product enablement.
- Append nullable latitude/longitude floats and string metro codes to trusted visitor snapshots, preserving existing constructor positions. Validate bounded decimal coordinates and numeric identifiers locally; demonstrate coordinates and unknown fallbacks in the localized example without postal-code or geocoding dependencies.
- Support Laravel-native facade mocking with typed dependency injection while retaining immutable visitor/request snapshots. Add explicit immutable request fixtures for known, absent, malformed and untrusted consumer tests without changing live trust or request state.
- Add the `MoztopiaCloudflare` facade alias, `moztopia-cloudflare` configuration namespace/file and `moztopia-cloudflare-config` publish tag for explicit coexistence with API-management packages.
- Preserve existing fully qualified PHP access and legacy configuration/publishing. Offer the standalone legacy `Cloudflare` alias only when no existing alias or class claims it, leaving another package's facade untouched. Explicit namespaced trust settings take precedence, including an empty list; `null` retains legacy fallback and invalid explicit values fail closed. Use the canonical publishing tag to avoid common-tag collisions.
- Verify provider/manifest order, configuration caching, canonical publishing, Blade and active-container behavior with synthetic integration tests.
- Check dependent issue PRs and combined phase branches in the owner-approved grouped integration workflow.
- Add affected-path GitHub Actions checks for the supported PHP/Laravel matrix and consuming example. Resolve Laravel/Testbench lines independently and verify the exact PHP runtime used for tests.
- Keep Cloudflare live-ingress checks, credentials, artifact publication and deployments outside ordinary synthetic CI. Documentation-only changes skip application builds.

## 0.9.1 — Cirrus — 2026-11-21

- Make the wiki the authoritative human-facing project guide and shorten the root and example READMEs to entry points. Preserve detailed development, setup and example guidance in the wiki.
- Add the tropical Moztopia Cloudflare family logo in root `logo.png` and at the top of the wiki sidebar.
- Preserve the 0.9.0 runtime API, requirements and ingress contract; no consumer migration is required.

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
