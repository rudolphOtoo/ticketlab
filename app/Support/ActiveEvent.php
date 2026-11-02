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

    private const string MEMO_KEY = 'active_event.';

    public static function resolve(?int $organizationId): ?Event
    {
        if ($organizationId === null) {
            return null;
        }

        // Several components resolve the working event in one request; look it up once per request.
        $memo = request()->attributes;
        $memoKey = self::MEMO_KEY . $organizationId;

        if ($memo->has($memoKey)) {
            $memoized = $memo->get($memoKey);

            return $memoized instanceof Event ? $memoized : null;
        }

        $event = self::lookup($organizationId);
        $memo->set($memoKey, $event);

        return $event;
    }

    private static function lookup(int $organizationId): ?Event
    {
        $events = Event::query()->where('organization_id', $organizationId);
        $selectedId = session(self::SESSION_KEY);

        if (is_int($selectedId) || is_string($selectedId) && ctype_digit($selectedId)) {
            $selected = (clone $events)->whereKey((int) $selectedId)->first();

            if ($selected !== null) {
                return $selected;
            }
        }

        $fallback = $events->latest('id')->first();

        // Pin the fallback, so creating another event later never silently moves the admin off this one.
        if ($fallback !== null) {
            self::set($fallback);
        }

        return $fallback;
    }

    public static function id(?int $organizationId): ?int
    {
        return self::resolve($organizationId)?->id;
    }

    public static function set(Event $event): void
    {
        session()->put(self::SESSION_KEY, $event->id);
        request()->attributes->set(self::MEMO_KEY . $event->organization_id, $event);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);

        foreach (array_keys(request()->attributes->all()) as $key) {
            if (!str_starts_with((string) $key, self::MEMO_KEY)) {
                continue;
            }

            request()->attributes->remove((string) $key);
        }
    }
}
