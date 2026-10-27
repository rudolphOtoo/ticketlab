<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }
}
