<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property string $day_label
 * @property string $session_title
 * @property string|null $speaker_name
 * @property string|null $venue_hall
 * @property string|null $description
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Event $event
 */
class EventSchedule extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'event_id',
        'day_label',
        'session_title',
        'speaker_name',
        'venue_hall',
        'description',
        'start_time',
        'end_time',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
