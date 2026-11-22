<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Cloudflare\parts;

use DateTimeZone;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\Visitor\Visitor;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
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
     * Name the headers implemented by the credential-free local parser.
     *
     * @var list<string>
     */

    private const HEADERS = [
        'cf-ipcountry', 'cf-ipcontinent', 'cf-region', 'cf-region-code', 'cf-ipcity', 'cf-timezone',
        'cf-iplatitude', 'cf-iplongitude', 'cf-metro-code', 'cf-connecting-ip', 'cf-ray', 'cf-visitor',
    ];

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
            latitude: $this->coordinate('cf-iplatitude', 90),
            longitude: $this->coordinate('cf-iplongitude', 180),
            metroCode: $this->pattern('cf-metro-code', '/\A[0-9]{1,16}\z/'),
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
     * Describe supported headers without returning or parsing untrusted values.
     *
     * @return array<string, HeaderStatus>
     */

    public function diagnostics(bool $trusted): array
    {
        if (!$trusted) {
            return array_fill_keys(self::HEADERS, HeaderStatus::Untrusted);
        }

        $visitor = $this->visitor();
        $context = $this->requestContext();
        $parsed = [
            'cf-ipcountry' => $visitor->countryCode !== null || $visitor->countryMarker !== null,
            'cf-ipcontinent' => $visitor->continentCode !== null,
            'cf-region' => $visitor->region !== null,
            'cf-region-code' => $visitor->regionCode !== null,
            'cf-ipcity' => $visitor->city !== null,
            'cf-timezone' => $visitor->timezone !== null,
            'cf-iplatitude' => $visitor->latitude !== null,
            'cf-iplongitude' => $visitor->longitude !== null,
            'cf-metro-code' => $visitor->metroCode !== null,
            'cf-connecting-ip' => $context->clientIp !== null,
            'cf-ray' => $context->rayId !== null,
            'cf-visitor' => $context->scheme !== null,
        ];
        $statuses = [];

        foreach (self::HEADERS as $name) {
            $statuses[$name] = !$this->headers->has($name)
                ? HeaderStatus::Missing
                : ($parsed[$name] ? HeaderStatus::Parsed : HeaderStatus::Rejected);
        }

        return $statuses;
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

    /**
     * Parse one finite decimal coordinate within its geographic bounds.
     */

    private function coordinate(string $name, float $limit): ?float
    {
        $value = $this->text($name);

        if ($value === null || preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)\z/', $value) !== 1) {
            return null;
        }

        $coordinate = (float) $value;

        return is_finite($coordinate) && abs($coordinate) <= $limit ? $coordinate : null;
    }
}
