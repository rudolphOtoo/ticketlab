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
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'event_id',
        'name',
        'title_role',
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

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
