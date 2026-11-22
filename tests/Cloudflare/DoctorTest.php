<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\Cloudflare\Cloudflare as CloudflareService;

it('checks isolated samples without observing or changing the active browser request', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.99']);
    config()->set('cloudflare.api_token', 'private-config-token');
    $request = Request::create('/private-browser-path', server: ['REMOTE_ADDR' => '198.51.100.77']);
    $request->headers->add(['CF-Connecting-IP' => '203.0.113.73', 'CF-Region' => 'Private region', 'CF-IPCountry' => 'US']);
    $this->app->instance('request', $request);
    $configuration = config()->all();
    $container = Container::getInstance();
    $service = app(CloudflareService::class);
    Http::preventStrayRequests();

    expect(Artisan::call('moztopia-cloudflare:doctor', ['--json' => true]))->toBe(0);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($report['schemaVersion'])->toBe(1)
        ->and($report['scope'])->toBe('configuration_and_synthetic_samples')
        ->and($report['liveRequestObserved'])->toBeFalse()
        ->and($report['configuration'])->toBe(['source' => 'moztopia-cloudflare', 'configuredEntries' => 1, 'issues' => []])
        ->and($report['syntheticChecksPassed'])->toBeTrue()
        ->and($report['samples']['trusted']['headers']['cf-ipcountry'])->toBe('parsed')
        ->and($report['samples']['missing']['headers']['cf-ipcountry'])->toBe('missing')
        ->and($report['samples']['malformed']['headers']['cf-ipcountry'])->toBe('rejected')
        ->and($report['samples']['untrusted']['headers']['cf-ipcountry'])->toBe('untrusted')
        ->and($output)->not->toContain('192.0.2.99', '203.0.113.73', 'Private region', 'private-config-token', 'private-browser-path')
        ->and(request())->toBe($request)
        ->and(config()->all())->toBe($configuration)
        ->and(Container::getInstance())->toBe($container)
        ->and(app(CloudflareService::class))->toBe($service)
        ->and($service->requestContext()->trusted)->toBeFalse();
    Http::assertNothingSent();
});

it('explains explicit empty trust without enabling ingress or inheriting legacy peers', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', []);
    config()->set('cloudflare.trusted_proxies', ['192.0.2.10']);

    expect(Artisan::call('moztopia-cloudflare:doctor', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['configuration'])->toBe(['source' => 'moztopia-cloudflare', 'configuredEntries' => 0, 'issues' => ['empty_trust']])
        ->and($report['syntheticChecksPassed'])->toBeTrue()
        ->and(config('moztopia-cloudflare.trusted_proxies'))->toBe([]);
});

it('reports invalid or unrestricted configuration with failure and redacted values', function (mixed $proxies, string $issue): void {
    config()->set('moztopia-cloudflare.trusted_proxies', $proxies);

    expect(Artisan::call('moztopia-cloudflare:doctor', ['--json' => true]))->toBe(1);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($report['configuration']['issues'])->toContain($issue)
        ->and($report['liveRequestObserved'])->toBeFalse()
        ->and($report['syntheticChecksPassed'])->toBeTrue()
        ->and($output)->not->toContain('sensitive-invalid-value', '192.0.2.0/99', '0.0.0.0/0', '::/0');
})->with([
    'wrong shape' => ['sensitive-invalid-value', 'invalid_trust_list'],
    'bad entry' => [['sensitive-invalid-value'], 'invalid_trust_entry'],
    'bad prefix' => [['192.0.2.0/99'], 'invalid_trust_entry'],
    'unrestricted IPv4' => [['0.0.0.0/0'], 'unrestricted_trust'],
    'unrestricted IPv6' => [['::/0'], 'unrestricted_trust'],
]);

it('identifies the legacy configuration fallback without printing its entries', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', null);
    config()->set('cloudflare.trusted_proxies', ['192.0.2.99']);

    expect(Artisan::call('moztopia-cloudflare:doctor', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['configuration']['source'])->toBe('cloudflare')
        ->and(Artisan::output())->not->toContain('192.0.2.99');
});

it('uses the host locale for human guidance without changing application preferences', function (string $locale, string $title, string $note): void {
    app()->setLocale($locale);
    $timezone = config('app.timezone');

    expect(Artisan::call('moztopia-cloudflare:doctor'))->toBe(0)
        ->and(Artisan::output())->toContain($title, $note)
        ->and(Artisan::output())->not->toContain('moztopia-cloudflare::doctor.')
        ->and(app()->getLocale())->toBe($locale)
        ->and(config('app.timezone'))->toBe($timezone);
})->with([
    ['en', 'Moztopia Cloudflare local diagnostics', 'No live browser request has been observed.'],
    ['th', 'การตรวจสอบภายใน Moztopia Cloudflare', 'ยังไม่ได้ตรวจคำขอจริงจากเบราว์เซอร์'],
    ['ar', 'تشخيص Moztopia Cloudflare المحلي', 'لم تتم مراقبة طلب مباشر من المتصفح.'],
]);

it('keeps machine status output independent of the selected human language', function (): void {
    $baseline = null;

    foreach (['en', 'th', 'ar'] as $locale) {
        app()->setLocale($locale);
        expect(Artisan::call('moztopia-cloudflare:doctor', ['--json' => true]))->toBe(0);
        $current = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        if ($baseline !== null) {
            expect($current)->toBe($baseline);
        }

        $baseline = $current;
    }
});

it('supports Laravel vendor-language overrides for package-owned command copy', function (): void {
    $directory = lang_path('vendor/moztopia-cloudflare/en');
    $path = $directory.'/doctor.php';
    $existing = is_file($path) ? file_get_contents($path) : null;
    if (!is_dir($directory)) {
        mkdir($directory, recursive: true);
    }
    file_put_contents($path, '<?php return ["title" => "Host-owned doctor title"];');

    try {
        app('translator')->setLoaded([]);
        expect(Artisan::call('moztopia-cloudflare:doctor'))->toBe(0)
            ->and(Artisan::output())->toContain('Host-owned doctor title', 'No live browser request has been observed.');
    } finally {
        if ($existing === null) {
            unlink($path);
        } else {
            file_put_contents($path, $existing);
        }
    }
});

it('ships complete package catalogs with matching placeholder names', function (): void {
    $catalogs = [];
    foreach (['en', 'th', 'ar'] as $locale) {
        $catalog = require __DIR__.'/../../lang/'.$locale.'/doctor.php';
        $flat = Illuminate\Support\Arr::dot($catalog);
        $catalogs[$locale] = $flat;
        expect(array_keys($flat))->toBe(array_keys($catalogs['en']));

        foreach ($flat as $key => $value) {
            expect(is_string($value) && trim($value) !== '')->toBeTrue();
            preg_match_all('/:[a-z_]+/', $value, $translated);
            preg_match_all('/:[a-z_]+/', $catalogs['en'][$key], $english);
            sort($translated[0]);
            sort($english[0]);
            expect($translated[0])->toBe($english[0]);
        }
    }
});
