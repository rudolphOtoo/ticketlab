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
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Walk-In Cash Desk Register')]
class CashDeskTerminal extends Component
{
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
            $defaultTier = TicketType::where('event_id', $event->id)->first();
            if ($defaultTier) {
                $this->selectedTierId = $defaultTier->id;
                $this->cashTendered = (string) $defaultTier->price;
            }
        }

        $this->loadRecentCashSales();
    }

    public function loadRecentCashSales(): void
    {
        $orgId = auth()->user()?->organization_id;

        $orders = Order::with('attendees.ticketType')
            ->where('payment_method', PaymentMethod::PhysicalCash->value)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('paid_at')
            ->take(5)
            ->get();

        $this->recentCashSales = $orders->map(function (Order $order) {
            $tierName = $order->attendees->first()?->ticketType?->name ?? 'Gate Pass';
            $passCode = 'TKT-CW-'.strtoupper(substr(md5($order->reference), 0, 6));

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
        if (! $this->selectedTierId) {
            return 0.00;
        }

        $tier = TicketType::find($this->selectedTierId);

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

        if ($tendered < $total) {
            $this->addError('cashTendered', 'Tendered amount is less than total price.');

            return;
        }

        $event = $this->getActiveEvent();
        if (! $event) {
            $this->addError('tier', 'No active event found.');

            return;
        }

        $result = DB::transaction(function () use ($event, $total, $tendered) {
            $tier = TicketType::where('id', $this->selectedTierId)->lockForUpdate()->first();

            if (! $tier || $tier->available_quantity < $this->quantity) {
                return [
                    'success' => false,
                    'error' => 'Selected tier does not have enough remaining capacity.',
                ];
            }

            $tier->decrement('available_quantity', $this->quantity);

            $orgId = auth()->user()?->organization_id ?? $event->organization_id;
            $subdivisionId = auth()->user()?->subdivision_id;

            $customerName = trim($this->attendeeName) !== '' ? trim($this->attendeeName) : 'Walk-In Guest';
            $customerPhone = trim($this->attendeePhone) !== '' ? trim($this->attendeePhone) : 'N/A';
            $customerEmail = 'walkin_'.strtolower(bin2hex(random_bytes(4))).'@cashdesk.local';
            $reference = 'TL-ORD-CASH-'.strtoupper(bin2hex(random_bytes(4)));

            $order = Order::create([
                'organization_id' => $orgId,
                'subdivision_id' => $subdivisionId,
                'reference' => $reference,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'gross_amount' => $total,
                'net_amount' => $total,
                'fee_amount' => 0.00,
                'payment_method' => PaymentMethod::PhysicalCash->value,
                'payment_status' => OrderStatus::Paid->value,
                'paid_at' => now(),
            ]);

            $attendees = [];
            for ($i = 0; $i < $this->quantity; $i++) {
                $qrHash = hash('sha256', 'TL-PASS-'.$order->reference.'-'.($i + 1).'-'.bin2hex(random_bytes(4)));
                $attendees[] = Attendee::create([
                    'organization_id' => $orgId,
                    'order_id' => $order->id,
                    'subdivision_id' => $subdivisionId,
                    'ticket_type_id' => $tier->id,
                    'name' => $this->quantity > 1 ? $customerName.' (Pass '.($i + 1).')' : $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'qr_code_hash' => $qrHash,
                    'checked_in_at' => null,
                ]);
            }

            $passCode = 'TKT-CW-'.strtoupper(substr(md5($order->reference), 0, 6));

            return [
                'success' => true,
                'order' => $order,
                'pass' => [
                    'pass_code' => $passCode,
                    'attendee_name' => $customerName,
                    'attendee_phone' => $customerPhone,
                    'tier_name' => $tier->name,
                    'quantity' => $this->quantity,
                    'total_amount' => $total,
                    'cash_tendered' => $tendered,
                    'change_due' => $this->calculateChange(),
                    'issued_at' => now()->format('M d, Y · H:i:s'),
                    'qr_code_hash' => $attendees[0]->qr_code_hash,
                ],
            ];
        });

        if (! $result['success']) {
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

    private function getActiveEvent(): ?Event
    {
        return $this->eventId ? Event::find($this->eventId) : Event::latest()->first();
    }

    public function render(): View
    {
        $event = $this->getActiveEvent();
        /** @var Collection<int, TicketType> $tiers */
        $tiers = $event ? TicketType::where('event_id', $event->id)->get() : collect();

        return view('livewire.ops.cash-desk-terminal', [
            'event' => $event,
            'tiers' => $tiers,
            'total' => $this->calculateTotal(),
            'changeDue' => $this->calculateChange(),
        ]);
    }
}
