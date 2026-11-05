<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Ledger\SessionReportService;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.admin')]
#[Title('Financial Closeout & Attendance')]
class DailySessionLedger extends Component
{
    #[Locked]
    public ?int $eventId = null;

    public ?int $activeSessionId = null;

    public function mount(?int $eventId = null): void
    {
        $this->eventId = $eventId;

        $session = $this->sessions()->latest('opened_at')->first();

        if (!$session) {
            $orgId = $this->orgId() ?? $this->getActiveEvent()?->organization_id;

            if ($orgId === null) {
                return;
            }

            $session = SessionLedger::create([
                'organization_id' => $orgId,
                'subdivision_id' => $this->zoneId(),
                'session_name' => $this->sessionLabel() . 'Session Day 1 — ' . now()->format('M d, Y'),
                'opened_at' => now(),
            ]);
        }

        $this->activeSessionId = $session->id;
    }

    public function lockSession(SessionReportService $reportService): void
    {
        $event = $this->getActiveEvent();
        $capacityTotal = $this->capacityTotal($event);

        $locked = DB::transaction(function () use ($reportService, $event, $capacityTotal): bool {
            $session = $this->sessions()->whereKey($this->activeSessionId)->lockForUpdate()->first();

            // Re-checked under the row lock so two concurrent closeouts cannot both freeze figures.
            if (!$session || $session->isLocked()) {
                return false;
            }

            $orgId = $session->organization_id;
            $totals = $this->ledgerTotals($orgId);

            $zoneBreakdown = Subdivision::query()
                ->where('organization_id', $orgId)
                ->when($session->subdivision_id, static fn($q, int $zoneId) => $q->whereKey($zoneId))
                ->withCount(['attendees as admitted' => static fn($q) => $q->whereNotNull('checked_in_at')])
                ->get()
                ->map(static fn(Subdivision $sub) => [
                    'name' => $sub->name,
                    'code' => $sub->slug,
                    'admitted' => (int) $sub->getAttribute('admitted'),
                ])
                ->all();

            $session->update([
                'locked_at' => now(),
                'locked_by' => auth()->id(),
                'total_online_momo' => $totals['online'],
                'total_cash' => $totals['cash'],
                'total_attendees' => $totals['checked_in'],
            ]);

            $reportService->generateReport($session, $event, $capacityTotal, $zoneBreakdown);

            return true;
        });

        if ($locked) {
            $this->dispatch('session-locked');
        }
    }

    public function downloadPdfReport(SessionReportService $reportService): ?StreamedResponse
    {
        $session = $this->activeSession();
        if (!$session) {
            return null;
        }

        return $reportService->downloadReport($session);
    }

