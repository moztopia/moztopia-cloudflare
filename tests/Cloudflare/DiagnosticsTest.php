<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;
use Moztopia\Cloudflare\RequestFixture\RequestFixture;

beforeEach(function (): void {
    $fixture = RequestFixture::trusted();
    config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);
    $request = Request::create('/', server: $fixture->server);
    $request->headers->add($fixture->headers);
    $this->app->instance('request', $request);
    Http::preventStrayRequests();
});

it('distinguishes parsed missing rejected and untrusted metadata without exposing values', function (): void {
    request()->headers->add([
        'CF-Connecting-IP' => '203.0.113.73',
        'CF-Ray' => '0123456789abcdef-BKK',
        'CF-Region' => 'Private region',
        'CF-IPLatitude' => '0',
        'CF-IPLongitude' => '181',
    ]);
    $report = Cloudflare::diagnostics();

    expect($report->trusted)->toBeTrue()
        ->and($report->ingress)->toBe(IngressStatus::Trusted)
        ->and($report->header('CF-Region'))->toBe(HeaderStatus::Parsed)
        ->and($report->header('cf-iplatitude'))->toBe(HeaderStatus::Parsed)
        ->and($report->header('CF-IPLONGITUDE'))->toBe(HeaderStatus::Rejected)
        ->and($report->header('CF-IPCity'))->toBe(HeaderStatus::Missing)
        ->and($report->configurationIssues)->toBe([]);

    $json = json_encode($report, JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('203.0.113.73', '0123456789abcdef', 'Private region', '192.0.2.10');
    request()->server->set('REMOTE_ADDR', '198.51.100.10');

    expect(Cloudflare::diagnostics()->header('CF-Region'))->toBe(HeaderStatus::Untrusted)
        ->and(Cloudflare::diagnostics()->header('CF-IPCity'))->toBe(HeaderStatus::Untrusted)
        ->and(Cloudflare::diagnostics()->trusted)->toBe(Cloudflare::requestContext()->trusted);
    Http::assertNothingSent();
});

it('reports accepted country markers without inventing a country', function (string $marker): void {
    request()->headers->set('CF-IPCountry', $marker);

    expect(Cloudflare::diagnostics()->header('CF-IPCountry'))->toBe(HeaderStatus::Parsed)
        ->and(Cloudflare::visitor()->countryCode)->toBeNull()
        ->and(Cloudflare::visitor()->countryMarker)->toBe($marker);
})->with(['XX', 'T1']);

it('reports supplied empty duplicate and unsafe values as rejected', function (mixed $value): void {
    request()->headers->set('CF-Region', $value);
    expect(Cloudflare::diagnostics()->header('CF-Region'))->toBe(HeaderStatus::Rejected);
})->with(['empty text' => '', 'empty values' => [[]], 'duplicates' => [['Bangkok', 'London']], 'invalid UTF-8' => "\xFF", 'control character' => "private\nvalue"]);

it('explains unsupported request and ingress states without trusting header presence', function (mixed $proxies, mixed $peer, IngressStatus $expected): void {
    config()->set('moztopia-cloudflare.trusted_proxies', $proxies);
    request()->server->set('REMOTE_ADDR', $peer);

    expect(Cloudflare::diagnostics()->ingress)->toBe($expected)
        ->and(Cloudflare::diagnostics()->trusted)->toBeFalse()
        ->and(Cloudflare::diagnostics()->header('CF-IPCountry'))->toBe(HeaderStatus::Untrusted)
        ->and(Cloudflare::requestContext()->trusted)->toBeFalse();
})->with([
    'empty trust' => [[], '192.0.2.10', IngressStatus::NoTrustedProxies],
    'invalid list' => ['*', '192.0.2.10', IngressStatus::InvalidConfiguration],
    'invalid entries' => [['*'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'invalid CIDR' => [['192.0.2.0/99'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'missing peer' => [['192.0.2.10'], null, IngressStatus::MissingPeer],
    'invalid peer' => [['192.0.2.10'], 'not-an-ip', IngressStatus::InvalidPeer],
    'different peer' => [['192.0.2.10'], '198.51.100.10', IngressStatus::Untrusted],
]);

it('reports configuration issues while retaining the normal acceptance of valid entries', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10', 'invalid-sensitive-value']);

    expect(Cloudflare::diagnostics()->trusted)->toBeTrue()
        ->and(Cloudflare::requestContext()->trusted)->toBeTrue()
        ->and(Cloudflare::diagnostics()->configurationIssues)->toBe(['invalid_trust_entry'])
        ->and(json_encode(Cloudflare::diagnostics(), JSON_THROW_ON_ERROR))->not->toContain('invalid-sensitive-value');
});

it('keeps diagnostics local to each new container and retains old snapshots', function (): void {
    $worker = $this->app;
    $service = app(CloudflareService::class);
    $first = $service->diagnostics();
    $sandbox = new Container();
    $sandbox->instance('config', new Repository(['moztopia-cloudflare' => ['trusted_proxies' => ['192.0.2.10']]]));

    try {
        Container::setInstance($sandbox);
        expect($service->diagnostics()->ingress)->toBe(IngressStatus::NoRequest)
            ->and($service->visitor()->countryCode)->toBeNull();

        $sandbox->instance('request', new stdClass());
        expect($service->diagnostics()->ingress)->toBe(IngressStatus::InvalidRequest);

        $request = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $request->headers->set('CF-IPCountry', 'ZZ');
        $sandbox->instance('request', $request);
        expect($service->diagnostics()->header('CF-IPCountry'))->toBe(HeaderStatus::Rejected)
            ->and($first->header('CF-IPCountry'))->toBe(HeaderStatus::Parsed);
    } finally {
        Container::setInstance($worker);
    }
});
