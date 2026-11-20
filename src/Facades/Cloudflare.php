<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Facades;

use Illuminate\Support\Facades\Facade;
use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\Visitor\Visitor;

/**
 * Access Cloudflare context from the current Laravel request.
 *
 * @method static Visitor visitor()
 * @method static RequestContext requestContext()
 *
 * @see CloudflareService
 */

final class Cloudflare extends Facade
{
    /**
     * Resolve the stateless Cloudflare service from Laravel's container.
     */

    protected static function getFacadeAccessor(): string
    {
        return CloudflareService::class;
    }
}
