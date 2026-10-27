<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventSpeakerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSpeaker extends Model
{
    /** @use HasFactory<EventSpeakerFactory> */
    use HasFactory;

    protected static function newFactory(): EventSpeakerFactory
    {
        return EventSpeakerFactory::new();
    }

    use BelongsToOrganization;
    use HasFactory;

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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
