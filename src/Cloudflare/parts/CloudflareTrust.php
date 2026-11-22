<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Cloudflare\parts;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Resolve and assess the local immediate-peer policy shared by all context reads.
 *
 * @internal
 */

final class CloudflareTrust
{
    /**
     * Keep this individual assessment's effective configuration.
     */

    private readonly mixed $proxies;

    /**
     * Name the effective configuration source without exposing its values.
     */

    private readonly string $source;

    /**
     * Preserve canonical precedence, explicit empty trust and legacy fallback.
     */

    public function __construct(Repository $configuration)
    {
        $settings = $configuration->get('moztopia-cloudflare', []);
        $canonical = !is_array($settings) || isset($settings['trusted_proxies']);
        $this->source = $canonical ? 'moztopia-cloudflare' : 'cloudflare';
        $this->proxies = is_array($settings)
            ? ($settings['trusted_proxies'] ?? $configuration->get('cloudflare.trusted_proxies', []))
            : null;
    }

    /**
     * Explain request provenance with the existing explicit IP/CIDR policy.
     */

    public function ingress(mixed $request): IngressStatus
    {
        if ($request === null) {
            return IngressStatus::NoRequest;
        }

        if (!$request instanceof Request) {
            return IngressStatus::InvalidRequest;
        }

        if (!is_array($this->proxies)) {
            return IngressStatus::InvalidConfiguration;
        }

        if ($this->proxies === []) {
            return IngressStatus::NoTrustedProxies;
        }

        $peer = $request->server->get('REMOTE_ADDR');

        if ($peer === null || $peer === '') {
            return IngressStatus::MissingPeer;
        }

        if (!is_string($peer) || filter_var($peer, FILTER_VALIDATE_IP) === false) {
            return IngressStatus::InvalidPeer;
        }

        $valid = false;

        foreach ($this->proxies as $proxy) {
            if (self::validProxy($proxy)) {
                $valid = true;

                if (IpUtils::checkIp($peer, $proxy)) {
                    return IngressStatus::Trusted;
                }
            }
        }

        return $valid ? IngressStatus::Untrusted : IngressStatus::InvalidConfiguration;
    }

    /**
     * Describe configuration problems without returning the supplied entries.
     *
     * @return list<string>
     */

    public function issues(): array
    {
        if (!is_array($this->proxies)) {
            return ['invalid_trust_list'];
        }

        if ($this->proxies === []) {
            return ['empty_trust'];
        }

        $issues = [];

        foreach ($this->proxies as $proxy) {
            if (!self::validProxy($proxy)) {
                $issues[] = 'invalid_trust_entry';
            } elseif (str_contains($proxy, '/') && (int) explode('/', $proxy)[1] === 0) {
                $issues[] = 'unrestricted_trust';
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * Count configured entries without exposing their addresses.
     */

    public function configuredCount(): int
    {
        return is_array($this->proxies) ? count($this->proxies) : 0;
    }

    /**
     * Identify canonical configuration or the compatible legacy fallback.
     */

    public function sourceNamespace(): string
    {
        return $this->source;
    }

    /**
     * Validate an IP or a bounded CIDR prefix before passing it to IpUtils.
     */

    private static function validProxy(mixed $proxy): bool
    {
        if (!is_string($proxy)) {
            return false;
        }

        $parts = explode('/', $proxy);

        if (count($parts) > 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (!isset($parts[1])) {
            return true;
        }

        $limit = str_contains($parts[0], ':') ? 128 : 32;

        return preg_match('/\A[0-9]{1,3}\z/', $parts[1]) === 1 && (int) $parts[1] <= $limit;
    }
}
