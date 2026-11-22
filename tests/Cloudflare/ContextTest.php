<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;
use Moztopia\Cloudflare\Facades\Cloudflare;

beforeEach(function (): void {
    config()->set('cloudflare.trusted_proxies', ['192.0.2.0/24', '2001:db8::/32']);
    $this->app->instance('request', Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.10']));
    Http::preventStrayRequests();
});

it('reads location and request context without sending HTTP requests', function (): void {
    request()->headers->add([
        'CF-IPCountry' => 'TH', 'CF-IPContinent' => 'AS', 'CF-Region' => 'Bangkok',
        'CF-Region-Code' => '10', 'CF-IPCity' => 'กรุงเทพมหานคร', 'CF-Timezone' => 'Asia/Bangkok',
        'CF-Connecting-IP' => '203.0.113.10', 'CF-Ray' => '0123456789abcdef-BKK',
        'CF-Visitor' => '{"scheme":"https"}',
    ]);

    $visitor = Cloudflare::visitor();
    $context = Cloudflare::requestContext();

    expect($visitor->countryCode)->toBe('TH')
        ->and($visitor->continentCode)->toBe('AS')
        ->and($visitor->region)->toBe('Bangkok')
        ->and($visitor->regionCode)->toBe('10')
        ->and($visitor->city)->toBe('กรุงเทพมหานคร')
        ->and($visitor->timezone)->toBe('Asia/Bangkok')
        ->and($visitor->countryMarker)->toBeNull()
        ->and($context->trusted)->toBeTrue()
        ->and($context->clientIp)->toBe('203.0.113.10')
        ->and($context->rayId)->toBe('0123456789abcdef-BKK')
        ->and($context->scheme)->toBe('https');

    Http::assertNothingSent();
});

it('returns unknown values when headers are absent', function (): void {
    expect(array_filter((array) Cloudflare::visitor()))->toBeEmpty()
        ->and(Cloudflare::requestContext()->clientIp)->toBeNull()
        ->and(Cloudflare::requestContext()->scheme)->toBeNull();
});

it('does not trust header presence or Laravel forwarded-header configuration alone', function (): void {
    config()->set('cloudflare.trusted_proxies', []);
    request()->headers->add(['CF-IPCountry' => 'TH', 'CF-Ray' => '0123456789abcdef-BKK']);
    Request::setTrustedProxies(['192.0.2.0/24'], Request::HEADER_X_FORWARDED_FOR);

    try {
        expect(Cloudflare::visitor()->countryCode)->toBeNull()
            ->and(Cloudflare::requestContext()->trusted)->toBeFalse()
            ->and(Cloudflare::requestContext()->rayId)->toBeNull();
    } finally {
        Request::setTrustedProxies([], 0);
    }
});

it('rejects untrusted peers and wildcard trust', function (mixed $proxies, mixed $peer): void {
    config()->set('cloudflare.trusted_proxies', $proxies);
    request()->server->set('REMOTE_ADDR', $peer);
    request()->headers->set('CF-IPCountry', 'TH');

    expect(Cloudflare::requestContext()->trusted)->toBeFalse()
        ->and(Cloudflare::visitor()->countryCode)->toBeNull();
})->with([
    'untrusted IPv4' => [['192.0.2.0/24'], '198.51.100.2'],
    'wildcard' => [['*'], '192.0.2.10'],
    'invalid list' => ['*', '192.0.2.10'],
    'invalid peer' => [['192.0.2.0/24'], 'invalid'],
    'missing peer' => [['192.0.2.0/24'], null],
    'invalid CIDR prefix' => [['192.0.2.0/no'], '192.0.2.10'],
    'out of range CIDR' => [['192.0.2.0/99'], '192.0.2.10'],
    'duplicate CIDR suffix' => [['192.0.2.0/24/1'], '192.0.2.10'],
]);

it('supports IPv6 ingress and visitor addresses', function (): void {
    request()->server->set('REMOTE_ADDR', '2001:db8::1');
    request()->headers->set('CF-Connecting-IP', '2001:db8:1::2');

    expect(Cloudflare::requestContext()->trusted)->toBeTrue()
        ->and(Cloudflare::requestContext()->clientIp)->toBe('2001:db8:1::2');
});

it('uses explicit namespaced trust settings before legacy configuration', function (mixed $settings, bool $trusted): void {
    config()->set('moztopia-cloudflare', $settings);
    request()->headers->set('CF-IPCountry', 'TH');

    expect(Cloudflare::requestContext()->trusted)->toBe($trusted)
        ->and(Cloudflare::visitor()->countryCode)->toBe($trusted ? 'TH' : null);
})->with([
    'explicit trusted peer' => [['trusted_proxies' => ['192.0.2.10']], true],
    'explicit different peer' => [['trusted_proxies' => ['198.51.100.10']], false],
    'explicit empty list disables legacy trust' => [['trusted_proxies' => []], false],
    'null preserves legacy trust' => [['trusted_proxies' => null], true],
    'missing key preserves legacy trust' => [[], true],
    'invalid explicit list fails closed' => [['trusted_proxies' => '*'], false],
    'invalid explicit namespace fails closed' => ['*', false],
    'explicit wildcard does not enable legacy trust' => [['trusted_proxies' => ['*']], false],
]);

it('does not fall back from invalid namespaced configuration to a trusted legacy peer', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', false);
    request()->headers->set('CF-IPCountry', 'TH');

    expect(Cloudflare::requestContext()->trusted)->toBeFalse()
        ->and(Cloudflare::visitor()->countryCode)->toBeNull();
});

