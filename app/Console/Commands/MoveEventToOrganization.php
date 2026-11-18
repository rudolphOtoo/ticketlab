<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\EventZone;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\TicketTypeZoneCapacity;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Moves an event that was created under the wrong organisation, with every row that belongs to it, to the right one.
 *
 * Dry run by default: it prints what would move and changes nothing. With --execute everything moves in one
 * transaction and is verified before commit. Each zone the event uses is mapped to the target organisation's zone
 * with the same name, or as given with --map="Old name=New name"; the command refuses if any zone cannot be mapped.
 * Money already settled is not touched: payout_subaccount_code still records where each payment went.
 */
class MoveEventToOrganization extends Command
{
    protected $signature = 'tenancy:move-event
        {event : Event id, or slug when it is unique}
        {--to-org= : Slug of the organisation the event belongs to}
        {--map=* : Zone mapping "Source zone=Target zone" (repeatable); unmapped zones match by name}
        {--with-ledgers : Also move the source organisation\'s session ledgers (only when this is its only event with passes)}
        {--execute : Actually move (otherwise a dry run that changes nothing)}';

    protected $description = 'Move an event and all of its rows to another organisation (dry run by default)';

    /** @var array<string, class-string<Model>> Organisation-scoped rows keyed by the label printed in the table. */
    private const array TENANT_TABLES = [
        'event_zones' => EventZone::class,
        'ticket_type_zone_capacities' => TicketTypeZoneCapacity::class,
        'reservations' => Reservation::class,
        'orders' => Order::class,
        'attendees' => Attendee::class,
        'event_speakers' => EventSpeaker::class,
        'event_schedules' => EventSchedule::class,
        'event_faqs' => EventFaq::class,
        'event_form_fields' => EventFormField::class,
        'broadcasts' => Broadcast::class,
        'audit_logs' => AuditLog::class,
        'session_ledgers' => SessionLedger::class,
    ];

    /** Rows that carry a zone, which is remapped to the target organisation's zone. */
    private const array ZONED_TABLES = [
        'event_zones',
        'ticket_type_zone_capacities',
        'reservations',
        'orders',
        'attendees',
        'session_ledgers',
    ];

    public function handle(): int
    {
        try {
            $plan = $this->plan();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        [$event, $source, $target] = [$plan['event'], $plan['source'], $plan['target']];

        $this->info(
            "Event #{$event->id} \"{$event->title}\": {$source->name} ({$source->slug}) → {$target->name} ({$target->slug})",
        );
        $this->printZoneMap($plan['zones']);
        $this->printCounts('Before', $plan['ids'], $source, $target);

        if (!$this->option('execute')) {
            $this->warn('Dry run: nothing was changed. Re-run with --execute to move these rows.');

            return self::SUCCESS;
        }

        $this->apply($plan);
        $this->printCounts('After', $plan['ids'], $source, $target);
        $this->info('Moved. Every row now belongs to ' . $target->name . '.');

        return self::SUCCESS;
    }

    /**
     * Everything the move needs, validated; throws (and changes nothing) when the move cannot be done safely.
     *
     * @return array{event: Event, source: Organization, target: Organization, ids: array<string, list<int>>, zones: array<int, int>}
     */
    private function plan(): array
    {
        $event = $this->event(self::text($this->argument('event')));
        $source = Organization::query()->findOrFail($event->organization_id);
        $target = $this->target($source);
        $this->assertSlugFree($event, $target);
        $ids = $this->rowIds($event, $source);

        return [
            'event' => $event,
            'source' => $source,
            'target' => $target,
            'ids' => $ids,
            'zones' => $this->zoneMap($ids, $source, $target),
        ];
    }

    /**
     * @param array{event: Event, source: Organization, target: Organization, ids: array<string, list<int>>, zones: array<int, int>} $plan
     */
    private function apply(array $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $this->move($plan['event'], $plan['target'], $plan['ids'], $plan['zones']);
            $this->assertNothingLeftBehind($plan['event'], $plan['source'], $plan['ids']);
            $this->unfeature($plan['event'], $plan['source']);
        });

        Log::warning('Event moved to another organisation.', [
            'event_id' => $plan['event']->id,
            'from_organization_id' => $plan['source']->id,
            'to_organization_id' => $plan['target']->id,
            'zone_map' => $plan['zones'],
        ]);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function event(string $key): Event
    {
        if (ctype_digit($key)) {
            return Event::query()->find((int) $key) ?? throw new RuntimeException("No event with id {$key}.");
        }

        $matches = Event::query()->where('slug', $key)->get();

        return match ($matches->count()) {
            0 => throw new RuntimeException("No event with slug \"{$key}\"."),
            1 => $matches->firstOrFail(),
            default => throw new RuntimeException("Several events use the slug \"{$key}\"; pass the event id instead."),
        };
    }

    private function target(Organization $source): Organization
    {
        $slug = self::text($this->option('to-org'));
        $target = $slug === '' ? null : Organization::query()->where('slug', $slug)->first();

        if ($target === null) {
            throw new RuntimeException(
                'Pass --to-org with the slug of an existing organisation. Onboard it first if needed.',
            );
        }

        if ($target->is($source)) {
            throw new RuntimeException('The event already belongs to ' . $target->slug . '.');
        }

        return $target;
    }

    private function assertSlugFree(Event $event, Organization $target): void
    {
        if (Event::query()->where('organization_id', $target->id)->where('slug', $event->slug)->exists()) {
            throw new RuntimeException(
                "{$target->slug} already has an event with the slug \"{$event->slug}\". Rename one first.",
            );
        }
    }

    /**
     * The ids of every row that belongs to the event, per table. Orders are found through their passes and their
     * seat holds, so pending and paid orders both move.
     *
     * @return array<string, list<int>>
     */
    private function rowIds(Event $event, Organization $source): array
    {
        $tierIds = self::ids(TicketType::query()->where('event_id', $event->id));
        $byEvent = static fn(string $model): array => self::ids($model::query()->where('event_id', $event->id));
        $byTier = static fn(string $model): array => self::ids($model::query()->whereIn('ticket_type_id', $tierIds));

        $reservations = $byTier(Reservation::class);
        $attendees = $byTier(Attendee::class);
        $orders = self::ids(Order::query()
            ->where('organization_id', $source->id)
            ->where(
                static fn(Builder $query) => $query->whereIn(
                    'id',
                    Attendee::query()->whereIn('ticket_type_id', $tierIds)->select('order_id'),
                )->orWhereIn('reservation_id', $reservations),
            ));

        return [
            'ticket_types' => $tierIds,
            'event_zones' => $byEvent(EventZone::class),
            'ticket_type_zone_capacities' => $byTier(TicketTypeZoneCapacity::class),
            'reservations' => $reservations,
            'orders' => $orders,
            'attendees' => $attendees,
            'event_speakers' => $byEvent(EventSpeaker::class),
            'event_schedules' => $byEvent(EventSchedule::class),
            'event_faqs' => $byEvent(EventFaq::class),
            'event_form_fields' => $byEvent(EventFormField::class),
            'broadcasts' => $byEvent(Broadcast::class),
            'audit_logs' => self::ids(
                AuditLog::query()
                    ->where('organization_id', $source->id)
                    ->where('subject_type', 'attendee')
                    ->whereIn('subject_id', $attendees),
            ),
            'session_ledgers' => $this->ledgerIds($event, $source),
        ];
    }

    /**
     * Ledgers belong to an organisation and a day, not to an event, so they only move on request and only when this
     * is the source organisation's only event with passes (otherwise another event's cash would move with them).
     *
     * @return list<int>
     */
    private function ledgerIds(Event $event, Organization $source): array
    {
        $ledgers = self::ids(SessionLedger::query()->where('organization_id', $source->id));

        if ($ledgers === []) {
            return [];
        }

        if (!$this->option('with-ledgers')) {
            $this->warn(
                count($ledgers)
                . " session ledger(s) of {$source->slug} are not linked to an event and stay put. "
                . 'Pass --with-ledgers to move them too.',
            );

            return [];
        }

        $otherSales = Attendee::query()
            ->where('organization_id', $source->id)
            ->whereNotIn('ticket_type_id', TicketType::query()->where('event_id', $event->id)->select('id'))
            ->exists();

        if ($otherSales) {
            throw new RuntimeException(
                "--with-ledgers refused: {$source->slug} has passes for other events, so its ledgers cannot be attributed to this one.",
            );
        }

        return $ledgers;
    }

    /**
     * Every zone the moving rows use, mapped to a zone of the target organisation. Refuses unmapped zones, and two
     * source zones landing on one target zone (that would merge their per-zone settings).
     *
     * @param array<string, list<int>> $ids
     *
     * @return array<int, int> source zone id => target zone id
     */
    private function zoneMap(array $ids, Organization $source, Organization $target): array
    {
        $used = collect(self::ZONED_TABLES)
            ->flatMap(
                static fn(string $table) => self::TENANT_TABLES[$table]::query()
                    ->whereKey($ids[$table])
                    ->whereNotNull('subdivision_id')
                    ->distinct()
                    ->pluck('subdivision_id'),
            )
            ->unique()
            ->values();

        $sourceZones = Subdivision::query()->whereKey($used)->get()->keyBy('id');
        $targetZones = Subdivision::query()->where('organization_id', $target->id)->get();
        $explicit = $this->explicitMap();
        $map = [];
        $unmapped = [];

        foreach ($used as $zoneId) {
            $zone = $sourceZones->get($zoneId);

            if ($zone === null || $zone->organization_id !== $source->id) {
                throw new RuntimeException(
                    "Zone #{$zoneId} used by this event does not belong to {$source->slug}; fix the data first.",
                );
            }

            $wanted = mb_strtolower($explicit[mb_strtolower($zone->name)] ?? $zone->name);
            $match = $targetZones->first(
                static fn(Subdivision $candidate): bool => mb_strtolower($candidate->name) === $wanted,
            );

            if ($match === null) {
                $unmapped[] = $zone->name;

                continue;
            }

            $map[$zone->id] = $match->id;
        }

        if ($unmapped !== []) {
            throw new RuntimeException(
                'Cannot map these zones to '
                . $target->slug
                . ': '
                . implode(', ', $unmapped)
                . '. Create them there, or pass --map="Source zone=Target zone".',
            );
        }

        if (count(array_unique($map)) !== count($map)) {
            throw new RuntimeException(
                'Two source zones map to the same target zone; map each zone to a different one.',
            );
        }

        return $map;
    }

    /**
     * @return array<string, string> lower-cased source zone name => target zone name
     */
    private function explicitMap(): array
    {
        $map = [];

        foreach ((array) $this->option('map') as $pair) {
            $parts = explode('=', (string) $pair, 2);

            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new RuntimeException("Invalid --map \"{$pair}\"; use --map=\"Source zone=Target zone\".");
            }

            $map[mb_strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        return $map;
    }

    /**
     * @param array<string, list<int>> $ids
     * @param array<int, int> $zoneMap
     */
    private function move(Event $event, Organization $target, array $ids, array $zoneMap): void
    {
        Event::query()->whereKey($event->id)->update(['organization_id' => $target->id]);

        foreach (self::TENANT_TABLES as $table => $model) {
            if ($ids[$table] === []) {
                continue;
            }

            $model::query()->whereKey($ids[$table])->update(['organization_id' => $target->id]);

            if (!in_array($table, self::ZONED_TABLES, true)) {
                continue;
            }

            foreach ($zoneMap as $from => $to) {
                $model::query()
                    ->whereKey($ids[$table])
                    ->where('subdivision_id', $from)
                    ->update(['subdivision_id' => $to]);
            }
        }
    }

    /**
     * @param array<string, list<int>> $ids
     */
    private function assertNothingLeftBehind(Event $event, Organization $source, array $ids): void
    {
        $left = Event::query()->whereKey($event->id)->where('organization_id', $source->id)->count();

        foreach (self::TENANT_TABLES as $table => $model) {
            $left += $model::query()->whereKey($ids[$table])->where('organization_id', $source->id)->count();
        }

        if ($left > 0) {
            throw new RuntimeException("{$left} row(s) still point at {$source->slug}; the move was rolled back.");
        }
    }

    private function unfeature(Event $event, Organization $source): void
    {
        $settings = $source->fresh()->settings ?? [];

        if (($settings['featured_event_id'] ?? null) === $event->id) {
            unset($settings['featured_event_id']);
            $source->forceFill(['settings' => $settings])->save();
        }
    }

    /**
     * @param array<int, int> $zoneMap
     */
    private function printZoneMap(array $zoneMap): void
    {
        $names = Subdivision::query()
            ->whereKey([...array_keys($zoneMap), ...array_values($zoneMap)])
            ->pluck('name', 'id');

        $this->table(
            ['Source zone', 'Target zone'],
            array_map(
                static fn(int $from, int $to): array => [$names[$from] ?? "#{$from}", $names[$to] ?? "#{$to}"],
                array_keys($zoneMap),
                array_values($zoneMap),
            ),
        );
    }

    /**
     * @param array<string, list<int>> $ids
     */
    private function printCounts(string $when, array $ids, Organization $source, Organization $target): void
    {
        $rows = [['events', 1, '']];

        foreach (['ticket_types', ...array_keys(self::TENANT_TABLES)] as $table) {
            $model = $table === 'ticket_types' ? null : self::TENANT_TABLES[$table];
            $rows[] = [
                $table,
                count($ids[$table]),
                $model === null
                    ? '(follow the event)'
                    : $model::query()->whereKey($ids[$table])->where('organization_id', $source->id)->count()
                    . ' under '
                    . $source->slug
                    . ' / '
                    . $model::query()->whereKey($ids[$table])->where('organization_id', $target->id)->count()
                    . ' under '
                    . $target->slug,
            ];
        }

        $this->line("{$when}:");
        $this->table(['Table', 'Rows', 'Owner'], $rows);
    }

    /**
     * @param Builder<covariant Model> $query
     *
     * @return list<int>
     */
    private static function ids(Builder $query): array
    {
        return array_values(array_map(intval(...), $query->pluck('id')->all()));
    }
}
