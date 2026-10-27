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
use App\Services\Ledger\SessionReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.ops')]
#[Title('Financial Closeout & Attendance')]
class DailySessionLedger extends Component
{
    public ?int $eventId = null;

    public ?int $activeSessionId = null;

    public function mount(?int $eventId = null): void
    {
        $this->eventId = $eventId;

        $orgId = auth()->user()?->organization_id;

        $session = SessionLedger::query()
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->latest('opened_at')
            ->first();

        if (!$session) {
            $session = SessionLedger::create([
                'organization_id' => $orgId ?? 1,
                'session_name' => 'Session Day 1 — ' . now()->format('M d, Y'),
                'opened_at' => now(),
            ]);
        }

        $this->activeSessionId = $session->id;
    }

    public function lockSession(SessionReportService $reportService): void
    {
        $session = SessionLedger::find($this->activeSessionId);
        if (!$session || $session->isLocked()) {
            return;
        }

        $event = $this->getActiveEvent();
        $orgId = auth()->user()->organization_id ?? $session->organization_id;

        $totalOnline = (float) Order::where('payment_method', '!=', PaymentMethod::PhysicalCash->value)
            ->where('payment_status', OrderStatus::Paid->value)
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->sum('gross_amount');

        $totalCash = (float) Order::where('payment_method', PaymentMethod::PhysicalCash->value)
            ->where('payment_status', OrderStatus::Paid->value)
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->sum('gross_amount');

        $totalAttendees = Attendee::whereNotNull('checked_in_at')
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->count();

        $capacityTotal = (int) TicketType::when($event, static fn($q) => $q->where('event_id', $event->id))->sum(
            'total_capacity',
        );

        $subdivisions = Subdivision::when($orgId, static fn($q) => $q->where('organization_id', $orgId))->get();
        $zoneBreakdown = $subdivisions->map(static fn(Subdivision $sub) => [
            'name' => $sub->name,
            'code' => $sub->slug,
            'admitted' => Attendee::where('subdivision_id', $sub->id)->whereNotNull('checked_in_at')->count(),
        ])->toArray();

        DB::transaction(static function () use (
            $session,
            $totalOnline,
            $totalCash,
            $totalAttendees,
            $reportService,
            $event,
            $capacityTotal,
            $zoneBreakdown,
        ): void {
            $session->update([
                'locked_at' => now(),
                'locked_by' => auth()->id(),
                'total_online_momo' => $totalOnline,
                'total_cash' => $totalCash,
                'total_attendees' => $totalAttendees,
            ]);

            $reportService->generateReport($session, $event, $capacityTotal, $zoneBreakdown);
        });

        $this->dispatch('session-locked');
    }

    public function downloadPdfReport(SessionReportService $reportService): ?StreamedResponse
    {
        $session = SessionLedger::find($this->activeSessionId);
        if (!$session) {
            return null;
        }

        return $reportService->downloadReport($session);
    }

    private function getActiveEvent(): ?Event
    {
        return $this->eventId ? Event::find($this->eventId) : Event::latest()->first();
    }

    public function render(): View
    {
        $event = $this->getActiveEvent();
        $orgId = auth()->user()?->organization_id;

        $session = SessionLedger::find($this->activeSessionId);

        /** @var Collection<int, Subdivision> $subdivisions */
        $subdivisions = Subdivision::query()->when($orgId, static fn($q) => $q->where(
            'organization_id',
            $orgId,
        ))->get();

        // Calculate actual totals
        $totalOnlinePaystack = (float) Order::where('payment_method', '!=', PaymentMethod::PhysicalCash->value)
            ->where('payment_status', OrderStatus::Paid->value)
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->sum('gross_amount');

        $totalGateCash = (float) Order::where('payment_method', PaymentMethod::PhysicalCash->value)
            ->where('payment_status', OrderStatus::Paid->value)
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->sum('gross_amount');

        $grossRevenue = $totalOnlinePaystack + $totalGateCash;

        $turnoutCount = Attendee::whereNotNull('checked_in_at')
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->count();

        $capacityTotal = (int) TicketType::when($event, static fn($q) => $q->where('event_id', $event->id))->sum(
            'total_capacity',
        );
        if ($capacityTotal === 0) {
            $capacityTotal = max(1, Attendee::count());
        }

        $turnoutRate = $capacityTotal > 0 ? (int) round(($turnoutCount / $capacityTotal) * 100) : 0;

        $liveGateFeed = Attendee::with(['subdivision', 'ticketType'])
            ->whereNotNull('checked_in_at')
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->latest('checked_in_at')
            ->take(4)
            ->get();

        return view('livewire.admin.daily-session-ledger', [
            'event' => $event,
            'session' => $session,
            'subdivisions' => $subdivisions,
            'grossRevenue' => $grossRevenue,
            'totalOnlinePaystack' => $totalOnlinePaystack,
            'totalGateCash' => $totalGateCash,
            'turnoutCount' => $turnoutCount,
            'capacityTotal' => $capacityTotal,
            'turnoutRate' => $turnoutRate,
            'liveGateFeed' => $liveGateFeed,
        ]);
    }
}
