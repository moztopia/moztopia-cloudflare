<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Moztopia\Cloudflare\Cloudflare\Cloudflare;

/**
 * Run the actual package against either a live request or an isolated fixture.
 */

final class ExampleHarness
{
    /**
     * Resolve the application's real package service for live mode.
     */

    public function __construct(private readonly Cloudflare $cloudflare)
    {
    }

    /**
     * Read context while leaving the application request and trust list intact.
     *
     * @return array<string, mixed>
     */

    public function run(string $scenario, Request $request): array
    {
        $service = $this->cloudflare;
        $source = $request;
        $simulated = $scenario !== 'live';

        if ($simulated) {
            $fixture = config('example.scenarios.'.$scenario);
            abort_unless(is_array($fixture), 404);
            $source = Request::create('/simulated', server: ['REMOTE_ADDR' => $fixture['peer']]);
            $source->headers->add($fixture['headers']);
            $sandbox = new Container();
            $sandbox->instance('request', $source);
            $sandbox->instance('config', new Repository(['cloudflare' => ['trusted_proxies' => ['192.0.2.10']]]));
            $service = new Cloudflare($sandbox);
        }

        $headers = [];

        foreach (['CF-IPCountry', 'CF-IPContinent', 'CF-Region', 'CF-Region-Code', 'CF-IPCity', 'CF-Timezone', 'CF-IPLatitude', 'CF-IPLongitude', 'CF-Metro-Code', 'CF-Connecting-IP', 'CF-Ray', 'CF-Visitor'] as $name) {
            $headers[$name] = $source->headers->get($name);
        }

        return [
            'visitor' => $service->visitor(),
            'context' => $service->requestContext(),
            'headers' => $headers,
            'simulated' => $simulated,
            'scenario' => $scenario,
        ];
    }
}
