<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubdivisionAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $tier  Optional tier constraint ('admin', 'superadmin')
     */
    public function handle(Request $request, Closure $next, ?string $tier = null): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Tier 3: Door Ushers & Gate Scanners are restricted from administrative roster & financial ledgers
        if ($user->role === 'usher') {
            abort(403, 'Door terminal operators are restricted from administrative roster and financial ledgers.');
        }

        // Tier 1: Superadmin clearance check
        if ($tier === 'superadmin' && $user->subdivision_id !== null && $user->role !== 'superadmin') {
            abort(403, 'Cross-zone superadmin clearance required.');
        }

        // Tier 2: Subdivision Admin zone boundary check
        $requestedSubdivisionId = $request->route('subdivision') ?? $request->query('subdivision_id');
        if ($requestedSubdivisionId !== null && $user->subdivision_id !== null && $user->role !== 'superadmin') {
            $requestedId = is_object($requestedSubdivisionId) ? $requestedSubdivisionId->id : (int) $requestedSubdivisionId;
            if ($user->subdivision_id !== $requestedId) {
                abort(403, 'You are only authorized to access records within your assigned zone.');
            }
        }

        return $next($request);
    }
}
