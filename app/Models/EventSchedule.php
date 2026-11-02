<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\EventScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<EventScheduleFactory> */
    use HasFactory;

    protected static function newFactory(): EventScheduleFactory
    {
        return EventScheduleFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'event_id',
        'day_label',
        'session_title',
        'title',
        'speaker_name',
        'venue_hall',
        'location',
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

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['session_title'] ?? null;
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['session_title'] = $value;
    }

    public function getLocationAttribute(): ?string
    {
        return $this->attributes['venue_hall'] ?? null;
    }

    public function setLocationAttribute(?string $value): void
    {
        $this->attributes['venue_hall'] = $value;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
