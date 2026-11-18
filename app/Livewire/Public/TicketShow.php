<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use App\Services\Payment\QrCodeService;
use App\Support\ZoneVenue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Your Ticket Pass')]
class TicketShow extends Component
{
    #[Locked]
    public int $orderId;

    public function mount(Order $order): void
    {
        $this->orderId = $order->id;
    }

    public function render(): View
    {
        $order = Order::query()
            ->with(['organization', 'attendees.ticketType.event.zones', 'attendees.subdivision', 'subdivision'])
            ->findOrFail($this->orderId);

        $organization = $order->organization;
        $attendees = $order->attendees;

        $qrCode = app(QrCodeService::class);

        $qrCodes = $attendees->mapWithKeys(static fn(Attendee $attendee): array => [
            (int) $attendee->id => $qrCode->svg($attendee->qr_code_hash, 240, 1),
        ]);

        return view('livewire.public.ticket-show', [
            'order' => $order,
            'organization' => $organization,
            'attendees' => $attendees,
            'qrCodes' => $qrCodes,
            'isPaid' => $order->payment_status === OrderStatus::Paid,
            'venue' => ($event = $attendees->first()?->ticketType->event) === null
                ? null
                : ZoneVenue::for($event, $order->subdivision),
        ]);
    }
}
