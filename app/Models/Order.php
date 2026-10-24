<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'subdivision_id',
        'user_id',
        'reference',
        'customer_name',
        'customer_email',
        'customer_phone',
        'gross_amount',
        'net_amount',
        'fee_amount',
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

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
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
