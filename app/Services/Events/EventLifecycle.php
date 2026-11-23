<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;

/**
 * An event's life after it has sold anything: it is archived (hidden, kept) rather than deleted, because its passes,
 * orders and payments are financial records. Only an event that never took an order, a seat hold or a pass can be
 * deleted. Every change is written to the organisation's audit trail.
 */
final class EventLifecycle
{
    public const string HAS_PASSES = "This event has sold passes, so it can't be deleted. Archive it to hide it, or cancel it if it won't take place.";

    public const string HAS_ORDERS = "This event has orders or seat holds, so it can't be deleted. Archive it to hide it, or cancel it if it won't take place.";

    /**
     * Why the event can't be deleted, or null when nothing was ever sold or held for it.
     */
    public function deleteBlocker(Event $event): ?string
    {
        $tiers = TicketType::query()->select('id')->where('event_id', $event->id);

        if (Attendee::query()->whereIn('ticket_type_id', $tiers)->exists()) {
            return self::HAS_PASSES;
        }

        // Every online order starts from a seat hold (kept after it expires or is paid), and a cash sale issues passes.
        return Reservation::query()->whereIn('ticket_type_id', $tiers)->exists() ? self::HAS_ORDERS : null;
    }

    /**
     * Leaves the public pages and stops selling. Passes, ticket pages, PDFs and reports keep working.
     */
    public function archive(Event $event, User $actor): void
    {
        if ($event->isArchived()) {
            return;
        }

        $event->forceFill(['archived_at' => now()])->save();
        $this->audit($event, $actor, 'event.archived');
    }

    /**
     * Back exactly as it was before archiving (published or draft).
     */
    public function unarchive(Event $event, User $actor): void
    {
        if (!$event->isArchived()) {
            return;
        }

        $event->forceFill(['archived_at' => null])->save();
        $this->audit($event, $actor, 'event.unarchived');
    }

    /**
     * Deletes an event that never sold or held anything, with its tiers and programme. Returns the reason instead when
     * it can't be deleted.
     */
    public function delete(Event $event, User $actor): ?string
    {
        return DB::transaction(function () use ($event, $actor): ?string {
            // Locked, so a checkout can't hold seats between the check and the delete.
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            TicketType::query()->where('event_id', $locked->id)->lockForUpdate()->get(['id']);
            $blocker = $this->deleteBlocker($locked);

            if ($blocker !== null) {
                return $blocker;
            }

            $wasActive = ActiveEvent::id($locked->organization_id) === $locked->id;
            $this->audit($locked, $actor, 'event.deleted', ['slug' => $locked->slug]);
            $locked->delete();

            // The next resolve falls back to (and pins) another event instead of the one just deleted.
            if ($wasActive) {
                ActiveEvent::forget();
            }

            DB::afterCommit(static fn() => MediaUrl::deleteStored($locked->banner_path));

            return null;
        });
    }

    /**
     * @param array<string, mixed> $details
     */
    private function audit(Event $event, User $actor, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'organization_id' => $event->organization_id,
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'event',
            'subject_id' => $event->id,
            'details' => ['title' => $event->title, ...$details],
        ]);
    }
}
