<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
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
        'customization',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
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
}
