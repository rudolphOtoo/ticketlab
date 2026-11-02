<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\Organization;
use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves the single organisation (and its headline event) served when the platform runs in Dedicated mode.
 */
final readonly class DedicatedSite
{
    /**
     * @param Collection<int, Event> $events Every published, listed event: the featured one first, then by date.
     */
    public function __construct(
        public Organization $organization,
        public ?Event $event,
        public Collection $events = new Collection(),
    ) {}

    public static function resolve(): ?self
    {
        if (!PlatformSetting::isDedicatedMode()) {
            return null;
        }

        $slug = PlatformSetting::getHomeOrgSlug();
        $organization = $slug === null ? null : Organization::query()->where('slug', $slug)->first();

        if ($organization === null) {
            return null;
        }

        return self::forOrganization($organization);
    }

    /**
     * Publishing another event never hides the others: all stay listed, and the headline is chosen explicitly
     * (the organisation's featured event), else the next upcoming one, else the most recent. Drafts never show.
     */
    public static function forOrganization(Organization $organization): self
    {
        $published = Event::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'published')
            ->orderBy('start_at')
            ->get();

        // Only the headline event renders its programme, so only it pays for the relations.
        $featured = self::featured($organization, $published)?->load(['ticketTypes', 'speakers', 'schedules', 'faqs']);
        $listed = $published
            ->filter(static fn(Event $event): bool => $event->is_public || $event->is($featured))
            ->sortBy(static fn(Event $event): int => $event->is($featured) ? 0 : 1, SORT_NUMERIC)
            ->values();

        return new self($organization, $featured, $listed);
    }

    /**
     * The headline event's id without loading its programme, for admin screens.
     */
    public static function featuredEventId(Organization $organization): ?int
    {
        $published = Event::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'published')
            ->orderBy('start_at')
            ->get(['id', 'is_public', 'start_at', 'end_at']);

        return self::featured($organization, $published)?->id;
    }

    /**
     * @param Collection<int, Event> $published
     */
    private static function featured(Organization $organization, Collection $published): ?Event
    {
        $featuredId = $organization->settings['featured_event_id'] ?? null;
        $chosen = is_numeric($featuredId) ? $published->firstWhere('id', (int) $featuredId) : null;

        return (
            $chosen
            ?? $published->first(static fn(Event $event): bool => $event->is_public && $event->end_at->isFuture())
            ?? $published->where('is_public', true)->last()
            ?? $published->last()
        );
    }

    /**
     * @return array{organization: Organization, event: Event|null, events: Collection<int, Event>}
     */
    public function viewData(): array
    {
        return ['organization' => $this->organization, 'event' => $this->event, 'events' => $this->events];
    }
}
