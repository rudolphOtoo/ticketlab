<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where (and optionally when) one zone of an event meets.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $subdivision_id
 * @property string|null $venue
 * @property string|null $address
 * @property string|null $map_url
 * @property CarbonImmutable|null $start_at
 * @property CarbonImmutable|null $end_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Event $event
 * @property-read Subdivision $subdivision
 */
class EventZone extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'event_id',
        'subdivision_id',
        'venue',
        'address',
        'map_url',
        'start_at',
        'end_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
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
     * @return BelongsTo<Subdivision, $this>
     */
    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }
}
