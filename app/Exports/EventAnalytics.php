<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Subdivision;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Headline figures for the analytics report, in the same scope as the roster it accompanies.
 *
 * Revenue follows the admin overview: paid orders at their gross amount, tied to the event through their passes.
 */
final readonly class EventAnalytics
{
    /**
     * @param list<array{zone: string, passes: int, revenue: float}> $zones
     */
    public function __construct(
        public float $revenue,
        public int $issued,
        public int $checkedIn,
        public array $zones,
    ) {}

    public static function for(AttendeeRoster $roster): self
    {
        $orgId = $roster->organizationId;
        $zoneId = $roster->zoneId;
        $eventId = $roster->eventId;

        $attendees = Attendee::query()
            ->where('organization_id', $orgId)
            ->when($zoneId, static fn(Builder $q, int $zone) => $q->where('subdivision_id', $zone))
            ->when($eventId, static fn(Builder $q, int $event) => $q->whereIn('ticket_type_id', TicketType::query()
                ->select('id')
                ->where('event_id', $event)));

        $orders = Order::query()
            ->where('organization_id', $orgId)
            ->where('payment_status', OrderStatus::Paid->value)
            ->when($zoneId, static fn(Builder $q, int $zone) => $q->where('subdivision_id', $zone))
            ->when($eventId, static fn(
                Builder $q,
                int $event,
            ) => $q->whereHas('attendees', static fn(Builder $passes) => $passes->whereIn('ticket_type_id', TicketType::query()
                ->select('id')
                ->where('event_id', $event))));

        /** @var array<int, int> $passesByZone */
        $passesByZone = (clone $attendees)
            ->toBase()
            ->selectRaw('coalesce(subdivision_id, 0) as zone, count(*) as aggregate')
            ->groupByRaw('coalesce(subdivision_id, 0)')
            ->pluck('aggregate', 'zone')
            ->map(static fn(mixed $count): int => (int) $count)
            ->all();

        /** @var array<int, float> $revenueByZone */
        $revenueByZone = (clone $orders)
            ->toBase()
            ->selectRaw('coalesce(subdivision_id, 0) as zone, sum(gross_amount) as aggregate')
            ->groupByRaw('coalesce(subdivision_id, 0)')
            ->pluck('aggregate', 'zone')
            ->map(static fn(mixed $sum): float => round((float) $sum, 2))
            ->all();

        return new self(
            revenue: round(array_sum($revenueByZone), 2),
            issued: array_sum($passesByZone),
            checkedIn: (clone $attendees)->whereNotNull('checked_in_at')->count(),
            zones: self::zoneRows($orgId, $zoneId, $passesByZone, $revenueByZone),
        );
    }

    public function pending(): int
    {
        return $this->issued - $this->checkedIn;
    }

    /**
     * Share of issued passes scanned in at the gate, from 0 to 1.
     */
    public function turnout(): float
    {
        return $this->issued === 0 ? 0.0 : round($this->checkedIn / $this->issued, 4);
    }

    /**
     * Every zone in scope (so quiet zones still appear on the chart), then sales with no zone, if any.
     *
     * @param array<int, int> $passes
     * @param array<int, float> $revenue
     *
     * @return list<array{zone: string, passes: int, revenue: float}>
     */
    private static function zoneRows(int $orgId, ?int $zoneId, array $passes, array $revenue): array
    {
        $rows = array_values(
            Subdivision::query()
                ->where('organization_id', $orgId)
                ->when($zoneId, static fn(Builder $q, int $zone) => $q->whereKey($zone))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn(Subdivision $zone): array => [
                    'zone' => $zone->name,
                    'passes' => $passes[$zone->id] ?? 0,
                    'revenue' => $revenue[$zone->id] ?? 0.0,
                ])
                ->all(),
        );

        // Sales with no zone are grouped under 0, which is never a real subdivision id.
        $unzonedPasses = $passes[0] ?? 0;
        $unzonedRevenue = $revenue[0] ?? 0.0;

        if ($rows === [] || $unzonedPasses > 0 || $unzonedRevenue > 0) {
            $rows[] = ['zone' => 'No zone', 'passes' => $unzonedPasses, 'revenue' => $unzonedRevenue];
        }

        return $rows;
    }
}
