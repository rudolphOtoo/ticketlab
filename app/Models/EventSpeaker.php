<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use Database\Factories\EventSpeakerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property string $name
 * @property string|null $title_role
 * @property string|null $topic
 * @property string|null $bio
 * @property string|null $photo_path
 * @property int $sort_order
 * @property bool $is_featured
 * @property array<string, string>|null $social_links
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Event $event
 */
class EventSpeaker extends Model
{
    /** @use HasFactory<EventSpeakerFactory> */
    use HasFactory;

    protected static function newFactory(): EventSpeakerFactory
    {
        return EventSpeakerFactory::new();
    }

    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'event_id',
        'name',
        'title_role',
        'title',
        'organization',
        'topic',
        'bio',
        'photo_path',
        'sort_order',
        'is_featured',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'social_links' => 'array',
        ];
    }

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['title_role'] ?? null;
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title_role'] = $value;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function photoUrl(): ?string
    {
        return MediaUrl::resolve($this->photo_path);
    }
}
