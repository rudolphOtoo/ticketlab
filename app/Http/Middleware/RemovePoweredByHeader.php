<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drops "X-Powered-By: PHP/x.y.z" from every response. api/php.ini sets expose_php = Off on Vercel; this is the
 * fallback for runtimes that ignore it. PHP adds the header itself when the request starts, so header_remove() takes
 * it back before the response is sent.
 */
final class RemovePoweredByHeader
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->remove('X-Powered-By');

        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
