<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare;

use Illuminate\Container\Container;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Moztopia\Cloudflare\Cloudflare\Cloudflare;
use Moztopia\Cloudflare\Commands\DoctorCommand\DoctorCommand;
use Moztopia\Cloudflare\Facades\Cloudflare as CloudflareFacade;

/**
 * Register the Cloudflare application API and publishable defaults.
 */

final class CloudflareServiceProvider extends ServiceProvider
{
    /**
     * Register a stateless service that resolves the active request on demand.
     */

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/moztopia-cloudflare.php', 'moztopia-cloudflare');
        $this->mergeConfigFrom(__DIR__.'/../config/cloudflare.php', 'cloudflare');

        $this->app->singleton(Cloudflare::class, static fn (): Cloudflare => new Cloudflare(
            static fn (): Container => Container::getInstance(),
        ));

        $this->registerLegacyAlias();
    }

    /**
     * Preserve the standalone legacy alias without claiming another facade's name.
     */

    private function registerLegacyAlias(): void
    {
        if (!class_exists(AliasLoader::class)) {
            return;
        }

        $loader = AliasLoader::getInstance();

        if (!array_key_exists('Cloudflare', $loader->getAliases()) && !class_exists('Cloudflare', false)) {
            $loader->alias('Cloudflare', CloudflareFacade::class);
        }
    }

    /**
     * Expose the package configuration to consuming applications.
     */

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'moztopia-cloudflare');

        if ($this->app->runningInConsole()) {
            $this->commands([DoctorCommand::class]);

            $this->publishes([
                __DIR__.'/../config/moztopia-cloudflare.php' => config_path('moztopia-cloudflare.php'),
            ], 'moztopia-cloudflare-config');

            $this->publishes([
                __DIR__.'/../config/cloudflare.php' => config_path('cloudflare.php'),
            ], 'cloudflare-config');
        }
    }
}
