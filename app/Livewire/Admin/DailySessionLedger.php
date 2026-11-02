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
                'session_name' => 'Session Day 1 — ' . now()->format('M d, Y'),
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

    /**
     * Session ledgers visible to the current user. `activeSessionId` is client-writable,
     * so every lookup must go through this tenant scope.
     *
     * @return Builder<SessionLedger>
     */
    private function sessions(): Builder
    {
        return SessionLedger::query()->when($this->orgId(), static fn($q, $orgId) => $q->where(
            'organization_id',
            $orgId,
        ));
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

        $events = Event::query()->when($orgId, static fn($q, $orgId) => $q->where('organization_id', $orgId));

        return $this->eventId ? $events->find($this->eventId) : $events->latest()->first();
    }

    private function capacityTotal(?Event $event): int
    {
        return $event ? (int) TicketType::query()->where('event_id', $event->id)->sum('total_capacity') : 0;
    }

    /**
     * @return array{online: float, cash: float, checked_in: int}
     */
    private function ledgerTotals(?int $orgId): array
    {
        $paidOrders = Order::query()
            ->where('payment_status', OrderStatus::Paid->value)
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId));

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
                ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
                ->count(),
        ];
    }

    public function render(): View
    {
        $event = $this->getActiveEvent();
        $orgId = $this->orgId();

        $totals = $this->ledgerTotals($orgId);

        // Gate turnout and the live feed follow the active event; cash and online totals stay per daily session.
        $eventAttendees = Attendee::query()
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->when($event, static fn($q, Event $active) => $q->whereIn('ticket_type_id', TicketType::query()
                ->select('id')
                ->where('event_id', $active->id)));
        $turnoutCount = (clone $eventAttendees)->whereNotNull('checked_in_at')->count();

        $capacityTotal = $this->capacityTotal($event);
        if ($capacityTotal === 0) {
            $capacityTotal = max(1, (clone $eventAttendees)->count());
        }

        $turnoutRate = (int) round(($turnoutCount / $capacityTotal) * 100);

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
        ]);
    }
}
