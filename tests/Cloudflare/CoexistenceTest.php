<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Moztopia\Cloudflare\Cloudflare\Cloudflare;
use Moztopia\Cloudflare\CloudflareServiceProvider;

it('preserves companion bindings and configuration in either provider order without discovery', function (string $companionName, bool $companionFirst): void {
    $original = Container::getInstance();
    $publications = ServiceProvider::$publishes;
    $groups = ServiceProvider::$publishGroups;
    $base = sys_get_temp_dir().'/moztopia-coexistence-'.bin2hex(random_bytes(8));
    $files = new Filesystem();
    $files->makeDirectory($base.'/config', recursive: true);
    $source = $base.'/companion.php';
    $files->put($source, '<?php return ["api_token" => "synthetic-companion-token", "account_id" => "synthetic-account"];');

    try {
        $application = new Application($base);
        $application->useConfigPath($base.'/config');
        $application->instance('config', new Repository([
            'cloudflare' => ['trusted_proxies' => ['198.51.100.10']],
            'moztopia-cloudflare' => ['trusted_proxies' => ['192.0.2.10']],
        ]));
        $request = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $request->headers->set('CF-IPCountry', 'TH');
        $application->instance('request', $request);
        $companion = new class ($application, $source, $companionName) extends ServiceProvider {
            /**
             * Supply a representative API wrapper's source and binding identity.
             */

            public function __construct(ApplicationContract $app, private readonly string $source, private readonly string $identity)
            {
                parent::__construct($app);
            }

            /**
             * Use the legacy namespace and binding like an API-management wrapper.
             */

            public function register(): void
            {
                $this->mergeConfigFrom($this->source, 'cloudflare');
                $this->app->singleton('cloudflare', fn (): string => $this->identity);
            }

            /**
             * Retain the wrapper's common publishing tag and configuration path.
             */

            public function boot(): void
            {
                $this->publishes([$this->source => config_path('cloudflare.php')], 'cloudflare-config');
            }
        };

        if ($companionFirst) {
            $application->register($companion);
            $application->register(CloudflareServiceProvider::class);
        } else {
            $application->register(CloudflareServiceProvider::class);
            $application->register($companion);
        }

        $application->boot();
        $configuration = $application->make('config');

        expect($application->make('cloudflare'))->toBe($companionName)
            ->and($configuration->get('cloudflare.api_token'))->toBe('synthetic-companion-token')
            ->and($configuration->get('cloudflare.account_id'))->toBe('synthetic-account')
            ->and($configuration->get('cloudflare.trusted_proxies'))->toBe(['198.51.100.10'])
            ->and($application->make(Cloudflare::class)->visitor()->countryCode)->toBe('TH')
            ->and(ServiceProvider::pathsToPublish(CloudflareServiceProvider::class, 'moztopia-cloudflare-config'))->toBe([
                dirname(__DIR__, 2).'/src/../config/moztopia-cloudflare.php' => $base.'/config/moztopia-cloudflare.php',
            ])
            ->and(ServiceProvider::pathsToPublish($companion::class))->toBe([$source => $base.'/config/cloudflare.php']);
    } finally {
        Container::setInstance($original);
        ServiceProvider::$publishes = $publications;
        ServiceProvider::$publishGroups = $groups;
        $files->deleteDirectory($base);
    }
})->with(['hampel-like API wrapper', 'ghost-like API wrapper'])->with(['companion first' => true, 'context first' => false]);
