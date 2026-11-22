<?php

declare(strict_types=1);

use App\Services\ExampleHarness;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Moztopia\Cloudflare\RequestFixture\RequestFixture;

it('renders the three real application pages with no outbound requests', function (string $path, string $heading): void {
    Http::preventStrayRequests();
    $this->get($path)->assertOk()->assertSee($heading)->assertSee('Simulated scenario')->assertSee('TH');
    Http::assertNothingSent();
})->with([['/', 'Visitor context'], ['/ui', 'UI examples'], ['/request', 'Request inspector']]);

it('renders all scenarios on every page', function (string $scenario): void {
    foreach (['/', '/ui', '/request'] as $path) {
        $this->get($path.'?scenario='.$scenario)->assertOk();
    }
})->with(['bangkok', 'london', 'dubai', 'missing', 'tor', 'untrusted', 'malformed', 'live']);

it('uses the path package without changing live context or trust configuration', function (): void {
    config()->set('cloudflare.trusted_proxies', []);
    request()->headers->set('CF-IPCountry', 'US');
    $original = request();
    $state = app(ExampleHarness::class)->run('bangkok', $original);

    expect($state['visitor']->countryCode)->toBe('TH')
        ->and($state['context']->trusted)->toBeTrue()
        ->and(request())->toBe($original)
        ->and(config('cloudflare.trusted_proxies'))->toBe([])
        ->and(Cloudflare::visitor()->countryCode)->toBeNull();
});

it('uses the actual connection trust policy in live mode', function (): void {
    config()->set('cloudflare.trusted_proxies', []);
    $this->withHeaders(['CF-IPCountry' => 'TH'])->get('/?scenario=live')
        ->assertOk()->assertSee('Live request')->assertSee('Country unavailable');

    config()->set('cloudflare.trusted_proxies', ['192.0.2.10']);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeaders(['CF-IPCountry' => 'GB'])
        ->get('/?scenario=live')->assertOk()->assertSee('United Kingdom');
});

it('uses public request fixtures for real application UI and unknown fallbacks', function (string $scenario, string $expected): void {
    Http::preventStrayRequests();
    $fixture = RequestFixture::{$scenario}();
    config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);

    $this->withServerVariables($fixture->server)->withHeaders($fixture->headers)
        ->get('/ui?scenario=live')->assertOk()->assertSee($expected);

    Http::assertNothingSent();
})->with([
    'known country' => ['trusted', 'Delivery within Thailand'],
    'missing headers' => ['missing', 'Explore our delivery destinations'],
    'malformed headers' => ['malformed', 'Explore our delivery destinations'],
    'untrusted ingress' => ['untrusted', 'Explore our delivery destinations'],
]);

it('keeps malformed and untrusted scenarios unknown', function (string $scenario): void {
    $state = app(ExampleHarness::class)->run($scenario, request());
    expect($state['visitor']->countryCode)->toBeNull()
        ->and($state['context']->clientIp)->toBeNull()
        ->and($state['visitor']->timezone)->toBeNull();
})->with(['untrusted', 'malformed', 'missing']);

it('preserves Tor markers', function (): void {
    $state = app(ExampleHarness::class)->run('tor', request());
    expect($state['visitor']->countryMarker)->toBe('T1')->and($state['visitor']->countryCode)->toBeNull();
    $this->get('/?scenario=tor')->assertOk()->assertSee('Tor marker received');
});

it('shows supplied coordinates and translated geography labels', function (string $locale, string $latitude, string $longitude, string $metro): void {
    $this->get('/?scenario=bangkok&locale='.$locale)->assertOk()
        ->assertSee($latitude)->assertSee($longitude)->assertSee($metro)
        ->assertSee('13.7563')->assertSee('100.5018');
    $this->get('/request?scenario=bangkok&locale='.$locale)->assertOk()
        ->assertSee('CF-IPLatitude')->assertSee('CF-IPLongitude')->assertSee('CF-Metro-Code');
})->with([
    ['en', 'Latitude', 'Longitude', 'Metro code'],
    ['th', 'ละติจูด', 'ลองจิจูด', 'รหัสเขตมหานคร'],
    ['ar', 'خط العرض', 'خط الطول', 'رمز المنطقة الحضرية'],
]);

