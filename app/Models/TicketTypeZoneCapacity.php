<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many seats of one ticket type one zone may sell.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $ticket_type_id
 * @property int $subdivision_id
 * @property int $capacity
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TicketType $ticketType
 * @property-read Subdivision $subdivision
 */
class TicketTypeZoneCapacity extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'ticket_type_id',
        'subdivision_id',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<Subdivision, $this>
     */
    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }
}
