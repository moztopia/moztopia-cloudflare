<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\RequestContext;

/**
 * Hold an immutable snapshot of trusted Cloudflare request metadata.
 */

final readonly class RequestContext
{
    /**
     * Create request context with explicit provenance and nullable metadata.
     */

    public function __construct(
        public bool $trusted = false,
        public ?string $clientIp = null,
        public ?string $rayId = null,
        public ?string $scheme = null,
    ) {
    }
}
