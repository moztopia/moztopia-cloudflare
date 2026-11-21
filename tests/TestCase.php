<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Tests;

use Moztopia\Cloudflare\CloudflareServiceProvider;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Boot the package in an isolated Laravel application for consumer tests.
 */

abstract class TestCase extends Orchestra
{
    /**
     * Register the provider that Composer package discovery advertises.
     *
     * @return list<class-string>
     */

    protected function getPackageProviders($app): array
    {
        return [CloudflareServiceProvider::class];
    }

    /**
     * Register the collision-resistant Blade alias advertised to consuming apps.
     *
     * @return array<string, class-string>
     */

    protected function getPackageAliases($app): array
    {
        return ['MoztopiaCloudflare' => Cloudflare::class];
    }
}
