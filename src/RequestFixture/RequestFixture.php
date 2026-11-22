<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\RequestFixture;

use InvalidArgumentException;

/**
 * Supply immutable synthetic request data without changing application state.
 */

final readonly class RequestFixture
{
    /**
     * Hold the immediate peer passed to the application's HTTP test builder.
     *
     * @var array{REMOTE_ADDR: string}
     */

    public array $server;

    /**
     * Hold independent header values, including intentional duplicate values.
     *
     * @var array<string, string|list<string>>
     */

    public array $headers;

    /**
     * Supply the explicit synthetic ingress configuration for this test.
     *
     * @var list<string>
     */

    public array $trustedProxies;

    /**
     * Copy fixture data so caller-owned references cannot alter the snapshot.
     *
     * @param array<string, string|list<string>> $headers Synthetic request headers.
     */

    private function __construct(array $headers, string $peer)
    {
        $this->server = ['REMOTE_ADDR' => $peer];
        $this->headers = self::copyHeaders($headers);
        $this->trustedProxies = ['192.0.2.10'];
    }

    /**
     * Supply a trusted peer with caller-selected headers or a known country.
     *
     * @param array<string, string|list<string>> $headers Synthetic request headers.
     */

    public static function trusted(array $headers = ['CF-IPCountry' => 'TH']): self
    {
        return new self($headers, '192.0.2.10');
    }

    /**
     * Supply a trusted peer with no Cloudflare metadata.
     */

    public static function missing(): self
    {
        return self::trusted([]);
    }

    /**
     * Supply a trusted peer with deliberately invalid visitor and request values.
     */

    public static function malformed(): self
    {
        return self::trusted([
            'CF-IPCountry' => 'ZZ',
            'CF-Timezone' => 'invalid/timezone',
            'CF-Connecting-IP' => 'not-an-ip',
            'CF-Ray' => 'not-a-ray',
            'CF-Visitor' => 'not-json',
        ]);
    }

    /**
     * Supply plausible headers from a peer outside the supplied trust list.
     *
     * @param array<string, string|list<string>> $headers Synthetic request headers.
     */

    public static function untrusted(array $headers = ['CF-IPCountry' => 'TH']): self
    {
        return new self($headers, '198.51.100.10');
    }

    /**
     * Reject mutable objects and detach references while preserving malformed text.
     *
     * @param array<string, string|list<string>> $headers Synthetic request headers.
     * @return array<string, string|list<string>>
     */

    private static function copyHeaders(array $headers): array
    {
        $copy = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Fixture header names must be strings.');
            }

            if (is_string($value)) {
                $copy[$name] = $value;

                continue;
            }

            if (!is_array($value) || !array_is_list($value)) {
                throw new InvalidArgumentException('Fixture headers must contain strings or lists of strings.');
            }

            $copy[$name] = [];

            foreach ($value as $entry) {
                if (!is_string($entry)) {
                    throw new InvalidArgumentException('Fixture header lists must contain only strings.');
                }

                $copy[$name][] = $entry;
            }
        }

        return $copy;
    }
}
