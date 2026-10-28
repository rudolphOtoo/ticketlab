<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Walk-In Cash Desk Register')]
class CashDeskTerminal extends Component
{
    #[Locked]
    public ?int $eventId = null;

    public ?int $selectedTierId = null;

    public int $quantity = 1;

    public string $attendeeName = '';

    public string $attendeePhone = '';

    public string $cashTendered = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $issuedPass = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $recentCashSales = [];

    public function mount(?int $eventId = null): void
    {
        $this->eventId = $eventId;

        $event = $this->getActiveEvent();
        if ($event) {
            $defaultTier = $event->ticketTypes()->first();
            if ($defaultTier) {
                $this->selectedTierId = $defaultTier->id;
                $this->cashTendered = $defaultTier->price;
            }
        }

        $this->loadRecentCashSales();
    }

    public function loadRecentCashSales(): void
    {
        $orgId = auth()->user()?->organization_id;

        $orders = Order::with('attendees.ticketType')
            ->where('payment_method', PaymentMethod::PhysicalCash->value)
            ->when($orgId, static fn($q) => $q->where('organization_id', $orgId))
            ->latest('paid_at')
            ->take(5)
            ->get();

        $this->recentCashSales = $orders->map(static function (Order $order) {
            $tierName = $order->attendees->first()?->ticketType->name ?? 'Gate Pass';
            $passCode = 'TKT-CW-' . strtoupper(substr(md5($order->reference), 0, 6));

            return [
                'pass_code' => $passCode,
                'name' => $order->customer_name,
                'tier' => $tierName,
                'qty' => $order->attendees->count() > 0 ? $order->attendees->count() : 1,
                'amount' => (float) $order->gross_amount,
                'time' => $order->paid_at ? $order->paid_at->diffForHumans() : 'Recently',
            ];
        })->toArray();
    }

    public function selectTier(int $tierId): void
    {
        $this->selectedTierId = $tierId;
        $this->updateTenderToExact();
    }

    public function incrementQuantity(): void
    {
        if ($this->quantity < 10) {
            $this->quantity++;
            $this->updateTenderToExact();
        }
    }

    public function decrementQuantity(): void
    {
        if ($this->quantity > 1) {
            $this->quantity--;
            $this->updateTenderToExact();
        }
    }

    public function setPresetTender(float $amount): void
    {
        $this->cashTendered = number_format($amount, 2, '.', '');
    }

    public function updateTenderToExact(): void
    {
        $total = $this->calculateTotal();
        $this->cashTendered = number_format($total, 2, '.', '');
    }

    public function calculateTotal(): float
    {
        $tierId = $this->selectedTierId;
        if ($tierId === null) {
            return 0.00;
        }

        $tier = $this->getActiveEvent()?->ticketTypes()->find($tierId);

        return $tier ? (float) $tier->price * $this->quantity : 0.00;
    }

    public function calculateChange(): float
    {
        $tendered = (float) $this->cashTendered;
        $total = $this->calculateTotal();

        return max(0.00, $tendered - $total);
    }

    public function processCashSale(): void
    {
        $total = $this->calculateTotal();
        $tendered = (float) $this->cashTendered;

        if ($this->selectedTierId === null) {
            $this->addError('tier', 'Please select a ticket tier.');

            return;
        }

        if ($this->quantity < 1 || $this->quantity > 100) {
            $this->addError('quantity', 'Quantity must be between 1 and 100 tickets.');

            return;
        }

        if ($tendered < $total) {
            $this->addError('cashTendered', 'Tendered amount is less than total price.');

            return;
        }

        $event = $this->getActiveEvent();
        if (!$event) {
            $this->addError('tier', 'No active event found.');

            return;
        }

        $result = DB::transaction(function () use ($event, $total, $tendered): array {
            // Pin the tier to the active event so a tampered tier id cannot sell another tenant's stock.
            $tier = TicketType::query()
                ->whereKey($this->selectedTierId)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if (!$tier || $tier->available_quantity < $this->quantity) {
                return [
                    'success' => false,
                    'error' => 'Selected tier does not have enough remaining capacity.',
                ];
            }

            $tier->decrement('available_quantity', $this->quantity);

            $order = $this->createCashOrder($event, $total);
            $firstPass = $this->issueAttendeePasses($order, $tier);

            return [
                'success' => true,
                'pass' => [
                    'pass_code' => 'TKT-CW-' . strtoupper(substr(md5($order->reference), 0, 6)),
                    'attendee_name' => $order->customer_name,
                    'attendee_phone' => $order->customer_phone,
                    'tier_name' => $tier->name,
                    'quantity' => $this->quantity,
                    'total_amount' => $total,
                    'cash_tendered' => $tendered,
                    'change_due' => $this->calculateChange(),
                    'issued_at' => now()->format('M d, Y · H:i:s'),
                    'qr_code_hash' => $firstPass->qr_code_hash,
                ],
            ];
        });

        if (!$result['success']) {
            $this->addError('tier', $result['error']);

            return;
        }

        $this->issuedPass = $result['pass'];
        $this->loadRecentCashSales();
        $this->dispatch('pass-issued');
    }

    public function closePassModal(): void
    {
        $this->issuedPass = null;
        $this->attendeeName = '';
        $this->attendeePhone = '';
        $this->quantity = 1;
        $this->updateTenderToExact();
        $this->resetErrorBag();
    }

    private function createCashOrder(Event $event, float $total): Order
    {
        $customerName = trim($this->attendeeName);
        $customerPhone = trim($this->attendeePhone);

        return Order::query()->create([
            'organization_id' => $event->organization_id,
            'subdivision_id' => auth()->user()?->subdivision_id,
            'reference' => 'TL-ORD-CASH-' . strtoupper(bin2hex(random_bytes(8))),
            'customer_name' => $customerName !== '' ? $customerName : 'Walk-In Guest',
            'customer_email' => 'walkin_' . bin2hex(random_bytes(4)) . '@cashdesk.local',
            'customer_phone' => $customerPhone !== '' ? $customerPhone : 'N/A',
            'gross_amount' => $total,
            'net_amount' => $total,
            'fee_amount' => 0.00,
            'payment_method' => PaymentMethod::PhysicalCash,
            'payment_status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Issue one QR pass per ticket and return the first, which is shown on the receipt.
     */
    private function issueAttendeePasses(Order $order, TicketType $tier): Attendee
    {
        $passes = collect(range(1, $this->quantity))->map(fn(int $number): Attendee => Attendee::query()->create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'subdivision_id' => $order->subdivision_id,
            'ticket_type_id' => $tier->id,
            'name' => $this->quantity > 1 ? $order->customer_name . ' (Pass ' . $number . ')' : $order->customer_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
            'qr_code_hash' => hash(
                'sha256',
                'TL-PASS-' . $order->reference . '-' . $number . '-' . bin2hex(random_bytes(4)),
            ),
            'checked_in_at' => null,
        ]));

        return $passes->firstOrFail();
    }

    private function getActiveEvent(): ?Event
    {
        $orgId = auth()->user()?->organization_id;

        $events = Event::query()->when($orgId, static fn($q) => $q->where('organization_id', $orgId));

        return $this->eventId ? $events->find($this->eventId) : $events->latest()->first();
    }

    public function render(): View
    {
        $event = $this->getActiveEvent();
        /** @var Collection<int, TicketType> $tiers */
        $tiers = $event ? $event->ticketTypes()->get() : collect();

        return view('livewire.ops.cash-desk-terminal', [
            'event' => $event,
            'tiers' => $tiers,
            'total' => $this->calculateTotal(),
            'changeDue' => $this->calculateChange(),
        ]);
    }
}
