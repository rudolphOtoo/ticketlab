<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait ScopedToZone
{
    /**
     * Boot the trait.
     */
    protected static function bootScopedToZone(): void
    {
        static::addGlobalScope('zone', function (Builder $builder) {
            if (Auth::check()) {
                $user = Auth::user();

                // Connexional Admin can access all zones
                if (method_exists($user, 'canAccessAllZones') && $user->canAccessAllZones()) {
                    return;
                }

                // Zone Admin and Gate Usher can only access their assigned zone
                if ($user->zone_id) {
                    $builder->where($builder->getModel()->getTable().'.zone_id', $user->zone_id);
                } elseif (method_exists($user, 'isZoneAdmin') && $user->isZoneAdmin()) {
                    // Zone admins must have a zone_id
                    $builder->whereRaw('1 = 0');
                }
            }
        });
    }

    /**
     * Scope query to a specific zone (for administrative purposes).
     */
    public function scopeForZone(Builder $query, int $zoneId): Builder
    {
        return $query->withoutGlobalScope('zone')->where('zone_id', $zoneId);
    }

    /**
     * Scope query to all zones (bypass zone scoping).
     */
    public function scopeAllZones(Builder $query): Builder
    {
        return $query->withoutGlobalScope('zone');
    }
}