    private function orgId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->organization_id : null;
    }

    private function zoneId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->subdivision_id : null;
    }

    private function sessionLabel(): string
    {
        $zoneId = $this->zoneId();
        $zoneName = $zoneId === null ? null : Subdivision::query()->whereKey($zoneId)->value('name');

        return $zoneName === null ? '' : $zoneName . ' · ';
    }

    /**
     * Session ledgers visible to the current user. `activeSessionId` is client-writable,
     * so every lookup must go through this tenant scope. Zone admins keep their own zone
     * closeout; organisation-wide admins keep the organisation-wide one.
     *
     * @return Builder<SessionLedger>
     */
    private function sessions(): Builder
    {
        $zoneId = $this->zoneId();

        return SessionLedger::query()
            ->where('organization_id', $this->orgId())
            ->when(
                $zoneId,
                static fn($q, int $zone) => $q->where('subdivision_id', $zone),
                static fn($q) => $q->whereNull('subdivision_id'),
            );
    }

    private function activeSession(): ?SessionLedger
    {
        return $this->activeSessionId === null ? null : $this->sessions()->find($this->activeSessionId);
    }

    private function getActiveEvent(): ?Event
    {
        $orgId = $this->orgId();

        if ($this->eventId === null && $orgId !== null) {
            return ActiveEvent::resolve($orgId);
        }

        $events = Event::query()->where('organization_id', $orgId);

        return $this->eventId ? $events->find($this->eventId) : $events->latest()->first();
    }

    /**
     * Tier capacity is a pool shared by every zone, so a zone measures turnout against the passes issued to it.
     */
    private function capacityTotal(?Event $event): int
    {
        if ($event === null) {
            return 0;
        }

        $zoneId = $this->zoneId();
        $tierIds = TicketType::query()->select('id')->where('event_id', $event->id);

        return $zoneId === null
            ? (int) TicketType::query()->where('event_id', $event->id)->sum('total_capacity')
            : Attendee::query()->where('subdivision_id', $zoneId)->whereIn('ticket_type_id', $tierIds)->count();
    }

    /**
     * @return array{online: float, cash: float, checked_in: int}
     */
    private function ledgerTotals(?int $orgId): array
    {
        $zoneId = $this->zoneId();

        $paidOrders = Order::query()
            ->where('payment_status', OrderStatus::Paid->value)
            ->where('organization_id', $orgId)
            ->when($zoneId, static fn($q, int $zone) => $q->where('subdivision_id', $zone));

        return [
            'online' => (float) (clone $paidOrders)->where(
                'payment_method',
                '!=',
                PaymentMethod::PhysicalCash->value,
            )->sum('gross_amount'),
            'cash' => (float) (clone $paidOrders)->where('payment_method', PaymentMethod::PhysicalCash->value)->sum(
                'gross_amount',
            ),
            'checked_in' => Attendee::query()
                ->whereNotNull('checked_in_at')
                ->where('organization_id', $orgId)
                ->when($zoneId, static fn($q, int $zone) => $q->where('subdivision_id', $zone))
                ->count(),
        ];
    }

    /**
     * Per-zone revenue and turnout, inside the same tenant and zone scope as the headline totals so the rows
     * always add up to them. Organisation-wide admins also get a row for sales recorded without a zone.
     *
     * @param Builder<Attendee> $eventAttendees
     *
     * @return Collection<int, array{name: string, online: float, cash: float, net: float, online_orders: int, cash_orders: int, admitted: int, issued: int, turnout: int}>
     */
    private function zoneRows(?int $orgId, Builder $eventAttendees): Collection
    {
        $zoneId = $this->zoneId();
        $revenue = $this->zoneRevenue($orgId);
        $turnout = (clone $eventAttendees)
            ->toBase()
            ->groupBy('subdivision_id')
            ->select('subdivision_id')
            ->selectRaw('count(*) as issued')
            ->selectRaw('sum(case when checked_in_at is not null then 1 else 0 end) as admitted')
            ->get()
            ->keyBy(static fn(object $row): string => (string) ($row->subdivision_id ?? 'none'));

        $zones = Subdivision::query()
            ->where('organization_id', $orgId)
            ->when($zoneId, static fn($q, int $zone) => $q->whereKey($zone))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn(Subdivision $zone): array => ['key' => (string) $zone->id, 'name' => $zone->name])
            ->toBase();

        if ($zoneId === null && ($revenue->has('none') || $turnout->has('none'))) {
            $zones->push(['key' => 'none', 'name' => 'No zone']);
        }

        return $zones->map(static fn(array $zone): array => self::zoneRow(
            $zone['name'],
            $revenue->get($zone['key']),
            $turnout->get($zone['key']),
        ))->values();
    }

    /**
     * Paid online and cash takings per zone, keyed by zone id ('none' for sales without a zone).
     *
     * @return Collection<string, \stdClass>
     */
    private function zoneRevenue(?int $orgId): Collection
    {
        $zoneId = $this->zoneId();
        $cash = PaymentMethod::PhysicalCash->value;

        return Order::query()
            ->where('payment_status', OrderStatus::Paid->value)
            ->where('organization_id', $orgId)
            ->when($zoneId, static fn($q, int $zone) => $q->where('subdivision_id', $zone))
            ->toBase()
            ->groupBy('subdivision_id')
            ->select('subdivision_id')
            ->selectRaw('coalesce(sum(case when payment_method = ? then gross_amount else 0 end), 0) as cash', [$cash])
            ->selectRaw('coalesce(sum(case when payment_method != ? then gross_amount else 0 end), 0) as online', [
                $cash,
            ])
            ->selectRaw('sum(case when payment_method = ? then 1 else 0 end) as cash_orders', [$cash])
            ->selectRaw('sum(case when payment_method != ? then 1 else 0 end) as online_orders', [$cash])
            ->get()
            ->keyBy(static fn(object $row): string => (string) ($row->subdivision_id ?? 'none'));
    }

    /**
     * @return array{name: string, online: float, cash: float, net: float, online_orders: int, cash_orders: int, admitted: int, issued: int, turnout: int}
     */
    private static function zoneRow(string $name, ?\stdClass $sales, ?\stdClass $gate): array
    {
        $online = (float) ($sales->online ?? 0);
        $cash = (float) ($sales->cash ?? 0);
        $issued = (int) ($gate->issued ?? 0);
        $admitted = (int) ($gate->admitted ?? 0);

        return [
            'name' => $name,
            'online' => $online,
            'cash' => $cash,
            'net' => $online + $cash,
            'online_orders' => (int) ($sales->online_orders ?? 0),
            'cash_orders' => (int) ($sales->cash_orders ?? 0),
            'admitted' => $admitted,
            'issued' => $issued,
            'turnout' => $issued > 0 ? (int) round(($admitted / $issued) * 100) : 0,
        ];
    }

    public function render(): View
    {
        $event = $this->getActiveEvent();
        $orgId = $this->orgId();

        $totals = $this->ledgerTotals($orgId);

        // Gate turnout and the live feed follow the active event; cash and online totals stay per daily session.
        $eventAttendees = Attendee::query()
            ->where('organization_id', $orgId)
            ->when($this->zoneId(), static fn($q, int $zone) => $q->where('subdivision_id', $zone))
            ->when($event, static fn($q, Event $active) => $q->whereIn('ticket_type_id', TicketType::query()
                ->select('id')
                ->where('event_id', $active->id)));
        $turnoutCount = (clone $eventAttendees)->whereNotNull('checked_in_at')->count();

        $capacityTotal = $this->capacityTotal($event);
        if ($capacityTotal === 0) {
            $capacityTotal = max(1, (clone $eventAttendees)->count());
        }

        $turnoutRate = (int) round(($turnoutCount / $capacityTotal) * 100);

        $zoneRows = $this->zoneRows($orgId, $eventAttendees);

        $liveGateFeed = (clone $eventAttendees)
            ->with(['subdivision', 'ticketType'])
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')
            ->take(4)
            ->get();

        return view('livewire.admin.daily-session-ledger', [
            'event' => $event,
            'session' => $this->activeSession(),
            'grossRevenue' => $totals['online'] + $totals['cash'],
            'totalOnlinePaystack' => $totals['online'],
            'totalGateCash' => $totals['cash'],
            'turnoutCount' => $turnoutCount,
            'capacityTotal' => $capacityTotal,
            'turnoutRate' => $turnoutRate,
            'liveGateFeed' => $liveGateFeed,
            'zoneRows' => $zoneRows,
            'onlineOrderCount' => (int) $zoneRows->sum('online_orders'),
            'cashOrderCount' => (int) $zoneRows->sum('cash_orders'),
            'scopeLabel' => $this->zoneId() === null ? 'All zones' : $zoneRows->first()['name'] ?? 'Your zone',
        ]);
    }
}