it('reads namespaced settings again after the active request container changes', function (): void {
    $worker = $this->app;
    $service = app(CloudflareService::class);
    request()->headers->set('CF-IPCountry', 'TH');
    expect($service->visitor()->countryCode)->toBe('TH');

    $sandbox = new Illuminate\Container\Container();
    $request = Request::create('/sandbox', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $request->headers->set('CF-IPCountry', 'US');
    $sandbox->instance('request', $request);
    $sandbox->instance('config', new Illuminate\Config\Repository([
        'moztopia-cloudflare' => ['trusted_proxies' => []],
        'cloudflare' => ['trusted_proxies' => ['192.0.2.10']],
    ]));

    try {
        Illuminate\Container\Container::setInstance($sandbox);
        expect($service->visitor()->countryCode)->toBeNull();

        $sandbox->make('config')->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10']);
        expect($service->visitor()->countryCode)->toBe('US');
    } finally {
        Illuminate\Container\Container::setInstance($worker);
    }
});

it('supports the namespaced Blade alias', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10']);
    request()->headers->set('CF-IPCountry', 'TH');

    expect(Blade::render('{{ MoztopiaCloudflare::visitor()->countryCode }}'))->toBe('TH');
});

it('preserves Cloudflare country markers without inventing a country', function (string $marker): void {
    request()->headers->set('CF-IPCountry', $marker);

    expect(Cloudflare::visitor()->countryCode)->toBeNull()
        ->and(Cloudflare::visitor()->countryMarker)->toBe($marker);
})->with(['XX', 'T1']);

it('rejects malformed location values', function (string $header, mixed $value, string $property): void {
    request()->headers->set($header, $value);

    expect(Cloudflare::visitor()->{$property})->toBeNull();
})->with([
    ['CF-IPCountry', 'ZZ', 'countryCode'],
    ['CF-IPCountry', 'Thailand', 'countryCode'],
    ['CF-IPCountry', ['TH', 'US'], 'countryCode'],
    ['CF-IPContinent', 'XX', 'continentCode'],
    ['CF-Region-Code', 'invalid code', 'regionCode'],
    ['CF-Region', "Bangkok\nInjected", 'region'],
    ['CF-IPCity', str_repeat('x', 257), 'city'],
    ['CF-IPCity', "\xFF", 'city'],
    ['CF-IPCity', '  ', 'city'],
    ['CF-Timezone', 'invented/timezone', 'timezone'],
]);

