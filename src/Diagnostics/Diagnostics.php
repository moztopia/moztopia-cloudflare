<?php

declare(strict_types=1);

namespace Moztopia\Cloudflare\Diagnostics;

use InvalidArgumentException;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;

/**
 * Hold immutable redacted ingress, header and configuration assessments.
 */

final readonly class Diagnostics
{
    /**
     * Indicate the same immediate-peer acceptance as requestContext().
     */

    public bool $trusted;

    /**
     * Hold case-insensitive supported header names and typed statuses only.
     *
     * @var array<string, HeaderStatus>
     */

    public array $headers;

    /**
     * Hold machine-readable configuration issue codes without configured values.
     *
     * @var list<string>
     */

    public array $configurationIssues;

    /**
     * Detach caller references and reject mutable values from the snapshot.
     *
     * @param array<string, HeaderStatus> $headers Supported header assessments.
     * @param list<string> $configurationIssues Safe configuration issue codes.
     */

    public function __construct(
        public IngressStatus $ingress,
        array $headers,
        array $configurationIssues = [],
    ) {
        $this->trusted = $ingress === IngressStatus::Trusted;
        $statuses = [];

        foreach ($headers as $name => $status) {
            if (!is_string($name) || !$status instanceof HeaderStatus || isset($statuses[strtolower($name)])) {
                throw new InvalidArgumentException('Diagnostics require unique header names and typed statuses.');
            }

            $statuses[strtolower($name)] = $status;
        }

        $issues = [];

        foreach ($configurationIssues as $issue) {
            if (!is_string($issue)) {
                throw new InvalidArgumentException('Diagnostic issue codes must be strings.');
            }

            $issues[] = $issue;
        }

        $this->headers = $statuses;
        $this->configurationIssues = $issues;
    }

    /**
     * Read a supported header's status without depending on its capitalization.
     */

    public function header(string $name): HeaderStatus
    {
        return $this->headers[strtolower($name)]
            ?? throw new InvalidArgumentException('This header has no supported diagnostic assessment.');
    }
}
