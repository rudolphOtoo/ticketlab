<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

        // Mock recent transactions for terminal session ledger
        $this->recentCashSales = [
            [
                'pass_code' => 'TKT-CW-9B841A',
                'name' => 'Kweku Boateng',
                'tier' => 'VIP Gold Pass',
                'qty' => 1,
                'amount' => 120.00,
                'time' => '4m ago',
            ],
            [
                'pass_code' => 'TKT-CW-2A710F',
                'name' => 'Akosua Darko',
                'tier' => 'Regular Admission',
                'qty' => 2,
                'amount' => 100.00,
                'time' => '11m ago',
            ],
            [
                'pass_code' => 'TKT-CW-5D129C',
                'name' => 'Yaw Osei',
                'tier' => 'Regular Admission',
                'qty' => 1,
                'amount' => 50.00,
                'time' => '19m ago',
            ],
        ];
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
        if (!$this->selectedTierId) {
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

        $tier = TicketType::find($this->selectedTierId);
        $passCode = 'TKT-CW-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));

        $this->issuedPass = [
            'pass_code' => $passCode,
            'attendee_name' => trim($this->attendeeName) !== '' ? trim($this->attendeeName) : 'Walk-In Guest',
            'attendee_phone' => trim($this->attendeePhone) !== '' ? trim($this->attendeePhone) : 'N/A',
            'tier_name' => $tier ? $tier->name : 'Gate Pass',
            'quantity' => $this->quantity,
            'total_amount' => $total,
            'cash_tendered' => $tendered,
            'change_due' => $this->calculateChange(),
            'issued_at' => now()->format('M d, Y · H:i:s'),
        ];

        // Push to terminal recent session list
        array_unshift($this->recentCashSales, [
            'pass_code' => $passCode,
            'name' => $this->issuedPass['attendee_name'],
            'tier' => $this->issuedPass['tier_name'],
            'qty' => $this->quantity,
            'amount' => $total,
            'time' => 'Just now',
        ]);

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
