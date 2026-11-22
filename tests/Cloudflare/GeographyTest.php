<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Moztopia\Cloudflare\RequestFixture\RequestFixture;
use Moztopia\Cloudflare\Visitor\Visitor;

beforeEach(function (): void {
    $fixture = RequestFixture::trusted();
    config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);
    $request = Request::create('/', server: $fixture->server);
    $request->headers->add($fixture->headers);
    $this->app->instance('request', $request);
    Http::preventStrayRequests();
});

it('accepts signed decimal coordinates including zero and geographic boundaries', function (string $latitude, string $longitude, float $expectedLatitude, float $expectedLongitude): void {
    request()->headers->add(['CF-IPLatitude' => $latitude, 'CF-IPLongitude' => $longitude]);

    expect(Cloudflare::visitor()->latitude)->toBe($expectedLatitude)
        ->and(Cloudflare::visitor()->longitude)->toBe($expectedLongitude);
})->with([
    'origin' => ['0', '0', 0.0, 0.0],
    'minimum' => ['-90', '-180', -90.0, -180.0],
    'maximum' => ['+90', '+180', 90.0, 180.0],
    'fractions' => ['-.5', '+.25', -0.5, 0.25],
    'trimmed' => [' 51.5074 ', ' -0.1278 ', 51.5074, -0.1278],
]);

it('keeps malformed geography unknown', function (string $header, mixed $value, string $property): void {
    request()->headers->set($header, $value);

    expect(Cloudflare::visitor()->{$property})->toBeNull();
})->with([
    ['CF-IPLatitude', '90.001', 'latitude'],
    ['CF-IPLatitude', '-90.001', 'latitude'],
    ['CF-IPLongitude', '180.001', 'longitude'],
    ['CF-IPLongitude', '-180.001', 'longitude'],
    ['CF-IPLatitude', 'NaN', 'latitude'],
    ['CF-IPLongitude', 'INF', 'longitude'],
    ['CF-IPLatitude', '-Infinity', 'latitude'],
    ['CF-IPLongitude', '1e2', 'longitude'],
    ['CF-IPLatitude', '13,75', 'latitude'],
    ['CF-IPLongitude', '12.1.2', 'longitude'],
    ['CF-IPLatitude', ['13.7', '14.8'], 'latitude'],
    ['CF-IPLongitude', "\xFF", 'longitude'],
    ['CF-IPLatitude', "13.7\nInjected", 'latitude'],
    ['CF-IPLatitude', ' ', 'latitude'],
    ['CF-IPLongitude', str_repeat('1', 257), 'longitude'],
    ['CF-IPLatitude', '١٣', 'latitude'],
    ['CF-Metro-Code', 'abc', 'metroCode'],
    ['CF-Metro-Code', '635,807', 'metroCode'],
    ['CF-Metro-Code', '-635', 'metroCode'],
    ['CF-Metro-Code', '6.35', 'metroCode'],
    ['CF-Metro-Code', ['635', '807'], 'metroCode'],
    ['CF-Metro-Code', "635\0bad", 'metroCode'],
    ['CF-Metro-Code', "\xFF", 'metroCode'],
    ['CF-Metro-Code', str_repeat('9', 17), 'metroCode'],
]);

it('preserves metro zero as an identifier without guessing its meaning', function (): void {
    request()->headers->set('CF-Metro-Code', '0');
    expect(Cloudflare::visitor()->metroCode)->toBe('0');
});

it('validates geography fields independently and retains country markers', function (): void {
    request()->headers->add(['CF-IPCountry' => 'T1', 'CF-IPLatitude' => 'invalid', 'CF-IPLongitude' => '100.5', 'CF-Metro-Code' => '635']);

    expect(Cloudflare::visitor()->latitude)->toBeNull()
        ->and(Cloudflare::visitor()->longitude)->toBe(100.5)
        ->and(Cloudflare::visitor()->metroCode)->toBe('635')
        ->and(Cloudflare::visitor()->countryMarker)->toBe('T1')
        ->and(Cloudflare::visitor()->countryCode)->toBeNull();
});

it('requires trusted immediate ingress for every supplied geographic field', function (): void {
    request()->headers->add(['CF-IPLatitude' => '13.7563', 'CF-IPLongitude' => '100.5018', 'CF-Metro-Code' => '635']);
    request()->server->set('REMOTE_ADDR', '198.51.100.10');

    expect(Cloudflare::visitor()->latitude)->toBeNull()
        ->and(Cloudflare::visitor()->longitude)->toBeNull()
        ->and(Cloudflare::visitor()->metroCode)->toBeNull();
});

it('does not retain coordinates or metro data after a request changes', function (): void {
    request()->headers->add(['CF-IPLatitude' => '13.7563', 'CF-IPLongitude' => '100.5018', 'CF-Metro-Code' => '635']);
    $service = app(CloudflareService::class);
    $first = $service->visitor();
    $next = Request::create('/next', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $this->app->instance('request', $next);

    expect($first->latitude)->toBe(13.7563)
        ->and($first->metroCode)->toBe('635')
        ->and($service->visitor()->latitude)->toBeNull()
        ->and($service->visitor()->longitude)->toBeNull()
        ->and($service->visitor()->metroCode)->toBeNull();
});

it('preserves the original snapshot constructor positions and nullable additions', function (): void {
    $visitor = new Visitor('TH', 'AS', 'Bangkok', '10', 'กรุงเทพมหานคร', 'Asia/Bangkok', 'XX');

    expect($visitor->countryCode)->toBe('TH')
        ->and($visitor->timezone)->toBe('Asia/Bangkok')
        ->and($visitor->countryMarker)->toBe('XX')
        ->and($visitor->latitude)->toBeNull()
        ->and($visitor->longitude)->toBeNull()
        ->and($visitor->metroCode)->toBeNull();
});

it('renders zero coordinates and useful unknown fallbacks in Blade', function (): void {
    $blade = '@if ($visitor->latitude !== null && $visitor->longitude !== null){{ $visitor->latitude }}, {{ $visitor->longitude }}@else Unknown coordinates @endif|{{ $visitor->metroCode ?? "Unknown metro" }}';

    expect(Blade::render($blade, ['visitor' => new Visitor(latitude: 0.0, longitude: 0.0, metroCode: '0635')]))->toBe('0, 0|0635')
        ->and(Blade::render($blade, ['visitor' => new Visitor()]))->toContain('Unknown coordinates', 'Unknown metro');
});

it('reads the supplied coordinates and retains metro identifiers as strings', function (): void {
    request()->headers->add(['CF-IPLatitude' => '13.7563', 'CF-IPLongitude' => '100.5018', 'CF-Metro-Code' => '0635']);
    $visitor = Cloudflare::visitor();

    expect($visitor->latitude)->toBe(13.7563)
        ->and($visitor->longitude)->toBe(100.5018)
        ->and($visitor->metroCode)->toBe('0635')
        ->and($visitor->countryCode)->toBe('TH');
    Http::assertNothingSent();
});
