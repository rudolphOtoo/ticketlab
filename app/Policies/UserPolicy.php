<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isConnexionalAdmin() || $user->isZoneAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->isConnexionalAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $user->zone_id === $model->zone_id;
        }

        return $user->id === $model->id;
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
    public function update(User $user, User $model): bool
    {
        if ($user->isConnexionalAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $user->zone_id === $model->zone_id;
        }

        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->isConnexionalAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin() && $user->zone_id === $model->zone_id) {
            // Prevent zone admins from deleting other zone admins
            return !$model->isZoneAdmin();
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isConnexionalAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isConnexionalAdmin();
    }
}
