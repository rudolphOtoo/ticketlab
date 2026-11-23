<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\EventZone;
use App\Models\Subdivision;
use Carbon\CarbonImmutable;

/**
 * Where and when a pass holder attends: their zone's venue and schedule, falling back to the event's own.
 *
 * Every buyer-facing surface (event page, checkout, ticket page, PDF, email, scanner) reads venue and time
 * through this, so a Cape Coast pass never shows the Kumasi hall.
 */
final readonly class ZoneVenue
{
    public ?string $venue;

    public ?string $address;

    public ?string $mapUrl;

    public CarbonImmutable $startAt;

    public CarbonImmutable $endAt;

    private function __construct(
        public ?Subdivision $zone,
        Event $event,
        ?EventZone $details,
    ) {
        $this->venue = self::filled($details?->venue) ?? $event->venue;
        $this->address = self::filled($details?->address);
        $this->mapUrl = self::safeUrl($details?->map_url);
        $this->startAt = $details->start_at ?? $event->start_at;
        $this->endAt = $details->end_at ?? $details->start_at ?? $event->end_at;
    }

    public static function for(Event $event, ?Subdivision $zone): self
    {
        return new self($zone, $event, self::details($event, $zone));
    }

    /**
     * "Sat, 6 Mar 2027 · 10:00 AM": when this zone meets, as the event page shows it.
     */
    public function when(): string
    {
        return $this->startAt->format('D, j M Y · g:i A');
    }

    /**
     * "Wesley Girls Hall, 12 Castle Road, Cape Coast" style one-liner for passes and emails.
     */
    public function place(): ?string
    {
        $parts = array_values(array_filter([$this->venue, $this->address]));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function details(Event $event, ?Subdivision $zone): ?EventZone
    {
        if ($zone === null) {
            return null;
        }

        if ($event->relationLoaded('zones')) {
            return $event->zones->firstWhere('subdivision_id', $zone->id);
        }

        return EventZone::query()
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->where('subdivision_id', $zone->id)
            ->first();
    }

    private static function filled(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Map links are admin input rendered as hrefs, so only http(s) URLs are ever emitted.
     */
    private static function safeUrl(?string $url): ?string
    {
        $url = self::filled($url);

        return $url !== null && preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
