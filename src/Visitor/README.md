# Visitor

`Moztopia\Cloudflare\Visitor\Visitor` is an immutable location snapshot returned by `Cloudflare::visitor()`. Its nullable properties are documented in the [wiki field reference](https://github.com/moztopia/moztopia-cloudflare/wiki/Visitor-and-request-context). `countryMarker` preserves Cloudflare's `XX` and `T1` values without treating them as countries. Focused consumer tests live in `tests/Cloudflare/`.
