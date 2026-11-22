<?php

declare(strict_types=1);

use Moztopia\Cloudflare\Diagnostics\Diagnostics;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;

it('keeps report statuses and issues independent of caller-owned references', function (): void {
    $status = HeaderStatus::Parsed;
    $issue = 'empty_trust';
    $report = new Diagnostics(IngressStatus::Trusted, ['CF-Region' => &$status], [&$issue]);
    $status = HeaderStatus::Rejected;
    $issue = 'changed';

    expect($report->header('cf-REGION'))->toBe(HeaderStatus::Parsed)
        ->and($report->configurationIssues)->toBe(['empty_trust'])
        ->and(fn () => $report->headers['cf-region'] = HeaderStatus::Rejected)->toThrow(Error::class)
        ->and(fn () => $report->configurationIssues[] = 'changed')->toThrow(Error::class)
        ->and(fn () => $report->header('X-Unrelated'))->toThrow(InvalidArgumentException::class);
});

it('rejects mutable or ambiguous diagnostic input', function (array $headers, array $issues): void {
    expect(fn () => new Diagnostics(IngressStatus::Untrusted, $headers, $issues))->toThrow(InvalidArgumentException::class);
})->with([
    'mutable header' => [['CF-Region' => new stdClass()], []],
    'ambiguous name' => [['CF-Region' => HeaderStatus::Parsed, 'cf-region' => HeaderStatus::Missing], []],
    'mutable issue' => [[], [new stdClass()]],
]);
