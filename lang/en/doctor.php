<?php

declare(strict_types=1);

return [
    'description' => 'Check local Cloudflare configuration and isolated synthetic samples.',
    'json_help' => 'Return redacted, locale-independent JSON status data.',
    'title' => 'Moztopia Cloudflare local diagnostics',
    'no_live_request' => 'Configuration and synthetic samples only. No live browser request has been observed.',
    'source' => 'Effective configuration namespace: :namespace',
    'entries' => 'Configured ingress entries: :count',
    'sample' => 'Synthetic sample :sample: country header :status',
    'guidance' => 'Configure controlled immediate ingress peers in moztopia-cloudflare.trusted_proxies and rebuild the configuration cache when applicable. Verify that ingress overwrites or removes client-supplied supported headers; an address match alone does not establish their provenance.',
    'no_product_inference' => 'Missing headers may reflect settings, topology or unavailable data; they do not prove a Cloudflare product is disabled. No network probes were made.',
    'sample_failed' => 'An isolated synthetic check failed.',
    'issues' => [
        'empty_trust' => 'No ingress peer is trusted. Normal context reads stay unknown until ingress is configured.',
        'invalid_trust_list' => 'The effective trusted-proxy setting must be an array; the supplied value is redacted.',
        'invalid_trust_entry' => 'An ingress entry is not a valid IP/CIDR. Review the configured list; values are redacted.',
        'unrestricted_trust' => 'A zero-prefix CIDR accepts unrestricted peers. Replace it with controlled ingress addresses or ranges.',
    ],
    'samples' => ['trusted' => 'trusted', 'missing' => 'missing', 'malformed' => 'malformed', 'untrusted' => 'untrusted'],
    'statuses' => ['parsed' => 'parsed', 'missing' => 'missing', 'rejected' => 'rejected', 'untrusted' => 'not assessed: untrusted ingress'],
];
