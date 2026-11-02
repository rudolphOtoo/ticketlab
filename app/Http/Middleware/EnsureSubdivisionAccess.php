<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Subdivision;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubdivisionAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $tier  Optional tier constraint ('ops', 'admin', 'superadmin')
     */
    public function handle(Request $request, Closure $next, ?string $tier = null): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            abort(401);
        }

        // Fail closed: without an organisation every org-scoped query would fall back to a global view.
        if (!$user->hasOrganizationAccess()) {
            abort(403, 'Your account is not assigned to an organization.');
        }

        // Tier 3: Staff field operators are restricted from administrative surfaces & financial ledgers
        if ($tier !== 'ops' && !$user->isAdmin()) {
            abort(403, 'Staff field operators are restricted from administrative roster and financial ledgers.');
        }

        // Tier 1: Superadmin clearance check
        if ($tier === 'superadmin' && $user->subdivision_id !== null && !$user->isSuperadmin()) {
            abort(403, 'Cross-subdivision superadmin clearance required.');
        }

        // Tier 2: Subdivision Admin boundary check
        $requestedSubdivisionId = $request->route('subdivision') ?? $request->query('subdivision_id');
        if ($requestedSubdivisionId !== null && $user->subdivision_id !== null && !$user->isSuperadmin()) {
            $requestedId = $requestedSubdivisionId instanceof Subdivision
                ? $requestedSubdivisionId->id
                : (int) $requestedSubdivisionId;
            if ($user->subdivision_id !== $requestedId) {
                abort(403, 'You are only authorized to access records within your assigned subdivision.');
            }
        }

        return $next($request);
    }
}