it('keeps missing malformed and untrusted geographic fields unknown in the example', function (string $scenario): void {
    $state = app(ExampleHarness::class)->run($scenario, request());

    expect($state['visitor']->latitude)->toBeNull()
        ->and($state['visitor']->longitude)->toBeNull()
        ->and($state['visitor']->metroCode)->toBeNull();
    $this->get('/?scenario='.$scenario)->assertOk()->assertDontSee('13.7563');
})->with(['missing', 'malformed', 'untrusted']);

it('shows zero coordinates and preserves the metro identifier in the actual UI', function (): void {
    $fixture = RequestFixture::trusted(['CF-IPLatitude' => '0', 'CF-IPLongitude' => '0', 'CF-Metro-Code' => '0635']);
    config()->set('moztopia-cloudflare.trusted_proxies', $fixture->trustedProxies);

    $this->withServerVariables($fixture->server)->withHeaders($fixture->headers)
        ->get('/?scenario=live')->assertOk()->assertSee('<bdi>0</bdi>', false)->assertSee('0635');
});

it('changes application examples with country and timezone context', function (): void {
    $this->get('/ui?scenario=bangkok')->assertOk()->assertSee('Delivery within Thailand')->assertSee('19:00');
    $this->get('/ui?scenario=london')->assertOk()->assertSee('Delivery across the United Kingdom')->assertSee('12:00');
    $this->get('/ui?scenario=dubai')->assertOk()->assertSee('Delivery in the United Arab Emirates')->assertSee('16:00');
    $this->get('/ui?scenario=missing')->assertOk()->assertSee('Explore our delivery destinations')->assertSee('UTC fallback');
});

it('returns private no-store pages and omits unrelated sensitive headers', function (): void {
    $response = $this->withHeaders(['Authorization' => 'secret-token', 'X-Unrelated' => 'private-value'])
        ->get('/request?scenario=live')->assertOk()->assertDontSee('secret-token')->assertDontSee('private-value');
    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('keeps assets and navigation on the public origin behind an HTTPS proxy', function (): void {
    foreach (['/', '/ui', '/request'] as $path) {
        $response = $this->withServerVariables(['HTTP_HOST' => 'example.test', 'HTTPS' => 'off'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])->get($path)->assertOk();

        $response->assertSee('href="/assets/example.css"', false)
            ->assertSee('src="/assets/example.js"', false)
            ->assertDontSee('http://example.test', false);
    }
});

it('escapes live metadata and does not infer the interface language from country', function (): void {
    config()->set('cloudflare.trusted_proxies', ['192.0.2.10']);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->withHeaders(['CF-IPCountry' => 'AE', 'CF-Region' => '<script>alert(1)</script>'])
        ->get('/?scenario=live')->assertOk()
        ->assertSee('lang="en"', false)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

it('supports each enabled language and direction', function (string $locale, string $direction, string $heading): void {
    $this->get('/?locale='.$locale)->assertOk()->assertSee('lang="'.$locale.'"', false)
        ->assertSee('dir="'.$direction.'"', false)->assertSee($heading);
})->with([['en', 'ltr', 'Visitor context'], ['th', 'ltr', 'ข้อมูลผู้เยี่ยมชม'], ['ar', 'rtl', 'معلومات الزائر']]);

it('retains a valid language cookie and rejects planned-only language selection', function (): void {
    $this->withUnencryptedCookie('example_locale', 'th')->get('/')->assertOk()->assertSee('lang="th"', false);
    $this->get('/?locale=fr')->assertOk()->assertSee('lang="en"', false);
});

it('has complete nonempty catalogs with identical keys', function (): void {
    $english = require lang_path('en/demo.php');
    foreach (array_keys(config('example.locales')) as $locale) {
        $catalog = require lang_path($locale.'/demo.php');
        expect(array_keys($catalog))->toBe(array_keys($english));
        foreach ($catalog as $value) {
            expect($value)->toBeString()->not->toBeEmpty();
        }
    }
});

it('rejects invalid scenario selectors', function (string $selector): void {
    $this->get('/?'.$selector)->assertNotFound();
})->with(['scenario=../../config/app', 'scenario[]=bangkok', 'scenario=unknown']);
