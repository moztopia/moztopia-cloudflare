<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve an explicit supported language and prevent shared caching of context.
 */

final class ExampleLocaleMiddleware
{
    /**
     * Apply the selected language without guessing it from the visitor's country.
     *
     * @param Closure(Request): Response $next The remaining request pipeline.
     */

    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('example.locales'));
        $choice = $request->query('locale', $request->cookie('example_locale'));
        $locale = is_string($choice) && in_array($choice, $supported, true)
            ? $choice
            : ($choice === null ? $request->getPreferredLanguage($supported) : config('app.fallback_locale'));

        app()->setLocale($locale ?? 'en');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
        $response->headers->setCookie(cookie('example_locale', app()->getLocale(), 43200, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'));

        return $response;
    }
}
