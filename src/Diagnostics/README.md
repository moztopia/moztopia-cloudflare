# Diagnostics

The immutable report returned by `Cloudflare::diagnostics()` contains an `IngressStatus`, a matching `trusted` boolean, supported-header `HeaderStatus` values and configuration issue codes. `header('CF-Region')` is case-insensitive and rejects unsupported/unassessed names. Header/issue arrays are independently copied and readonly; the service-generated report never includes raw header values, addresses, proxy entries or credentials.

An untrusted ingress makes every header `Untrusted` without parsing it. Accepted ingress distinguishes missing, rejected and parsed values through the same parser as normal reads. Missing data does not prove a disabled product. Reports remain snapshots when the active request/configuration changes. Manually constructed reports are application-supplied data. The wiki owns the human guide and exact supported contract; this API is unreleased core work.
