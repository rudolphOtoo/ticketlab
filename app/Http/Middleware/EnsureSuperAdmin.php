<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Authentication required to access the Platform Super Admin Console.');
        }

        if (!in_array($user->role, ['super_admin', 'superadmin'], true)) {
            abort(403, 'Platform Super Admin clearance required.');
        }

        return $next($request);
    }
}
