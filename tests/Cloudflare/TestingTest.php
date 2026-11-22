<?php

declare(strict_types=1);

use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\RequestFixture\RequestFixture;
use Moztopia\Cloudflare\Visitor\Visitor;

it('shares Laravel facade mocks with typed dependency injection', function (): void {
    $visitor = new Visitor(countryCode: 'TH');
    Cloudflare::shouldReceive('visitor')->once()->andReturn($visitor);
    $this->app->make('router')->get('/mocked-location', fn (CloudflareService $context): array => [
        'country' => $context->visitor()->countryCode,
    ]);

    expect(Cloudflare::getFacadeRoot())->toBe(app(CloudflareService::class));
    $this->get('/mocked-location')->assertOk()->assertExactJson(['country' => 'TH']);
});

it('renders known and fallback UI through the real parser with explicit fixture trust', function (string $scenario, ?string $country, bool $trusted): void {
    Http::preventStrayRequests();
    $request = request();
    $configuration = config()->all();
    $fixture = RequestFixture::{$scenario}();

    expect(request())->toBe($request)
        ->and(config()->all())->toBe($configuration);

    config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);
    $this->app->make('router')->get('/fixture-location', fn (CloudflareService $context): array => [
        'country' => $context->visitor()->countryCode,
        'trusted' => $context->requestContext()->trusted,
        'html' => Blade::render('{{ $visitor->countryCode ?? "Unknown" }}', ['visitor' => $context->visitor()]),
    ]);

    $this->withServerVariables($fixture->server)->withHeaders($fixture->headers)
        ->get('/fixture-location')->assertOk()->assertExactJson([
            'country' => $country,
            'trusted' => $trusted,
            'html' => $country ?? 'Unknown',
        ]);
    Http::assertNothingSent();
})->with([
    'known' => ['trusted', 'TH', true],
    'absent' => ['missing', null, true],
    'malformed' => ['malformed', null, true],
    'untrusted' => ['untrusted', null, false],
]);

it('does not let fixture headers bypass an explicitly empty trust list', function (): void {
    $fixture = RequestFixture::trusted();
    config()->set('moztopia-cloudflare.trusted_proxies', []);
    $request = Request::create('/', server: $fixture->server);
    $request->headers->add($fixture->headers);
    $this->app->instance('request', $request);

    expect(Cloudflare::requestContext()->trusted)->toBeFalse()
        ->and(Cloudflare::visitor()->countryCode)->toBeNull();
});

it('restores the real facade and typed binding in a fresh Laravel test application', function (): void {
    $snapshot = new Visitor(countryCode: 'TH');
    Cloudflare::shouldReceive('visitor')->once()->andReturn($snapshot);
    $mock = app(CloudflareService::class);
    expect($mock->visitor())->toBe($snapshot);

    $this->refreshApplication();
    $fixture = RequestFixture::trusted(['CF-IPCountry' => 'US']);
    config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);
    $request = Request::create('/', server: $fixture->server);
    $request->headers->add($fixture->headers);
    $this->app->instance('request', $request);

    expect(app(CloudflareService::class))->not->toBe($mock)
        ->and(Cloudflare::getFacadeRoot())->toBe(app(CloudflareService::class))
        ->and(Cloudflare::visitor()->countryCode)->toBe('US')
        ->and($snapshot->countryCode)->toBe('TH')
        ->and((new ReflectionClass(Visitor::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(Visitor::class))->isReadOnly())->toBeTrue()
        ->and((new ReflectionClass(RequestContext::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(RequestContext::class))->isReadOnly())->toBeTrue();
});
