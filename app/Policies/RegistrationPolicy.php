<?php

namespace App\Policies;

use App\Models\User;

class RegistrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isConnexionalAdmin() || $user->isZoneAdmin() || $user->isGateUsher();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $registration): bool
    {
        if ($user->isConnexionalAdmin()) {
            return true;
        }

        if (isset($registration->zone_id)) {
            return $user->zone_id === $registration->zone_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isConnexionalAdmin() || $user->isZoneAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $registration): bool
    {
        if ($user->isConnexionalAdmin()) {
            return true;
        }

        if (isset($registration->zone_id)) {
            return $user->zone_id === $registration->zone_id && $user->isZoneAdmin();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $registration): bool
    {
        if ($user->isConnexionalAdmin()) {
            return true;
        }

        if (isset($registration->zone_id)) {
            return $user->zone_id === $registration->zone_id && $user->isZoneAdmin();
        }

        return false;
    }

    /**
     * Determine whether the user can scan/verify tickets.
     */
    public function scan(User $user): bool
    {
        return $user->isConnexionalAdmin() || $user->isZoneAdmin() || $user->isGateUsher();
    }
}
