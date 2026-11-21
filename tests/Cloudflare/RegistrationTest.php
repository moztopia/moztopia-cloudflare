<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Moztopia\Cloudflare\Cloudflare\Cloudflare;
use Moztopia\Cloudflare\CloudflareServiceProvider;

it('advertises its provider and facade for Composer discovery', function (): void {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('moztopia/cloudflare')
        ->and($composer['license'])->toBe('Apache-2.0')
        ->and($composer['extra']['laravel']['providers'])->toContain(CloudflareServiceProvider::class)
        ->and($composer['extra']['laravel']['aliases']['MoztopiaCloudflare'])->toBe(Moztopia\Cloudflare\Facades\Cloudflare::class)
        ->and($composer['extra']['laravel']['aliases']['Cloudflare'])->toBe(Moztopia\Cloudflare\Facades\Cloudflare::class)
        ->and(app(Cloudflare::class))->toBeInstanceOf(Cloudflare::class);
});

it('ships untrusted defaults and publishes its configuration', function (): void {
    expect(config('cloudflare.trusted_proxies'))->toBe([]);

    $this->artisan('vendor:publish', ['--tag' => 'cloudflare-config', '--force' => true])->assertExitCode(0);
    $path = config_path('cloudflare.php');

    try {
        expect(is_file($path))->toBeTrue()
            ->and(require $path)->toBe(['trusted_proxies' => []]);
    } finally {
        @unlink($path);
    }
});

it('is discovered by Laravel from installed Composer package metadata', function (): void {
    $files = new Filesystem();
    $base = sys_get_temp_dir().'/moztopia-cloudflare-'.bin2hex(random_bytes(8));
    $files->makeDirectory($base.'/vendor/composer', recursive: true);
    $files->makeDirectory($base.'/bootstrap/cache', recursive: true);
    $package = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $files->put($base.'/vendor/composer/installed.json', json_encode(['packages' => [$package]], JSON_THROW_ON_ERROR));
    $manifest = new PackageManifest($files, $base, $base.'/bootstrap/cache/packages.php');

    try {
        $manifest->build();

        expect($manifest->providers())->toContain(CloudflareServiceProvider::class)
            ->and($manifest->aliases()['MoztopiaCloudflare'])->toBe(Moztopia\Cloudflare\Facades\Cloudflare::class)
            ->and($manifest->aliases()['Cloudflare'])->toBe(Moztopia\Cloudflare\Facades\Cloudflare::class);
    } finally {
        $files->deleteDirectory($base);
    }
});

it('supports Laravel configuration caching', function (): void {
    $path = $this->app->getCachedConfigPath();
    $configPath = config_path('cloudflare.php');

    $this->artisan('vendor:publish', ['--tag' => 'cloudflare-config', '--force' => true])->assertExitCode(0);
    file_put_contents($configPath, '<?php return ["trusted_proxies" => ["192.0.2.10"]];');

    try {
        $this->artisan('config:cache')->assertExitCode(0);
        $cached = require $path;

        expect($cached['cloudflare'])->toBe(['trusted_proxies' => ['192.0.2.10']]);
    } finally {
        @unlink($path);
        @unlink($configPath);
    }
});

it('publishes canonical configuration without overwriting a companion configuration file', function (): void {
    $legacyPath = config_path('cloudflare.php');
    $canonicalPath = config_path('moztopia-cloudflare.php');
    $existing = is_file($legacyPath) ? file_get_contents($legacyPath) : null;
    $companion = '<?php return ["api_token" => "synthetic-companion-token"];';
    file_put_contents($legacyPath, $companion);

    try {
        $this->artisan('vendor:publish', ['--tag' => 'moztopia-cloudflare-config', '--force' => true])->assertExitCode(0);

        expect(file_get_contents($legacyPath))->toBe($companion)
            ->and(require $canonicalPath)->toBe(['trusted_proxies' => null]);
    } finally {
        @unlink($canonicalPath);
        if ($existing === null) {
            @unlink($legacyPath);
        } else {
            file_put_contents($legacyPath, $existing);
        }
    }
});

it('caches explicit canonical settings alongside legacy settings without changing precedence', function (): void {
    $cachePath = $this->app->getCachedConfigPath();
    $canonicalPath = config_path('moztopia-cloudflare.php');
    $legacyPath = config_path('cloudflare.php');
    file_put_contents($canonicalPath, '<?php return ["trusted_proxies" => []];');
    file_put_contents($legacyPath, '<?php return ["trusted_proxies" => ["192.0.2.10"]];');

    try {
        $this->artisan('config:cache')->assertExitCode(0);
        $cached = require $cachePath;
        $container = new Illuminate\Container\Container();
        $container->instance('config', new Illuminate\Config\Repository($cached));
        $request = Illuminate\Http\Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $request->headers->set('CF-IPCountry', 'TH');
        $container->instance('request', $request);

        expect($cached['moztopia-cloudflare']['trusted_proxies'])->toBe([])
            ->and($cached['cloudflare']['trusted_proxies'])->toBe(['192.0.2.10'])
            ->and((new Cloudflare($container))->requestContext()->trusted)->toBeFalse();
    } finally {
        @unlink($cachePath);
        @unlink($canonicalPath);
        @unlink($legacyPath);
    }
});

it('retains an unambiguous discovery alias alongside companion aliases in either manifest order', function (bool $companionFirst): void {
    $files = new Filesystem();
    $base = sys_get_temp_dir().'/moztopia-coexistence-manifest-'.bin2hex(random_bytes(8));
    $files->makeDirectory($base.'/vendor/composer', recursive: true);
    $files->makeDirectory($base.'/bootstrap/cache', recursive: true);
    $package = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $companion = ['name' => 'example/cloudflare-api', 'extra' => ['laravel' => [
        'providers' => ['Example\\ApiServiceProvider'],
        'aliases' => ['Cloudflare' => 'Example\\ApiFacade'],
    ]]];
    $packages = $companionFirst ? [$companion, $package] : [$package, $companion];
    $files->put($base.'/vendor/composer/installed.json', json_encode(['packages' => $packages], JSON_THROW_ON_ERROR));

    try {
        $manifest = new PackageManifest($files, $base, $base.'/bootstrap/cache/packages.php');
        $manifest->build();

        expect($manifest->providers())->toContain(CloudflareServiceProvider::class, 'Example\\ApiServiceProvider')
            ->and($manifest->aliases()['MoztopiaCloudflare'])->toBe(Moztopia\Cloudflare\Facades\Cloudflare::class);
    } finally {
        $files->deleteDirectory($base);
    }
})->with(['companion first' => true, 'context first' => false]);
