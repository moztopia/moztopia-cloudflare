<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Cloudflare;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Moztopia\Cloudflare\Cloudflare\parts\CloudflareHeaders;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\Visitor\Visitor;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Expose credential-free Cloudflare information from the active request.
 */

final class Cloudflare
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
     * Accept headers only from explicitly configured immediate ingress peers.
     */

    private function headers(): ?CloudflareHeaders
    {
        $container = $this->container instanceof Closure ? ($this->container)() : $this->container;
        $request = $container->make('request');
        $configuration = $container->make('config');
        $settings = $configuration->get('moztopia-cloudflare', []);
        $proxies = is_array($settings)
            ? ($settings['trusted_proxies'] ?? $configuration->get('cloudflare.trusted_proxies', []))
            : null;

        if (!$request instanceof Request || !is_array($proxies)) {
            return null;
        }

        $peer = $request->server->get('REMOTE_ADDR');

        if (!is_string($peer) || filter_var($peer, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        foreach ($proxies as $proxy) {
            if (is_string($proxy) && $this->matchesProxy($peer, $proxy)) {
                return new CloudflareHeaders($request->headers);
            }
        }

        return null;
    }

    /**
     * Reject invalid ingress configuration before checking an IP or CIDR range.
     */

    private function matchesProxy(string $peer, string $proxy): bool
    {
        $parts = explode('/', $proxy);

        if (count($parts) > 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (isset($parts[1]) && preg_match('/\A[0-9]{1,3}\z/', $parts[1]) !== 1) {
            return false;
        }

        return IpUtils::checkIp($peer, $proxy);
    }
}
