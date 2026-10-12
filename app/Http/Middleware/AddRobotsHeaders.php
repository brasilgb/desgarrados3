<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells search engines what must stay out of the index, also for responses without HTML metadata.
 *
 * Private areas get "noindex, nofollow"; public pages reached through filters, searches or other
 * query combinations get "noindex, follow" so their links are still discovered.
 */
class AddRobotsHeaders
{
    /** Query parameters that keep a public page canonical (mirrors resources/js/lib/seo.ts). */
    public const INDEXABLE_PARAMS = ['page'];

    private const PRIVATE_PATHS = [
        'admin', 'admin/*', 'settings', 'settings/*', 'dashboard', 'login', 'logout', 'register', 'forgot-password', 'reset-password/*',
        'two-factor-challenge', 'email/verify', 'email/verify/*', 'user/*', 'passkeys/*', '.well-known/*', 'contribua/obrigado', 'busca/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->headers->has('X-Robots-Tag')) {
            return $response;
        }

        if ($request->is(...self::PRIVATE_PATHS)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif ($response->getStatusCode() >= 400 || array_diff(array_keys($request->query()), self::INDEXABLE_PARAMS) !== []) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
