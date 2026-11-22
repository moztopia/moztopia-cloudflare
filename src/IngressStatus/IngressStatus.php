<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\IngressStatus;

/**
 * Explain whether the active immediate peer satisfies local ingress policy.
 */

enum IngressStatus: string
{
    case Trusted = 'trusted';
    case Untrusted = 'untrusted';
    case NoRequest = 'no_request';
    case InvalidRequest = 'invalid_request';
    case MissingPeer = 'missing_peer';
    case InvalidPeer = 'invalid_peer';
    case InvalidConfiguration = 'invalid_configuration';
    case NoTrustedProxies = 'no_trusted_proxies';
}
