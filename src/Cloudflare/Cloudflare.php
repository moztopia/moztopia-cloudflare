<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Cloudflare;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Moztopia\Cloudflare\Cloudflare\parts\CloudflareHeaders;
use Moztopia\Cloudflare\Cloudflare\parts\CloudflareTrust;
use Moztopia\Cloudflare\Diagnostics\Diagnostics;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\Visitor\Visitor;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Expose credential-free Cloudflare information from the active request.
 */

class Cloudflare
{
    /**
     * Accept an explicit container or a resolver for the active request sandbox.
     *
     * @param Container|Closure $container A container, or a closure returning the active container.
     */

    public function __construct(private readonly Container|Closure $container)
    {
    }

    /**
     * Read the current visitor's available location without a network call.
     */

    public function visitor(): Visitor
    {
        return $this->headers()?->visitor() ?? new Visitor();
    }

    /**
     * Read trusted metadata about the current Cloudflare request.
     */

    public function requestContext(): RequestContext
    {
        return $this->headers()?->requestContext() ?? new RequestContext();
    }

    /**
     * Explain current availability with redacted local status snapshots only.
     */

    public function diagnostics(): Diagnostics
    {
        $container = $this->activeContainer();
        $trust = new CloudflareTrust($container->make('config'));
        $request = $container->bound('request') ? $container->make('request') : null;
        $ingress = $trust->ingress($request);
        $headers = new CloudflareHeaders($request instanceof Request ? $request->headers : new HeaderBag());

        return new Diagnostics($ingress, $headers->diagnostics($ingress === IngressStatus::Trusted), $trust->issues());
    }

    /**
     * Accept headers only from explicitly configured immediate ingress peers.
     */

    private function headers(): ?CloudflareHeaders
    {
        $container = $this->activeContainer();
        $request = $container->bound('request') ? $container->make('request') : null;
        $trust = new CloudflareTrust($container->make('config'));

        return $trust->ingress($request) === IngressStatus::Trusted ? new CloudflareHeaders($request->headers) : null;
    }

    /**
     * Resolve the current Laravel sandbox instead of retaining a worker container.
     */

    private function activeContainer(): Container
    {
        return $this->container instanceof Closure ? ($this->container)() : $this->container;
    }
}
