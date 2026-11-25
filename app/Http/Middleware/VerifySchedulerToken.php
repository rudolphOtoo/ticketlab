<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the serverless maintenance endpoints with SCHEDULER_TOKEN.
 *
 *  1. "Authorization: Bearer <token>" is the standard. When a bearer token is sent it decides alone: a wrong one is
 *     refused even if a right "?token=" is also present.
 *  2. "?token=<token>" is a supported fallback for callers that cannot send headers, for the scheduler only
 *     (`header-only` turns it off per route, as run-migrate does) and only while SCHEDULER_ALLOW_QUERY_TOKEN is on.
 *
 * Fails closed: an empty token, or one shorter than MIN_LENGTH, refuses every request. Comparisons use hash_equals.
 *
 * The query token is removed from the request before anything else sees it, so logs, exception reports and
 * $request->fullUrl() never carry it. The platform's own access log (Vercel) is outside the app: that is why the
 * header is preferred.
 */
class VerifySchedulerToken
{
    public const int MIN_LENGTH = 32;

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'header-or-query'): Response
    {
        $queryToken = $request->query('token');
        self::scrubQueryToken($request);

        $expected = config('services.scheduler.token');

        if (!is_string($expected) || strlen($expected) < self::MIN_LENGTH) {
            return self::unauthorized();
        }

        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return hash_equals($expected, $bearer) ? $next($request) : self::unauthorized();
        }

        $queryAllowed = $mode !== 'header-only' && (bool) config('services.scheduler.allow_query_token', true);

        if ($queryAllowed && is_string($queryToken) && $queryToken !== '' && hash_equals($expected, $queryToken)) {
            return $next($request);
        }

        return self::unauthorized();
    }

    /** Drops "token" from the query bag and from the raw URI the request (and anything logging it) is built from. */
    private static function scrubQueryToken(Request $request): void
    {
        if (!$request->query->has('token')) {
            return;
        }

        $request->query->remove('token');
        $query = http_build_query($request->query->all());

        $request->server->set('QUERY_STRING', $query);
        $request->server->set('REQUEST_URI', $request->getPathInfo() . ($query === '' ? '' : '?' . $query));

        // The Request caches its URI parts; rebuild them from the scrubbed server bag.
        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent(),
        );
    }

    private static function unauthorized(): Response
    {
        return response()
            ->json(['error' => 'Unauthorized: send "Authorization: Bearer <SCHEDULER_TOKEN>".'], 401)
            ->header('Cache-Control', 'no-store');
    }
}
