<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Cloudflare\parts;

use DateTimeZone;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\Visitor\Visitor;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Intl\Countries;

/**
 * Validate and map headers after the immediate ingress peer has been trusted.
 *
 * @internal
 */

final class CloudflareHeaders
{
    /**
     * Keep the headers for this individual context read.
     */

    public function __construct(private readonly HeaderBag $headers)
    {
    }

    /**
     * Map available geographic fields without inferring application policy.
     */

    public function visitor(): Visitor
    {
        $country = $this->text('cf-ipcountry');
        $continent = $this->text('cf-ipcontinent');
        $timezone = $this->text('cf-timezone');

        return new Visitor(
            countryCode: $country !== null && !in_array($country, ['XX', 'T1'], true) && Countries::exists($country) ? $country : null,
            continentCode: in_array($continent, ['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'], true) ? $continent : null,
            region: $this->text('cf-region'),
            regionCode: $this->pattern('cf-region-code', '/\A[A-Z0-9-]{1,16}\z/'),
            city: $this->text('cf-ipcity'),
            timezone: in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true) ? $timezone : null,
            countryMarker: in_array($country, ['XX', 'T1'], true) ? $country : null,
        );
    }

    /**
     * Map connection identifiers and Cloudflare's visitor-facing scheme.
     */

    public function requestContext(): RequestContext
    {
        $ip = $this->text('cf-connecting-ip');
        $visitor = json_decode($this->text('cf-visitor') ?? '', true);
        $scheme = is_array($visitor) ? ($visitor['scheme'] ?? null) : null;

        return new RequestContext(
            trusted: true,
            clientIp: $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null,
            rayId: $this->pattern('cf-ray', '/\A[a-fA-F0-9]{16,32}-[A-Z]{3}\z/'),
            scheme: in_array($scheme, ['http', 'https'], true) ? $scheme : null,
        );
    }

    /**
     * Read one bounded UTF-8 value while rejecting ambiguous or unsafe input.
     */

    private function text(string $name): ?string
    {
        $values = $this->headers->all($name);

        if (count($values) !== 1 || !is_string($values[0])) {
            return null;
        }

        $value = $values[0];

        if (strlen($value) > 256 || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Require a complete match for a structured header value.
     */

    private function pattern(string $name, string $pattern): ?string
    {
        $value = $this->text($name);

        return $value !== null && preg_match($pattern, $value) === 1 ? $value : null;
    }
}
