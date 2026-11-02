<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
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

        $stats = ['sold' => 0, 'capacity' => 0, 'checked_in' => 0, 'revenue' => 0.0];

        if ($event !== null) {
            $tierIds = TicketType::query()->select('id')->where('event_id', $event->id);
            $attendees = Attendee::query()
                ->where('organization_id', $event->organization_id)
                ->whereIn('ticket_type_id', $tierIds);

            $stats = [
                'sold' => (clone $attendees)->count(),
                'capacity' => (int) TicketType::query()->where('event_id', $event->id)->sum('total_capacity'),
                'checked_in' => (clone $attendees)->whereNotNull('checked_in_at')->count(),
                'revenue' => (float) Order::query()
                    ->where('organization_id', $event->organization_id)
                    ->where('payment_status', OrderStatus::Paid->value)
                    ->whereHas('attendees', static fn($query) => $query->whereIn('ticket_type_id', $tierIds))
                    ->sum('gross_amount'),
            ];
        }

        return view('livewire.admin.admin-overview', [
            'user' => $user,
            'event' => $event,
            'stats' => $stats,
        ]);
    }
}
