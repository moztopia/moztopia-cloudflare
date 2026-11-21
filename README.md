<p align="center"><img src="logo.png" width="280" alt="Moztopia Cloudflare"></p>

# Moztopia Cloudflare

`moztopia/cloudflare` gives Laravel applications credential-free access to trusted Cloudflare visitor location and request context. Use country, region, city and timezone in controllers, services and Blade, with explicit ingress trust and predictable unknown values. Reading context makes no network requests.

**[Read the project wiki](https://github.com/moztopia/moztopia-cloudflare/wiki)** — the source of truth for human-facing project documentation, including installation, configuration, API usage, testing and the roadmap.

- [Getting started](https://github.com/moztopia/moztopia-cloudflare/wiki/Getting-started)
- [Trusted ingress](https://github.com/moztopia/moztopia-cloudflare/wiki/Trusted-ingress)
- [Visitor and request context](https://github.com/moztopia/moztopia-cloudflare/wiki/Visitor-and-request-context)
- [Blade and testing](https://github.com/moztopia/moztopia-cloudflare/wiki/Blade-and-testing)
- [Runnable example website](https://github.com/moztopia/moztopia-cloudflare/wiki/Example-website)
- [Development and verification](https://github.com/moztopia/moztopia-cloudflare/wiki/Development-and-verification)

The self-contained example lives in [`examples/website/`](examples/website/). Run `docker compose up` there and open **http://localhost:8080**.

Release name: **Cirrus**, through and including 1.0.0. The source version is recorded in [VERSION](VERSION).

[Project website](http://moztopia.com/packages/cloudflared) · [Apache License 2.0](LICENSE)
