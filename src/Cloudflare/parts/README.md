# Cloudflare internals

`CloudflareTrust` resolves canonical/legacy configuration and assesses the actual immediate peer. Ordinary reads and diagnostics use the same predicate. Its configuration issue codes are redacted; detection of broad/invalid entries does not silently rewrite configuration. `CloudflareHeaders` validates bounded UTF-8 header values and maps accepted values to snapshots/statuses. These are internal implementation classes, not supported application entry points. Use the package service/facade and the wiki reference.
