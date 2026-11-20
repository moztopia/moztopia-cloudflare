<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Moztopia\Cloudflare\Cloudflare\Cloudflare;

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
        $this->mergeConfigFrom(__DIR__.'/../config/cloudflare.php', 'cloudflare');

        $this->app->singleton(Cloudflare::class, fn (Container $app): Cloudflare => new Cloudflare($app));
    }

    /**
     * Expose the package configuration to consuming applications.
     */

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cloudflare.php' => config_path('cloudflare.php'),
            ], 'cloudflare-config');
        }
    }
}
