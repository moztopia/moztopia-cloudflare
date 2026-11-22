<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Visitor;

/**
 * Hold an immutable snapshot of available visitor location information.
 */

final readonly class Visitor
{
    /**
     * Create location context without substituting guesses for unknown values.
     *
     * @param 'XX'|'T1'|null $countryMarker Cloudflare's unknown-country or Tor marker.
     */

    public function __construct(
        public ?string $countryCode = null,
        public ?string $continentCode = null,
        public ?string $region = null,
        public ?string $regionCode = null,
        public ?string $city = null,
        public ?string $timezone = null,
        public ?string $countryMarker = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $metroCode = null,
    ) {
    }
}
