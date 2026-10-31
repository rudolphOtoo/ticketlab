<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;

/**
 * The admin's working event, chosen in the header switcher and shared by every admin and ops screen.
 *
 * The session only stores an id: it is always re-resolved inside the caller's organisation,
 * so a stale or tampered value can never surface another tenant's event.
 */
final class ActiveEvent
{
    public const string SESSION_KEY = 'admin_active_event_id';

    public static function resolve(?int $organizationId): ?Event
    {
        if ($organizationId === null) {
            return null;
        }

        $events = Event::query()->where('organization_id', $organizationId);
        $selectedId = session(self::SESSION_KEY);

        if (is_int($selectedId) || is_string($selectedId) && ctype_digit($selectedId)) {
            $selected = (clone $events)->whereKey((int) $selectedId)->first();

            if ($selected !== null) {
                return $selected;
            }
        }

        return $events->latest('id')->first();
    }

    public static function id(?int $organizationId): ?int
    {
        return self::resolve($organizationId)?->id;
    }

    public static function set(Event $event): void
    {
        session()->put(self::SESSION_KEY, $event->id);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
