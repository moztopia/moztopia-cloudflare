<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ExampleHarness;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Intl\Countries;

/**
 * Prepare the context and application decisions shown by the three examples.
 */

final class ExampleController
{
    /**
     * Render the chosen page using the shared, explicit request scenario.
     */

    public function __invoke(Request $request, ExampleHarness $harness, string $page = 'visitor'): View
    {
        $scenario = $request->query('scenario', 'bangkok');
        abort_unless(is_string($scenario) && ($scenario === 'live' || array_key_exists($scenario, config('example.scenarios'))), 404);
        $state = $harness->run($scenario, $request);
        $visitor = $state['visitor'];
        $country = $visitor->countryCode;
        $state['countryName'] = $country === null ? null : Countries::getName($country, app()->getLocale());
        $state['flag'] = $country === null ? '◎' : mb_chr(ord($country[0]) + 127397).mb_chr(ord($country[1]) + 127397);
        $state['deliveryKey'] = match ($country) {
            'TH' => 'delivery_th',
            'GB' => 'delivery_gb',
            'AE' => 'delivery_ae',
            default => 'delivery_global',
        };
        $state['eventTime'] = CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC')
            ->setTimezone($visitor->timezone ?? 'UTC')->locale(app()->getLocale())->isoFormat('D MMM YYYY · HH:mm');
        $state['snippet'] = match ($page) {
            'ui' => <<<'BLADE'
                @if (Cloudflare::visitor()->countryCode === 'TH')
                    <p>Delivery within Thailand is available.</p>
                @endif
                BLADE,
            'request' => <<<'PHP'
                $context = Cloudflare::requestContext();

                $context->trusted;
                $context->clientIp;
                $context->rayId;
                $context->scheme;
                PHP,
            default => <<<'PHP'
                $visitor = Cloudflare::visitor();

                $visitor->countryCode;
                $visitor->region;
                $visitor->city;
                $visitor->timezone;
                $visitor->latitude;
                $visitor->longitude;
                $visitor->metroCode;
                PHP,
        };

        return view('features.examples.'.$page.'.index', $state + [
            'page' => $page,
            'query' => ['scenario' => $scenario, 'locale' => app()->getLocale()],
            'scenarioOptions' => ['live', ...array_keys(config('example.scenarios'))],
            'locales' => config('example.locales'),
        ]);
    }
}
