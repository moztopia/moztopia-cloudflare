<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\HeaderStatus;

/**
 * Describe a header assessment without exposing its supplied value.
 */

enum HeaderStatus: string
{
    case Untrusted = 'untrusted';
    case Missing = 'missing';
    case Rejected = 'rejected';
    case Parsed = 'parsed';
}
