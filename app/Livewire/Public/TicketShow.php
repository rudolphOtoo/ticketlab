<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Subdivision;
use App\Services\Payment\QrCodeService;
use App\Services\Zones\ZoneAssignment;
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

    public ?string $zoneMessage = null;

    public function mount(Order $order): void
    {
        $this->orderId = $order->id;
    }

    /**
     * Passes sold before a zone was required: the buyer picks their zone once, for every pass on the order.
     * The service refuses a second pick, so the choice is locked even against a replayed request.
     */
    public function chooseZone(int $zoneId, ZoneAssignment $assignment): void
    {
        $order = Order::query()->findOrFail($this->orderId);

        $this->zoneMessage = $assignment->chooseByBuyer($order, $zoneId)
            ? 'Saved. Your passes now work at this gate.'
            : 'Your passes already have a zone. Contact the organisers to change it.';
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
            'zoneChoices' => $this->zoneChoices($order, $event),
        ]);
    }

    /**
     * The organisation's zones with their venues, offered only while every pass on a paid order still has no zone.
     *
     * @return list<array{id: int, name: string, venue: ZoneVenue}>
     */
    private function zoneChoices(Order $order, ?Event $event): array
    {
        $needsZone =
            $event !== null
            && $order->payment_status === OrderStatus::Paid
            && $order->attendees->isNotEmpty()
            && $order->attendees->every(static fn(Attendee $pass): bool => $pass->subdivision_id === null);

        if (!$needsZone) {
            return [];
        }

        return array_values(
            Subdivision::query()
                ->where('organization_id', $order->organization_id)
                ->orderBy('name')
                ->get()
                ->map(static fn(Subdivision $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'venue' => ZoneVenue::for($event, $zone),
                ])
                ->all(),
        );
    }
}
