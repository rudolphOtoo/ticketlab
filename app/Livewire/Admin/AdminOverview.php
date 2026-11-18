<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Overview')]
class AdminOverview extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $event = ActiveEvent::resolve($user instanceof User ? $user->organization_id : null);
        $zoneId = $user instanceof User ? $user->subdivision_id : null;

        $stats = ['sold' => 0, 'capacity' => 0, 'checked_in' => 0, 'revenue' => 0.0];

        if ($event !== null) {
            $tierIds = TicketType::query()->select('id')->where('event_id', $event->id);
            $attendees = Attendee::query()
                ->where('organization_id', $event->organization_id)
                ->whereIn('ticket_type_id', $tierIds)
                ->when($zoneId, static fn($query, int $zone) => $query->where('subdivision_id', $zone));

            $stats = [
                'sold' => (clone $attendees)->count(),
                'capacity' => (int) TicketType::query()->where('event_id', $event->id)->sum('total_capacity'),
                'checked_in' => (clone $attendees)->whereNotNull('checked_in_at')->count(),
                'revenue' => (float) Order::query()
                    ->where('organization_id', $event->organization_id)
                    ->where('payment_status', OrderStatus::Paid->value)
                    ->when($zoneId, static fn($query, int $zone) => $query->where('subdivision_id', $zone))
                    ->whereHas('attendees', static fn($query) => $query->whereIn('ticket_type_id', $tierIds))
                    ->sum('gross_amount'),
            ];
        }

        return view('livewire.admin.admin-overview', [
            'user' => $user,
            'event' => $event,
            'stats' => $stats,
            // Organisation-wide admins get every zone side by side; a zone admin's figures above are already theirs.
            'zoneRows' =>
                $event !== null && $zoneId === null ? $this->zoneRows($event->organization_id, $event->id) : [],
        ]);
    }

    /**
     * Passes, check-ins and paid revenue per zone for one event, plus a "No zone" row only when such sales exist.
     *
     * @return list<array{name: string, sold: int, checked_in: int, revenue: float}>
     */
    private function zoneRows(int $organizationId, int $eventId): array
    {
        $tierIds = TicketType::query()->select('id')->where('event_id', $eventId);

        $passes = Attendee::query()
            ->toBase()
            ->where('organization_id', $organizationId)
            ->whereIn('ticket_type_id', $tierIds)
            ->selectRaw('subdivision_id, count(*) as sold')
            ->selectRaw('sum(case when checked_in_at is not null then 1 else 0 end) as checked_in')
            ->groupBy('subdivision_id')
            ->get()
            ->keyBy(static fn(object $row): string => (string) ($row->subdivision_id ?? 'none'));

        $revenue = Order::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', OrderStatus::Paid->value)
            ->whereHas('attendees', static fn($query) => $query->whereIn('ticket_type_id', $tierIds))
            ->toBase()
            ->selectRaw('subdivision_id, sum(gross_amount) as revenue')
            ->groupBy('subdivision_id')
            ->get()
            ->keyBy(static fn(object $row): string => (string) ($row->subdivision_id ?? 'none'));

        $row = static fn(string $key, string $name): array => [
            'name' => $name,
            'sold' => (int) ($passes->get($key)->sold ?? 0),
            'checked_in' => (int) ($passes->get($key)->checked_in ?? 0),
            'revenue' => round((float) ($revenue->get($key)->revenue ?? 0), 2),
        ];

        $rows = Subdivision::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn(Subdivision $zone): array => $row((string) $zone->id, $zone->name))
            ->all();

        if ($passes->has('none') || $revenue->has('none')) {
            $rows[] = $row('none', 'No zone');
        }

        return array_values($rows);
    }
}
