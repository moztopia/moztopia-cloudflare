<?php

declare(strict_types=1);

use Moztopia\Cloudflare\RequestFixture\RequestFixture;

it('detaches referenced header values and nested lists from the caller', function (): void {
    $country = 'TH';
    $duplicate = 'US';
    $fixture = RequestFixture::trusted([
        'CF-IPCountry' => &$country,
        'CF-Region' => [&$duplicate, 'Bangkok'],
    ]);
    $country = 'JP';
    $duplicate = 'Changed';

    expect($fixture->headers['CF-IPCountry'])->toBe('TH')
        ->and($fixture->headers['CF-Region'])->toBe(['US', 'Bangkok']);
});

it('keeps all fixture data readonly', function (): void {
    $fixture = RequestFixture::trusted(['CF-IPCountry' => ['TH', 'US']]);

    expect(fn () => $fixture->headers['CF-IPCountry'][0] = 'JP')->toThrow(Error::class)
        ->and(fn () => $fixture->server['REMOTE_ADDR'] = '127.0.0.1')->toThrow(Error::class)
        ->and(fn () => $fixture->trustedProxies[] = '*')->toThrow(Error::class);
});

it('rejects header shapes that would retain mutable state or violate the HTTP test contract', function (array $headers): void {
    expect(fn () => RequestFixture::trusted($headers))->toThrow(InvalidArgumentException::class);
})->with([
    'numeric name' => [[0 => 'TH']],
    'object value' => [['CF-IPCountry' => new stdClass()]],
    'numeric value' => [['CF-IPCountry' => 1]],
    'associative list' => [['CF-IPCountry' => ['country' => 'TH']]],
    'nested object' => [['CF-IPCountry' => [new stdClass()]]],
]);
