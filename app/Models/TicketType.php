<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property int $total_capacity
 * @property int $available_quantity
 * @property CarbonImmutable|null $sales_start_at
 * @property CarbonImmutable|null $sales_end_at
 * @property list<string>|null $perks
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Event $event
 */
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    protected static function newFactory(): TicketTypeFactory
    {
        return TicketTypeFactory::new();
    }

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'total_capacity',
        'available_quantity',
        'sales_start_at',
        'sales_end_at',
        'perks',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'total_capacity' => 'integer',
            'available_quantity' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'perks' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * Whether the tier is active and inside its optional sales window.
     */
    public function isOnSale(): bool
    {
        $now = now();

        return (
            $this->is_active
            && ($this->sales_start_at === null || $this->sales_start_at->lte($now))
            && ($this->sales_end_at === null || $this->sales_end_at->gte($now))
        );
    }
}
