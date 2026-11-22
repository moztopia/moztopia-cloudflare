<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\CurrentApplication;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Octane\RequestContext as OctaneRequestContext;
use Laravel\Octane\Worker;
use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\RequestFixture\RequestFixture;
use Moztopia\Cloudflare\Visitor\Visitor;

it('isolates expanded snapshots through consecutive kernel and real Octane worker requests', function (bool $octane): void {
    Http::preventStrayRequests();
    $application = $this->app;
    $application->make('config')->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10']);
    $application->make('config')->set('cloudflare.trusted_proxies', ['192.0.2.10']);

    if ($octane) {
        $application->register(OctaneServiceProvider::class);
    }

    $warmed = $application->make(CloudflareService::class);
    expect(Cloudflare::getFacadeRoot())->toBe($warmed);
    $bootVisitor = $warmed->visitor();
    $bootContext = $warmed->requestContext();
    $bootDiagnostics = $warmed->diagnostics();
    $originalConfiguration = $application->make('config')->all();
    $snapshots = [];
    $snapshotJson = [];

    $application->make('router')->get('/isolated-context', function (CloudflareService $service) use ($warmed, &$snapshots, &$snapshotJson): array {
        expect($service)->toBe($warmed)
            ->and(Cloudflare::getFacadeRoot())->toBe($warmed);

        if (request()->attributes->has('trustOverride')) {
            config()->set('moztopia-cloudflare.trusted_proxies', request()->attributes->get('trustOverride'));
        }

        $visitor = $service->visitor();
        $context = $service->requestContext();
        $diagnostics = $service->diagnostics();
        expect(Cloudflare::visitor())->toEqual($visitor)
            ->and(Cloudflare::requestContext())->toEqual($context)
            ->and(Cloudflare::diagnostics())->toEqual($diagnostics);
        $snapshot = [$visitor, $context, $diagnostics];
        $snapshots[] = $snapshot;
        $snapshotJson[] = json_encode($snapshot, JSON_THROW_ON_ERROR);

        return [
            'visitor' => $visitor,
            'context' => $context,
            'diagnostics' => $diagnostics,
            'html' => Blade::render('{{ MoztopiaCloudflare::visitor()->countryCode ?? "Unknown" }}'),
        ];
    });
    $application->make('router')->get('/isolated-failure', function (): never {
        config()->set('moztopia-cloudflare.trusted_proxies', []);

        throw new RuntimeException('Synthetic request failure.');
    });

    $trusted = RequestFixture::trusted([
        'CF-IPCountry' => 'TH', 'CF-IPContinent' => 'AS', 'CF-Region' => 'Bangkok',
        'CF-Region-Code' => '10', 'CF-IPCity' => 'Bangkok', 'CF-Timezone' => 'Asia/Bangkok',
        'CF-IPLatitude' => '13.7563', 'CF-IPLongitude' => '100.5018', 'CF-Metro-Code' => '001',
        'CF-Connecting-IP' => '203.0.113.10', 'CF-Ray' => '0123456789abcdef-BKK',
        'CF-Visitor' => '{"scheme":"https"}',
    ]);
    $fullVisitor = new Visitor('TH', 'AS', 'Bangkok', '10', 'Bangkok', 'Asia/Bangkok', null, 13.7563, 100.5018, '001');
    $fullContext = new RequestContext(true, '203.0.113.10', '0123456789abcdef-BKK', 'https');
    $malformed = RequestFixture::trusted([
        ...RequestFixture::malformed()->headers,
        'CF-IPLatitude' => ['0', '1'], 'CF-IPLongitude' => '181', 'CF-Metro-Code' => "1\n2",
    ]);
    $partial = RequestFixture::trusted(['CF-IPCountry' => 'US', 'CF-IPLatitude' => '0', 'CF-IPLongitude' => '0', 'CF-Metro-Code' => '0']);
    $scenarios = [
        [$trusted, [], $fullVisitor, $fullContext, IngressStatus::Trusted, []],
        [RequestFixture::missing(), [], new Visitor(), new RequestContext(true), IngressStatus::Trusted, []],
        [$malformed, [], new Visitor(), new RequestContext(true), IngressStatus::Trusted, []],
        [RequestFixture::untrusted($trusted->headers), [], new Visitor(), new RequestContext(), IngressStatus::Untrusted, []],
        [$trusted, ['trustOverride' => []], new Visitor(), new RequestContext(), IngressStatus::NoTrustedProxies, ['empty_trust']],
        [$trusted, ['trustOverride' => '*'], new Visitor(), new RequestContext(), IngressStatus::InvalidConfiguration, ['invalid_trust_list']],
        [$partial, [], new Visitor(countryCode: 'US', latitude: 0, longitude: 0, metroCode: '0'), new RequestContext(true), IngressStatus::Trusted, []],
        [$trusted, [], $fullVisitor, $fullContext, IngressStatus::Trusted, []],
    ];
    $responses = [];
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('respond')->andReturnUsing(function (OctaneRequestContext $context, OctaneResponse $response) use (&$responses): void {
        $responses[] = $response->response;
    });
    $client->shouldReceive('error')->never();
    $factory = Mockery::mock(ApplicationFactory::class);
    $factory->shouldReceive('createApplication')->times($octane ? 1 : 0)->andReturn($application);
    $worker = $octane ? new Worker($factory, $client) : null;

    if ($worker !== null) {
        $worker->boot();
    }

    try {
        for ($round = 0; $round < 3; $round++) {
            foreach ($scenarios as $index => [$fixture, $attributes, $visitor, $context, $ingress, $issues]) {
                $fixtureJson = json_encode($fixture, JSON_THROW_ON_ERROR);
                $request = Request::create('/isolated-context', server: $fixture->server);
                $request->headers->add($fixture->headers);
                $request->attributes->add($attributes);
                $beforeConfiguration = $application->make('config')->all();

                if ($worker !== null) {
                    $worker->handle($request, new OctaneRequestContext());
                    $response = array_pop($responses);
                } else {
                    $kernel = $application->make(Kernel::class);
                    $response = $kernel->handle($request);
                    $kernel->terminate($request, $response);
                }

                expect($response->getStatusCode())->toBe(200);
                $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
                $latest = $snapshots[array_key_last($snapshots)];
                expect($latest[0])->toEqual($visitor)
                    ->and($latest[1])->toEqual($context)
                    ->and(array_keys($latest[2]->headers))->toBe(array_map('strtolower', array_keys($trusted->headers)))
                    ->and($latest[2]->ingress)->toBe($ingress)
                    ->and($latest[2]->configurationIssues)->toBe($issues)
                    ->and($data['visitor'])->toBe(json_decode(json_encode($visitor, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))
                    ->and($data['context'])->toBe((array) $context)
                    ->and($data['diagnostics']['trusted'])->toBe($context->trusted)
                    ->and($data['html'])->toBe($visitor->countryCode ?? 'Unknown')
                    ->and(json_encode($fixture, JSON_THROW_ON_ERROR))->toBe($fixtureJson);

                foreach ($latest[2]->headers as $name => $status) {
                    $expected = !$context->trusted ? HeaderStatus::Untrusted
                        : (!array_key_exists($name, array_change_key_case($fixture->headers)) ? HeaderStatus::Missing
                            : ($index === 2 ? HeaderStatus::Rejected : HeaderStatus::Parsed));
                    expect($status)->toBe($expected);
                }

                foreach ($snapshots as $savedIndex => $snapshot) {
                    expect(json_encode($snapshot, JSON_THROW_ON_ERROR))->toBe($snapshotJson[$savedIndex]);
                }

                if ($worker !== null) {
                    expect($application->make('config')->all())->toBe($beforeConfiguration);
                } else {
                    $application->make('config')->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10']);
                }
            }

            if ($worker !== null) {
                $worker->handle(Request::create('/isolated-failure', server: $trusted->server), new OctaneRequestContext());
                expect(array_pop($responses)->getStatusCode())->toBe(500)
                    ->and($application->make('config')->all())->toBe($originalConfiguration);
            }
        }

        expect($bootVisitor)->toEqual(new Visitor())
            ->and($bootContext)->toEqual(new RequestContext())
            ->and($bootDiagnostics->trusted)->toBeFalse()
            ->and($application->make('config')->all())->toBe($originalConfiguration)
            ->and($responses)->toBe([]);
        Http::assertNothingSent();
    } finally {
        $worker?->terminate();
        CurrentApplication::set($application);
    }
})->with(['Laravel kernel' => false, 'Octane worker with native listeners' => true]);