it('rejects malformed request metadata', function (string $header, mixed $value, string $property): void {
    request()->headers->set($header, $value);

    expect(Cloudflare::requestContext()->{$property})->toBeNull();
})->with([
    ['CF-Connecting-IP', '203.0.113.1, 203.0.113.2', 'clientIp'],
    ['CF-Connecting-IP', 'not-an-ip', 'clientIp'],
    ['CF-Ray', 'arbitrary text', 'rayId'],
    ['CF-Visitor', 'not-json', 'scheme'],
    ['CF-Visitor', '{"scheme":"javascript"}', 'scheme'],
    ['CF-Visitor', '{"scheme":["https"]}', 'scheme'],
]);

it('does not retain a visitor when Laravel rebinds the request', function (): void {
    $service = app(CloudflareService::class);
    request()->headers->set('CF-IPCountry', 'TH');
    $first = Cloudflare::visitor();

    $next = Request::create('/next', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $next->headers->set('CF-IPCountry', 'US');
    $this->app->instance('request', $next);

    expect($first->countryCode)->toBe('TH')
        ->and($service->visitor()->countryCode)->toBe('US')
        ->and(Cloudflare::visitor()->countryCode)->toBe('US');

    $this->app->instance('request', Request::create('/unknown', server: ['REMOTE_ADDR' => '198.51.100.1']));

    expect($service->visitor()->countryCode)->toBeNull()
        ->and(Cloudflare::requestContext()->trusted)->toBeFalse();
});

it('follows a new request sandbox after the singleton was warmed in the worker container', function (): void {
    $worker = $this->app;
    $service = $worker->make(CloudflareService::class);
    request()->headers->set('CF-IPCountry', 'TH');
    expect($service->visitor()->countryCode)->toBe('TH');

    $sandbox = new Illuminate\Container\Container();
    $request = Request::create('/sandbox', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $request->headers->set('CF-IPCountry', 'US');
    $sandbox->instance('request', $request);
    $sandbox->instance('config', new Illuminate\Config\Repository(['cloudflare' => ['trusted_proxies' => ['192.0.2.10']]]));

    try {
        Illuminate\Container\Container::setInstance($sandbox);

        expect($service->visitor()->countryCode)->toBe('US')
            ->and($service->requestContext()->trusted)->toBeTrue();

        $sandbox->make('config')->set('cloudflare.trusted_proxies', []);

        expect($service->visitor()->countryCode)->toBeNull()
            ->and($service->requestContext()->trusted)->toBeFalse();
    } finally {
        Illuminate\Container\Container::setInstance($worker);
    }
});

it('supports Blade conditionals and escapes location text', function (): void {
    request()->headers->add(['CF-IPCountry' => 'TH', 'CF-Region' => '<script>alert(1)</script>']);

    $html = Blade::render(<<<'BLADE'
        @if (Cloudflare::visitor()->countryCode === 'TH')
            Thai delivery
        @endif
        {{ Cloudflare::visitor()->region }}
        BLADE);

    expect($html)->toContain('Thai delivery', '&lt;script&gt;')
        ->not->toContain('<script>');
});

it('leaves application locale and timezone choices unchanged', function (): void {
    request()->headers->add(['CF-IPCountry' => 'TH', 'CF-Timezone' => 'Asia/Bangkok']);
    $locale = app()->getLocale();
    $timezone = config('app.timezone');

    Cloudflare::visitor();

    expect(app()->getLocale())->toBe($locale)
        ->and(config('app.timezone'))->toBe($timezone);
});

it('injects context into a routed application and isolates consecutive HTTP requests', function (): void {
    $this->app->make('router')->get('/location', fn (CloudflareService $cloudflare): array => [
        'country' => $cloudflare->visitor()->countryCode,
        'trusted' => $cloudflare->requestContext()->trusted,
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeaders(['CF-IPCountry' => 'TH'])
        ->get('/location')
        ->assertOk()
        ->assertExactJson(['country' => 'TH', 'trusted' => true]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->get('/location')
        ->assertOk()
        ->assertExactJson(['country' => null, 'trusted' => false]);
});
