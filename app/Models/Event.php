<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\Media\ImagePreset;
use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $banner_path
 * @property string|null $venue
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property string $status
 * @property bool $is_public
 * @property array<string, mixed>|null $customization
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketType> $ticketTypes
 */
class Event extends Model
{
    use BelongsToOrganization;
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'description',
        'banner_path',
        'venue',
        'start_at',
        'end_at',
        'status',
        'is_public',
        'customization',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_public' => 'boolean',
            'customization' => 'array',
        ];
    }

    /**
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * @return HasMany<EventSpeaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(EventSpeaker::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(EventFaq::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventFormField, $this>
     */
    public function formFields(): HasMany
    {
        return $this->hasMany(EventFormField::class)->orderBy('sort_order');
    }

    /**
     * Per-zone venue and schedule for this event.
     *
     * @return HasMany<EventZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(EventZone::class);
    }

    /**
     * The banner's URL at a preset size ("hero" or "card"; Cloudinary scales it, never up). For a srcset, render
     * <x-media-image :value="$event->banner_path"> instead.
     */
    public function bannerUrl(ImagePreset|string $size = ImagePreset::Hero): ?string
    {
        return MediaUrl::resolve($this->banner_path, $size);
    }
}
