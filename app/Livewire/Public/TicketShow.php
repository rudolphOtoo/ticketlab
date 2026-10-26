<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Payment\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
        /** @var Order $order */
        $order = Order::query()
            ->with(['attendees.ticketType.event', 'attendees.subdivision', 'subdivision'])
            ->findOrFail($this->orderId);

        $organization = Organization::query()->findOrFail($order->organization_id);

        /** @var Collection<int, Attendee> $attendees */
        $attendees = $order->attendees;

        $qrCode = app(QrCodeService::class);

        $qrCodes = $attendees->mapWithKeys(fn(Attendee $attendee): array => [
            $attendee->id => $qrCode->svg($attendee->qr_code_hash, 240, 1),
        ]);

        return view('livewire.public.ticket-show', [
            'order' => $order,
            'organization' => $organization,
            'attendees' => $attendees,
            'qrCodes' => $qrCodes,
            'isPaid' => $order->payment_status === OrderStatus::Paid,
        ]);
    }
}
