<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\SubdivisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $paystack_subaccount_code
 * @property string|null $payout_verified_code The code last confirmed with Paystack; payments need it to match.
 * @property string|null $payout_verified_mode "test" or "live": the key mode the code was confirmed under.
 * @property CarbonImmutable|null $payout_verified_at
 * @property CarbonImmutable|null $payout_checked_at
 * @property string|null $payout_check_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 */
class Subdivision extends Model
{
    use BelongsToOrganization;
    /** @use HasFactory<SubdivisionFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'paystack_subaccount_code',
    ];

    protected function casts(): array
    {
        return [
            'payout_verified_at' => 'datetime',
            'payout_checked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
