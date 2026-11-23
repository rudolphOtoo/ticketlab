<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users of a suspended or archived organisation can't use TicketLab: they are signed out on their next request, however
 * they signed in (password, passkey, or a session from before the suspension), and the sign-in page says why. Scanner
 * and cash desk sessions end the same way. Platform super admins belong to no organisation and are never affected.
 */
class EnsureOrganizationIsActive
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user instanceof User && $user->organization_id !== null ? $user->organization : null;

        if ($organization === null || $organization->isActive()) {
            return $next($request);
        }

        $message = $organization->status->signInRefusal();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // A Livewire or API call can't follow a redirect to the sign-in page; it gets the message instead.
        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            abort(403, $message);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
