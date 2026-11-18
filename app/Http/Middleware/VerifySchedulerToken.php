<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the serverless maintenance endpoints (scheduler and migrations) with SCHEDULER_TOKEN.
 *
 * The token must arrive as "Authorization: Bearer <token>". Query strings end up in proxy and platform logs, so
 * "?token=" is accepted for one release only, with a deprecation warning, and will then be removed. Fails closed:
 * with no token configured every request is refused.
 */
class VerifySchedulerToken
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.scheduler.token');

        if (!is_string($expected) || $expected === '') {
            return self::unauthorized();
        }

        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return hash_equals($expected, $bearer) ? $next($request) : self::unauthorized();
        }

        $legacy = $request->query('token');

        if (is_string($legacy) && $legacy !== '' && hash_equals($expected, $legacy)) {
            // Never log the token itself.
            Log::warning(
                'Deprecated: scheduler token sent as ?token= query parameter. Send "Authorization: Bearer <token>" instead; the query parameter will stop working in the next release.',
                [
                    'path' => $request->path(),
                ],
            );

            return $next($request);
        }

        return self::unauthorized();
    }

    private static function unauthorized(): Response
    {
        return response()->json(['error' => 'Unauthorized: send "Authorization: Bearer <SCHEDULER_TOKEN>".'], 401);
    }
}
