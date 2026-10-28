<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\PayZephyrService;
use App\Services\Payment\PendingOrderService;
use App\Services\PaystackFeeCalculator;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.public')]
#[Title('Event Tickets')]
class EventShow extends Component
{
    #[Locked]
    public int $organizationId;

    #[Locked]
    public int $eventId;

    public ?int $ticketTypeId = null;

    public int $quantity = 1;

    public ?int $subdivisionId = null;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public bool $checkoutOpen = false;

    public ?string $orderReference = null;

    public ?string $flashMessage = null;

    public function mount(string $org_slug, string $event_slug): void
    {
        $organization = Organization::query()->where('slug', $org_slug)->firstOrFail();

        $event = Event::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $event_slug)
            ->where('status', 'published')
            ->firstOrFail();

        $this->organizationId = $organization->id;
        $this->eventId = $event->id;

        $defaultTicket = $event->ticketTypes()->where('available_quantity', '>', 0)->orderBy('price')->first();

        $this->ticketTypeId = $defaultTicket?->id;
        $this->subdivisionId = $organization->subdivisions()->orderBy('name')->value('id');
    }

    public function selectTicketType(int $ticketTypeId): void
    {
        $ticketType = $this->findTicketType($ticketTypeId);

        if ($ticketType === null) {
            $this->addError('ticketTypeId', 'Please choose a valid ticket tier.');

            return;
        }

        $this->ticketTypeId = $ticketType->id;
        $this->quantity = 1;
        $this->resetValidation();
    }

    public function incrementQuantity(): void
    {
        $ticketType = $this->selectedTicketType();

        if ($ticketType === null) {
            return;
        }

        if ($this->quantity >= $ticketType->available_quantity) {
            $this->addError('quantity', 'Only ' . $ticketType->available_quantity . ' ticket(s) remain for this tier.');

            return;
        }

        $this->quantity++;
        $this->resetValidation('quantity');
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
        $this->resetValidation('quantity');
    }

    public function updatedQuantity(mixed $value): void
    {
        $quantity = (int) $value;
        $ticketType = $this->selectedTicketType();
        $maximum = $ticketType === null ? 20 : $ticketType->available_quantity;
        $maximum = max(1, min(20, $maximum));

        $this->quantity = max(1, min($quantity, $maximum));
    }

    public function openCheckout(): void
    {
        if ($this->selectedTicketType() === null) {
            $this->addError('ticketTypeId', 'Select a ticket tier before continuing.');

            return;
        }

        $this->checkoutOpen = true;
        $this->resetValidation();
    }

    public function closeCheckout(): void
    {
        $this->checkoutOpen = false;
    }

    public function startCheckout(TicketReservationService $reservations, PendingOrderService $pendingOrders): void
    {
        $validated = $this->validate([
            'ticketTypeId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'subdivisionId' => ['nullable', 'integer'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'string', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50'],
        ]);

        $quantity = (int) $validated['quantity'];
        $organization = Organization::query()->findOrFail($this->organizationId);
        $ticketType = $this->findTicketType((int) $validated['ticketTypeId']);

        if ($ticketType === null || !$ticketType->isOnSale()) {
            $this->addError('ticketTypeId', 'The selected ticket tier is unavailable.');

            return;
        }

        try {
            $subdivision = $this->resolveSubdivision($organization, $validated['subdivisionId'] ?? null);
            $reservation = $reservations->holdOrReuse(
                $ticketType,
                $quantity,
                $this->checkoutSessionId(),
                $subdivision?->id,
            );
        } catch (TicketSoldOutException $exception) {
            $this->addError('quantity', $exception->getMessage());
            $this->dispatch('ticket-hold-failed');

            return;
        } catch (InvalidArgumentException $exception) {
            $this->addError('subdivisionId', $exception->getMessage());

            return;
        }

        try {
            $order = $pendingOrders->persist($reservation, $this->orderAttributes(
                $organization,
                $subdivision,
                $reservation,
                $ticketType,
                $validated,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('payment', $exception->getMessage());

            return;
        }

        $this->orderReference = $order->reference;
        $this->flashMessage = 'Your seats are held for 15 minutes. Complete payment to receive your QR pass.';

        $this->dispatch('open-paystack', payload: $this->paystackPayload($order, $ticketType, $subdivision, $quantity));
    }

    #[On('payment-completed')]
    public function paymentCompleted(string $reference): void
    {
        $order = Order::query()->where('reference', $reference)->first();

        if ($order === null) {
            $this->addError('payment', 'We could not locate that order.');

            return;
        }

        try {
            $payZephyr = app(PayZephyrService::class);
            $verification = $payZephyr->verify($reference);

            if ($payZephyr->isSuccessful($verification)) {
                app(OrderFulfillmentService::class)->fulfill($order, $verification);
            }
        } catch (Throwable $exception) {
            // The asynchronous Paystack webhook remains the fail-safe if the
            // browser loses connectivity during callback verification.
            Log::warning('Inline payment verification failed; deferring to the webhook.', [
                'order_reference' => $order->reference,
                'reason' => $exception->getMessage(),
            ]);
        }

        $this->redirect(route('public.tickets.show', ['order' => $order->reference]), navigate: true);
    }

    #[On('payment-cancelled')]
    public function paymentCancelled(): void
    {
        $this->flashMessage = 'Payment cancelled. Your seats remain reserved for 15 minutes.';
    }

    public function availabilityBadge(TicketType $ticketType): string
    {
        if ($ticketType->available_quantity <= 0) {
            return 'Sold Out';
        }

        $limitedThreshold = max(10, (int) ceil($ticketType->total_capacity * 0.2));

        return $ticketType->available_quantity <= $limitedThreshold ? 'Limited' : 'Available';
    }

    public function availabilityBadgeClass(TicketType $ticketType): string
    {
        return match ($this->availabilityBadge($ticketType)) {
            'Sold Out' => 'bg-zinc-200 text-zinc-600',
            'Limited' => 'bg-amber-100 text-amber-800',
            default => 'bg-emerald-100 text-emerald-800',
        };
    }

    public function render(): View
    {
        $event = $this->event();
        $organization = Organization::query()->findOrFail($this->organizationId);
        $ticketTypes = $event->ticketTypes()->orderBy('price')->get();

        $selectedTicketType = $ticketTypes->firstWhere('id', $this->ticketTypeId);

        $subtotal = $selectedTicketType === null ? 0.0 : round((float) $selectedTicketType->price * $this->quantity, 2);

        $breakdown = PaystackFeeCalculator::breakdown($subtotal, $this->platformFee($organization));

        return view('livewire.public.event-show', [
            'organization' => $organization,
            'event' => $event,
            'ticketTypes' => $ticketTypes,
            'subdivisions' => $organization->subdivisions()->orderBy('name')->get(),
            'selectedTicketType' => $selectedTicketType,
            'subtotal' => $subtotal,
            'breakdown' => $breakdown,
        ]);
    }

    private function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }

    private function findTicketType(int $ticketTypeId): ?TicketType
    {
        return TicketType::query()->where('event_id', $this->eventId)->whereKey($ticketTypeId)->first();
    }

    private function selectedTicketType(): ?TicketType
    {
        return $this->ticketTypeId === null ? null : $this->findTicketType($this->ticketTypeId);
    }

    /**
     * @throws InvalidArgumentException when the subdivision does not belong to the organisation
     */
    private function resolveSubdivision(Organization $organization, mixed $subdivisionId): ?Subdivision
    {
        if ($subdivisionId === null) {
            return null;
        }

        return (
            Subdivision::query()
                ->whereKey((int) $subdivisionId)
                ->where('organization_id', $organization->id)
                ->first() ?? throw new InvalidArgumentException(
                'Please select a valid ' . strtolower($organization->subdivision_label) . '.',
            )
        );
    }

    private function checkoutSessionId(): string
    {
        $sessionId = session()->getId();

        return $sessionId !== '' ? $sessionId : Str::uuid()->toString();
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    private function orderAttributes(
        Organization $organization,
        ?Subdivision $subdivision,
        Reservation $reservation,
        TicketType $ticketType,
        array $validated,
    ): array {
        $quantity = (int) $validated['quantity'];
        $breakdown = PaystackFeeCalculator::breakdown(
            round((float) $ticketType->price * $quantity, 2),
            $this->platformFee($organization),
        );

        return [
            'organization_id' => $organization->id,
            'subdivision_id' => $subdivision?->id,
            'reservation_id' => $reservation->getKey(),
            'customer_name' => $validated['customerName'],
            'customer_email' => $validated['customerEmail'],
            'customer_phone' => $validated['customerPhone'],
            'gross_amount' => $breakdown['gross'],
            'net_amount' => $breakdown['net'],
            'fee_amount' => $breakdown['processing_fee'],
            'currency' => 'GHS',
            'metadata' => [
                'reservation_id' => $reservation->getKey(),
                'ticket_type_id' => $ticketType->id,
                'ticket_type_name' => $ticketType->name,
                'quantity' => $quantity,
                'subdivision_name' => $subdivision?->name,
                'platform_fee' => $breakdown['platform_fee'],
                'reservation_expires_at' => $reservation->expires_at->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paystackPayload(
        Order $order,
        TicketType $ticketType,
        ?Subdivision $subdivision,
        int $quantity,
    ): array {
        return [
            'key' => app(PayZephyrService::class)->publicKey(),
            'email' => $order->customer_email,
            'amount' => $order->gross_pesewas,
            'currency' => $order->currency,
            'reference' => $order->reference,
            'subaccount' => $subdivision?->paystack_subaccount_code,
            'metadata' => [
                'order_reference' => $order->reference,
                'ticket_type' => $ticketType->name,
                'quantity' => $quantity,
                'organization_id' => $order->organization_id,
            ],
        ];
    }

    private function platformFee(Organization $organization): float
    {
        return max(0.0, (float) ($organization->settings['platform_fee'] ?? 0));
    }
}
