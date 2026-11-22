<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Commands\DoctorCommand;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Configuration;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Moztopia\Cloudflare\Cloudflare\Cloudflare;
use Moztopia\Cloudflare\Cloudflare\parts\CloudflareTrust;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
use Moztopia\Cloudflare\RequestFixture\RequestFixture;
use Symfony\Component\Console\Input\InputOption;

/**
 * Check effective local configuration and isolated samples without live probes.
 *
 * @internal
 */

final class DoctorCommand extends Command
{
    /**
     * Use the collision-resistant package command namespace.
     *
     * @var string
     */

    protected $name = 'moztopia-cloudflare:doctor';

    /**
     * Respect the host application's translation and fallback choices.
     */

    public function __construct(private readonly Translator $translator)
    {
        $this->description = $this->message('description');
        parent::__construct();
    }

    /**
     * Return redacted configuration and clearly identified synthetic checks.
     */

    public function handle(Configuration $configuration): int
    {
        $trust = new CloudflareTrust($configuration);
        $samples = [];
        $passed = true;

        foreach ([
            'trusted' => HeaderStatus::Parsed,
            'missing' => HeaderStatus::Missing,
            'malformed' => HeaderStatus::Rejected,
            'untrusted' => HeaderStatus::Untrusted,
        ] as $name => $expected) {
            $fixture = RequestFixture::{$name}();
            $sandbox = new Container();
            $sandbox->instance('config', new Repository(['moztopia-cloudflare' => ['trusted_proxies' => $fixture->trustedProxies]]));
            $request = Request::create('/synthetic-doctor/'.$name, server: $fixture->server);
            $request->headers->add($fixture->headers);
            $sandbox->instance('request', $request);
            $samples[$name] = (new Cloudflare($sandbox))->diagnostics();
            $passed = $passed
                && $samples[$name]->header('CF-IPCountry') === $expected
                && $samples[$name]->trusted === ($name !== 'untrusted');
        }

        $payload = [
            'schemaVersion' => 1,
            'scope' => 'configuration_and_synthetic_samples',
            'liveRequestObserved' => false,
            'configuration' => [
                'source' => $trust->sourceNamespace(),
                'configuredEntries' => $trust->configuredCount(),
                'issues' => $trust->issues(),
            ],
            'syntheticChecksPassed' => $passed,
            'samples' => $samples,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($this->message('title'));
            $this->line($this->message('no_live_request'));
            $this->line($this->message('source', ['namespace' => $trust->sourceNamespace()]));
            $this->line($this->message('entries', ['count' => $trust->configuredCount()]));

            foreach ($trust->issues() as $issue) {
                $this->warn($this->message('issues.'.$issue));
            }

            foreach ($samples as $name => $sample) {
                $this->line($this->message('sample', [
                    'sample' => $this->message('samples.'.$name),
                    'status' => $this->message('statuses.'.$sample->header('CF-IPCountry')->value),
                ]));
            }

            $this->comment($this->message('guidance'));
            $this->comment($this->message('no_product_inference'));

            if (!$passed) {
                $this->error($this->message('sample_failed'));
            }
        }

        return $passed && array_diff($trust->issues(), ['empty_trust']) === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Offer machine-readable output without language-dependent status codes.
     *
     * @return list<array{string, null, int, string}>
     */

    protected function getOptions(): array
    {
        return [['json', null, InputOption::VALUE_NONE, $this->message('json_help')]];
    }

    /**
     * Resolve package copy through the host's native translation namespace.
     *
     * @param array<string, string|int> $replace Translation placeholders.
     */

    private function message(string $key, array $replace = []): string
    {
        return (string) $this->translator->get('moztopia-cloudflare::doctor.'.$key, $replace);
    }
}
