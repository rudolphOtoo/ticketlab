<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $role
 * @property int|null $organization_id
 * @property int|null $subdivision_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
// Privilege fields (role, organization_id, subdivision_id) are deliberately not fillable; see assignMembership().
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /** Roles a tenant admin may grant from the team screen (never super_admin). */
    public const array ASSIGNABLE_ROLES = ['admin', 'staff', 'usher'];

    public const array ADMIN_ROLES = ['admin', 'org_admin'];

    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /**
     * The only sanctioned way to grant a role and tenant scope: callers must pass trusted values explicitly.
     */
    public function assignMembership(string $role, ?int $organizationId, ?int $subdivisionId = null): static
    {
        $this->forceFill([
            'role' => $role,
            'organization_id' => $organizationId,
            'subdivision_id' => $subdivisionId,
        ])->save();

        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin' || $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin()
            || ($this->organization_id !== null && in_array($this->role, self::ADMIN_ROLES, true));
    }

    public function isStaff(): bool
    {
        return $this->organization_id !== null && in_array($this->role, ['staff', 'usher'], true);
    }

    /**
     * @deprecated Use isStaff() instead.
     */
    public function isUsher(): bool
    {
        return $this->isStaff();
    }

    /**
     * Tenancy is never initialised globally, so every staff surface must check
     * organisation membership explicitly instead of trusting the tenant scope.
     */
    public function hasOrganizationAccess(): bool
    {
        return $this->isSuperadmin() || $this->organization_id !== null;
    }

    /**
     * Organisation owners may manage their team; staff can run events but not grant access.
     */
    public function canManageTeam(): bool
    {
        return (
            $this->isSuperAdmin()
            || $this->organization_id !== null
            && in_array($this->role, self::ADMIN_ROLES, true)
        );
    }

    /**
     * The internal surface this account works from, or null for attendees (public surface only).
     */
    public function staffHomeUrl(): ?string
    {
        return match (true) {
            $this->isSuperAdmin() => route('platform.dashboard'),
            $this->organization_id === null => null,
            $this->isAdmin() => route('admin.index'),
            default => route('ops.scanner'),
        };
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Subdivision, $this>
     */
    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1 ? Str::substr($initials, 0, 1) . Str::substr($initials, -1) : $initials;
    }
}
