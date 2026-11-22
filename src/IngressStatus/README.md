# IngressStatus

The backed enum reports local immediate-peer acceptance or its absence: trusted, untrusted, no request, wrong request type, missing/invalid peer, invalid configuration, or an empty trust list. `Trusted` uses the same configured IP/CIDR predicate as normal visitor/context reads; it is not cryptographic Cloudflare identity verification. It contains no peer address. Configuration issues remain available independently on the redacted diagnostics snapshot.
