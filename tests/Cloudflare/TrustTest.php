<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Moztopia\Cloudflare\Facades\Cloudflare;
use Moztopia\Cloudflare\HeaderStatus\HeaderStatus;
use Moztopia\Cloudflare\IngressStatus\IngressStatus;
use Moztopia\Cloudflare\RequestContext\RequestContext;
use Moztopia\Cloudflare\Visitor\Visitor;

it('assesses the raw immediate peer consistently across all context APIs', function (mixed $proxies, mixed $peer, IngressStatus $ingress): void {
    Http::preventStrayRequests();
    config()->set('moztopia-cloudflare.trusted_proxies', $proxies);
    config()->set('cloudflare.trusted_proxies', ['192.0.2.10']);
    $request = Request::create('/trust', server: ['REMOTE_ADDR' => $peer]);
    $request->headers->add([
        'CF-IPCountry' => 'TH', 'CF-IPContinent' => 'AS', 'CF-Region' => 'Bangkok',
        'CF-Region-Code' => '10', 'CF-IPCity' => 'Bangkok', 'CF-Timezone' => 'Asia/Bangkok',
        'CF-IPLatitude' => '13.7563', 'CF-IPLongitude' => '100.5018', 'CF-Metro-Code' => '001',
        'CF-Connecting-IP' => '203.0.113.10', 'CF-Ray' => '0123456789abcdef-BKK',
        'CF-Visitor' => '{"scheme":"https"}',
    ]);
    $this->app->instance('request', $request);
    $trusted = $ingress === IngressStatus::Trusted;
    $report = Cloudflare::diagnostics();

    expect($report->ingress)->toBe($ingress)
        ->and($report->trusted)->toBe($trusted)
        ->and($report->headers)->toHaveCount(12)
        ->and(Cloudflare::requestContext()->trusted)->toBe($trusted)
        ->and(array_unique($report->headers, SORT_REGULAR))->toEqual([
            'cf-ipcountry' => $trusted ? HeaderStatus::Parsed : HeaderStatus::Untrusted,
        ]);

    if ($trusted) {
        expect(Cloudflare::visitor()->latitude)->toBe(13.7563)
            ->and(Cloudflare::visitor()->metroCode)->toBe('001')
            ->and(Cloudflare::requestContext()->clientIp)->toBe('203.0.113.10');
    } else {
        expect(Cloudflare::visitor())->toEqual(new Visitor())
            ->and(Cloudflare::requestContext())->toEqual(new RequestContext());
    }

    Http::assertNothingSent();
})->with([
    'exact IPv4' => [['192.0.2.10'], '192.0.2.10', IngressStatus::Trusted],
    'different IPv4' => [['192.0.2.10'], '192.0.2.11', IngressStatus::Untrusted],
    'IPv4 network lower boundary' => [['192.0.2.0/24'], '192.0.2.0', IngressStatus::Trusted],
    'IPv4 network upper boundary' => [['192.0.2.0/24'], '192.0.2.255', IngressStatus::Trusted],
    'IPv4 outside network' => [['192.0.2.0/24'], '192.0.3.0', IngressStatus::Untrusted],
    'IPv4 host CIDR' => [['192.0.2.10/32'], '192.0.2.10', IngressStatus::Trusted],
    'IPv4 outside host CIDR' => [['192.0.2.10/32'], '192.0.2.11', IngressStatus::Untrusted],
    'IPv4 two-address CIDR' => [['192.0.2.10/31'], '192.0.2.11', IngressStatus::Trusted],
    'IPv4 outside two-address CIDR' => [['192.0.2.10/31'], '192.0.2.12', IngressStatus::Untrusted],
    'exact IPv6' => [['2001:db8::10'], '2001:db8::10', IngressStatus::Trusted],
    'equivalent expanded IPv6' => [['2001:db8::10'], '2001:0db8:0000:0000:0000:0000:0000:0010', IngressStatus::Trusted],
    'different IPv6' => [['2001:db8::10'], '2001:db8::11', IngressStatus::Untrusted],
    'IPv6 network lower boundary' => [['2001:db8::/32'], '2001:db8::', IngressStatus::Trusted],
    'IPv6 network upper boundary' => [['2001:db8::/32'], '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff', IngressStatus::Trusted],
    'IPv6 outside network' => [['2001:db8::/32'], '2001:db9::', IngressStatus::Untrusted],
    'IPv6 host CIDR' => [['2001:db8::10/128'], '2001:db8::10', IngressStatus::Trusted],
    'IPv6 outside host CIDR' => [['2001:db8::10/128'], '2001:db8::11', IngressStatus::Untrusted],
    'IPv6 two-address CIDR' => [['2001:db8::10/127'], '2001:db8::11', IngressStatus::Trusted],
    'IPv6 outside two-address CIDR' => [['2001:db8::10/127'], '2001:db8::12', IngressStatus::Untrusted],
    'IPv4 peer does not match IPv6 policy' => [['2001:db8::/32'], '192.0.2.10', IngressStatus::Untrusted],
    'IPv6 peer does not match IPv4 policy' => [['192.0.2.0/24'], '2001:db8::10', IngressStatus::Untrusted],
    'mapped IPv4 matches its IPv4 policy' => [['192.0.2.0/24'], '::ffff:192.0.2.10', IngressStatus::Trusted],
    'mapped IPv4 outside IPv4 policy' => [['192.0.2.0/24'], '::ffff:198.51.100.10', IngressStatus::Untrusted],
    'explicit mapped IPv6 policy' => [['::ffff:192.0.2.0/120'], '::ffff:192.0.2.10', IngressStatus::Trusted],
    'invalid entry cannot suppress a valid peer' => [[false, '*', '2001:db8::/32'], '2001:db8::10', IngressStatus::Trusted],
    'empty canonical policy suppresses legacy' => [[], '192.0.2.10', IngressStatus::NoTrustedProxies],
    'invalid canonical list suppresses legacy' => ['*', '192.0.2.10', IngressStatus::InvalidConfiguration],
    'boolean list' => [false, '192.0.2.10', IngressStatus::InvalidConfiguration],
    'wildcard entry' => [['*'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'nested list entry' => [[['192.0.2.10']], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'numeric entry' => [[3221225994], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'negative IPv4 mask' => [['192.0.2.0/-1'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'oversized IPv4 mask' => [['192.0.2.0/33'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'oversized IPv6 mask' => [['2001:db8::/129'], '2001:db8::10', IngressStatus::InvalidConfiguration],
    'empty mask' => [['192.0.2.0/'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'multiple masks' => [['2001:db8::/32/64'], '2001:db8::10', IngressStatus::InvalidConfiguration],
    'proxy with port' => [['192.0.2.10:443'], '192.0.2.10', IngressStatus::InvalidConfiguration],
    'IPv6 zone identifier' => [['fe80::1%eth0'], 'fe80::1', IngressStatus::InvalidConfiguration],
    'peer with port' => [['192.0.2.10'], '192.0.2.10:443', IngressStatus::InvalidPeer],
    'peer chain' => [['192.0.2.10'], '192.0.2.10, 198.51.100.10', IngressStatus::InvalidPeer],
    'integer peer' => [['192.0.2.10'], 3221225994, IngressStatus::InvalidPeer],
    'array peer' => [['192.0.2.10'], ['192.0.2.10'], IngressStatus::InvalidPeer],
    'absent peer' => [['192.0.2.10'], null, IngressStatus::MissingPeer],
    'empty peer' => [['192.0.2.10'], '', IngressStatus::MissingPeer],
]);

it('does not use Laravel forwarded IPs or header claims as Cloudflare ingress', function (string $peer, string $forwarded, bool $trusted): void {
    $originalProxies = Request::getTrustedProxies();
    $originalHeaders = Request::getTrustedHeaderSet();
    Request::setTrustedProxies([$peer], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

    try {
        config()->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10']);
        $request = Request::create('/forwarded', server: ['REMOTE_ADDR' => $peer]);
        $request->headers->add([
            'X-Forwarded-For' => $forwarded,
            'X-Forwarded-Proto' => 'https',
            'Forwarded' => 'for=192.0.2.10;proto=https',
            'True-Client-IP' => '192.0.2.10',
            'X-Real-IP' => '192.0.2.10',
            'CF-Connecting-IP' => '192.0.2.10',
            'CF-IPCountry' => 'TH',
        ]);
        $this->app->instance('request', $request);

        expect($request->ip())->toBe($forwarded)
            ->and($request->isSecure())->toBeTrue()
            ->and(Cloudflare::requestContext()->trusted)->toBe($trusted)
            ->and(Cloudflare::visitor()->countryCode)->toBe($trusted ? 'TH' : null)
            ->and(Cloudflare::diagnostics()->trusted)->toBe($trusted)
            ->and(Request::getTrustedProxies())->toBe([$peer])
            ->and(Request::getTrustedHeaderSet())->toBe(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO)
            ->and($request->server->get('REMOTE_ADDR'))->toBe($peer);
    } finally {
        Request::setTrustedProxies($originalProxies, $originalHeaders);
    }
})->with([
    'untrusted IPv4 claiming accepted ingress' => ['198.51.100.10', '192.0.2.10', false],
    'untrusted IPv6 claiming accepted ingress' => ['2001:db8::10', '192.0.2.10', false],
    'accepted peer forwarding an unrelated visitor' => ['192.0.2.10', '203.0.113.10', true],
]);

it('cannot recover an original proxy when the server rewrites REMOTE_ADDR', function (): void {
    config()->set('moztopia-cloudflare.trusted_proxies', ['192.0.2.10']);
    $request = Request::create('/rewritten', server: ['REMOTE_ADDR' => '203.0.113.10']);
    $request->headers->add(['CF-IPCountry' => 'TH', 'X-Forwarded-For' => '192.0.2.10']);
    $this->app->instance('request', $request);

    expect(Cloudflare::visitor())->toEqual(new Visitor())
        ->and(Cloudflare::diagnostics()->ingress)->toBe(IngressStatus::Untrusted);

    $request->server->set('REMOTE_ADDR', '192.0.2.10');

    expect(Cloudflare::visitor()->countryCode)->toBe('TH')
        ->and(Cloudflare::diagnostics()->ingress)->toBe(IngressStatus::Trusted);
});

it('identifies operator-supplied unrestricted CIDRs without silently changing the established policy', function (string $proxy, string $peer): void {
    config()->set('moztopia-cloudflare.trusted_proxies', [$proxy]);
    $this->app->instance('request', Request::create('/', server: ['REMOTE_ADDR' => $peer]));

    expect(Cloudflare::diagnostics()->configurationIssues)->toBe(['unrestricted_trust'])
        ->and(Cloudflare::requestContext()->trusted)->toBeTrue();

    $this->artisan('moztopia-cloudflare:doctor', ['--json' => true])->assertFailed();
})->with([
    'IPv4' => ['0.0.0.0/0', '198.51.100.10'],
    'IPv6' => ['::/0', '2001:db8::10'],
]);
