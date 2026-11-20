# RequestContext

`Moztopia\Cloudflare\RequestContext\RequestContext` describes the current request's Cloudflare metadata. `trusted` means the immediate peer matched the application's explicit package ingress configuration; it is not an authentication decision. Missing or invalid metadata stays `null`. Focused consumer tests live in `tests/Cloudflare/`.
