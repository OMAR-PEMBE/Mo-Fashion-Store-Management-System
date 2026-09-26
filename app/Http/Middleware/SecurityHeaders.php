<?php

namespace App\Http\Middleware;

use App\Support\ProductionSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $production = app()->environment('production');
        if ($production && app(ProductionSecurity::class)->failures()) {
            $response = response('Service unavailable.', 503);
        } elseif ($production && ! $request->isSecure()) {
            // Never replay a submitted password or financial mutation over a redirect.
            $response = in_array($request->method(), ['GET', 'HEAD'], true)
                ? redirect()->away(rtrim(config('app.url'), '/').$request->getRequestUri(), 308)
                : response('HTTPS is required.', 400);
        } elseif ($production && ($request->getHost() !== parse_url(config('app.url'), PHP_URL_HOST)
            || $request->getPort() !== (parse_url(config('app.url'), PHP_URL_PORT) ?: 443))) {
            $response = response('Invalid host.', 400);
        } else {
            $response = $next($request);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'");
        $response->headers->set('Cache-Control', 'no-store, private');
        // Internal staff system: keep every page, including sign-in, out of search results.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        if ($production && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
