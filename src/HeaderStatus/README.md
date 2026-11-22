# HeaderStatus

The backed enum describes a supported header's local assessment. `Untrusted` means no assessment was made because ingress was not accepted; it does not imply the header exists. For accepted ingress, `Missing` means absent, `Rejected` means supplied but invalid/ambiguous, and `Parsed` means accepted by the existing parser. A parsed country marker remains a marker. These statuses never establish Cloudflare product availability or contain header values.
