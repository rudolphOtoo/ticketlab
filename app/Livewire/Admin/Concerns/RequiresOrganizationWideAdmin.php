<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Screens that change what every zone shares (events, ticket types and prices, the public site, the zone list).
 *
 * Livewire calls bootRequiresOrganizationWideAdmin() on every request, both the first page load and every later
 * action call, so a zone admin is refused even when they send a hand-built Livewire request instead of using the page.
 */
trait RequiresOrganizationWideAdmin
{
    public function bootRequiresOrganizationWideAdmin(): void
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            return;
        }

        // Fail closed: an in-memory user without its zone columns is reloaded, never treated as organisation-wide.
        $attributes = $user->getAttributes();
        if (!array_key_exists('subdivision_id', $attributes) || !array_key_exists('role', $attributes)) {
            $user->refresh();
        }

        if (!$user->isSuperAdmin() && $user->subdivision_id !== null) {
            abort(
                403,
                'Only organisation admins can change this. Your account manages the '
                . ($user->subdivision->name ?? 'one')
                . ' '
                . strtolower($user->zoneLabel())
                . ' only.',
            );
        }
    }
}
