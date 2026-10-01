<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production, sends every request that did not arrive on APP_URL's host to APP_URL.
 * Covers www/alias domains and the common Hostinger layout where the subdomain folder also sits
 * inside the main site (main-domain.com/ims/...  →  https://ims.main-domain.com/...).
 */
class RedirectToCanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        if (! app()->isProduction() || $appHost === '' || strtolower($request->getHost()) === $appHost
            || $request->is('health') || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');
        // main-domain.com/ims/login → strip "/ims" when "ims.<host>" is the app host
        $segments = explode('/', ltrim($path, '/'), 2);
        $host = preg_replace('/^www\./', '', strtolower($request->getHost()));
        if ($segments[0] !== '' && strtolower($segments[0]).'.'.$host === $appHost) {
            $path = '/'.($segments[1] ?? '');
        }
        $query = $request->getQueryString();

        return redirect()->away($appUrl.($path === '/' ? '/' : $path).($query ? '?'.$query : ''), 301);
    }
}
