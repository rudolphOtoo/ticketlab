<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $subdivision_id
 * @property int|null $reservation_id
 * @property int|null $user_id
 * @property string $reference
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property string $gross_amount
 * @property string $net_amount
 * @property string $fee_amount
 * @property string $currency
 * @property PaymentMethod $payment_method
 * @property OrderStatus $payment_status
 * @property CarbonImmutable|null $paid_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int $gross_pesewas
 * @property-read Organization $organization
 * @property-read Subdivision|null $subdivision
 * @property-read Reservation|null $reservation
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Attendee> $attendees
 */
class Order extends Model
{
    use BelongsToOrganization;
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'subdivision_id',
        'reservation_id',
        'user_id',
        'reference',
        'customer_name',
        'customer_email',
        'customer_phone',
        'gross_amount',
        'net_amount',
        'fee_amount',
        'currency',
        'payment_method',
        'payment_status',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_status' => OrderStatus::class,
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return Attribute<int, never>
     */
    protected function grossPesewas(): Attribute
    {
        return Attribute::get(fn(): int => (int) round((float) $this->gross_amount * 100));
    }

    /**
     * @return BelongsTo<Subdivision, $this>
     */
    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }
}
