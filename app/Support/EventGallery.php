<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * The organisation's curated gallery, where every image belongs to one event (or, with no event_id, to the
 * organisation as a whole). Items are stored in the bespoke settings JSON, so they are normalised on every read.
 *
 * @phpstan-type GalleryItem array{id: string, event_id: int|null, url: string, title: string, caption: string, tag: string}
 * @phpstan-type PublicGalleryItem array{id: string, event_id: int|null, url: string, src: string, title: string, caption: string, tag: string}
 */
final class EventGallery
{
    /**
     * Every well-formed image, whichever event owns it.
     *
     * @return list<GalleryItem>
     */
    public static function all(Organization $organization): array
    {
        $gallery = (array) ($organization->bespokeSettings()['gallery'] ?? []);

        return self::normalize((array) ($gallery['images'] ?? []));
    }

    /**
     * What visitors see for an event: its own images plus organisation-wide ones, with display-ready sources.
     *
     * @return list<PublicGalleryItem>
     */
    public static function forEvent(Organization $organization, ?Event $event): array
    {
        $items = [];

        foreach (self::all($organization) as $item) {
            if (!self::belongsTo($item, $event?->id)) {
                continue;
            }

            $items[] = [...$item, 'src' => (string) MediaUrl::resolve($item['url'])];
        }

        return $items;
    }

    /**
     * An image is shown for an event when that event owns it or it is shared organisation-wide.
     *
     * @param GalleryItem $item
     */
    public static function belongsTo(array $item, ?int $eventId): bool
    {
        return $item['event_id'] === null || $item['event_id'] === $eventId;
    }

    /**
     * Stored settings are untrusted JSON; keep only well-formed images, every field typed.
     *
     * @param array<array-key, mixed> $images
     *
     * @return list<GalleryItem>
     */
    public static function normalize(array $images): array
    {
        $normalized = [];

        foreach ($images as $image) {
            if (!is_array($image) || !is_string($image['url'] ?? null) || $image['url'] === '') {
                continue;
            }

            $field = static fn(string $key, string $default = ''): string => is_scalar($image[$key] ?? null)
                && (string) $image[$key] !== ''
                    ? (string) $image[$key]
                    : $default;
            $eventId = $image['event_id'] ?? null;

            $normalized[] = [
                'id' => $field('id', Str::uuid()->toString()),
                'event_id' => is_numeric($eventId) ? (int) $eventId : null,
                'url' => $image['url'],
                'title' => $field('title', 'Event Moment'),
                'caption' => $field('caption'),
                'tag' => $field('tag'),
            ];
        }

        return $normalized;
    }
}
