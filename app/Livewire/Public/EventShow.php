<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\PayZephyrService;
use App\Services\PaystackFeeCalculator;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

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

    /**
     * @var array<string, mixed>
     */
    public array $formResponses = [];

    public string $faqSearch = '';

    public ?int $activeFaqId = null;

    public function mount(string $org_slug, string $event_slug): void
    {
        $organization = Organization::query()->where('slug', $org_slug)->firstOrFail();

        $event = Event::query()
            ->with([
                'organization',
                'speakers' => fn ($q) => $q->orderBy('sort_order'),
                'schedules' => fn ($q) => $q->orderBy('sort_order'),
                'faqs' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order'),
                'formFields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'ticketTypes',
            ])
            ->where('organization_id', $organization->id)
            ->where('slug', $event_slug)
            ->where('status', 'published')
            ->firstOrFail();

        $this->organizationId = $organization->id;
        $this->eventId = $event->id;

        $defaultTicket = $event->ticketTypes
            ->filter(fn ($ticket) => (int) ($ticket->available_quantity ?? 0) > 0)
            ->sortBy('price')
            ->first();

        $this->ticketTypeId = $defaultTicket?->id;
        $this->subdivisionId = $organization->subdivisions()->orderBy('name')->value('id');

        // Initialize dynamic form responses
        $formFields = $event->formFields;
        if ($formFields && $formFields->isNotEmpty()) {
            foreach ($formFields as $field) {
                if (! array_key_exists($field->field_key, $this->formResponses)) {
                    $this->formResponses[$field->field_key] = '';
                }
            }
        }
    }

    public function selectTicketType(int $ticketTypeId): void
    {
        $ticketType = $this->event()->ticketTypes()->whereKey($ticketTypeId)->first();

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
        $maximum = $ticketType?->available_quantity ?? 20;
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

    public function startCheckout(TicketReservationService $reservations): void
    {
        $event = $this->event();
        if (! $event->relationLoaded('formFields')) {
            $event->load(['formFields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);
        }

        // Sanitize form responses
        if (is_array($this->formResponses)) {
            $sanitized = [];
            foreach ($this->formResponses as $key => $value) {
                if (is_string($value)) {
                    $sanitized[$key] = trim(strip_tags($value));
                } elseif (is_array($value)) {
                    $sanitized[$key] = array_map(function ($v) {
                        return is_string($v) ? trim(strip_tags($v)) : $v;
                    }, $value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            $this->formResponses = $sanitized;
        }

        $rules = [
            'ticketTypeId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'subdivisionId' => ['nullable', 'integer'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'string', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50'],
        ];

        // Validate dynamic form fields
        $formFields = $event->formFields;
        if ($formFields && $formFields->isNotEmpty()) {
            $allowedKeys = [];
            foreach ($formFields as $field) {
                if (! $field->is_active) {
                    continue;
                }
                $allowedKeys[] = $field->field_key;
                $fieldRules = is_array($field->validation_rules) ? $field->validation_rules : [];
                $rules["formResponses.{$field->field_key}"] = [];

                if ($field->input_type === 'email') {
                    $rules["formResponses.{$field->field_key}"][] = 'email';
                } elseif ($field->input_type === 'number') {
                    $rules["formResponses.{$field->field_key}"][] = 'numeric';
                } elseif (in_array($field->input_type, ['select', 'radio'], true) && is_array($field->options)) {
                    $options = array_values(array_filter($field->options));
                    if (! empty($options)) {
                        $rules["formResponses.{$field->field_key}"][] = 'in:'.implode(',', $options);
                    }
                }

                if ($field->is_required) {
                    $rules["formResponses.{$field->field_key}"][] = 'required';
                }

                if (! empty($fieldRules)) {
                    // Merge provided rules but ensure basic type constraints are not bypassed
                    foreach ($fieldRules as $rule) {
                        if (is_string($rule) && ! in_array($rule, $rules["formResponses.{$field->field_key}"], true)) {
                            $rules["formResponses.{$field->field_key}"][] = $rule;
                        }
                    }
                }

                if (empty($rules["formResponses.{$field->field_key}"])) {
                    unset($rules["formResponses.{$field->field_key}"]);
                }
            }

            // Prevent arbitrary payload injection by only accepting known keys
            if (is_array($this->formResponses)) {
                $filtered = [];
                foreach ($this->formResponses as $key => $value) {
                    if (in_array($key, $allowedKeys, true)) {
                        $filtered[$key] = $value;
                    }
                }
                $this->formResponses = $filtered;
            }
        }

        $validated = $this->validate($rules);

        $ticketTypeId = (int) $validated['ticketTypeId'];
        $quantity = (int) $validated['quantity'];
        $subdivisionId = isset($validated['subdivisionId']) && $validated['subdivisionId'] !== null
            ? (int) $validated['subdivisionId']
            : null;

        $event = $this->event();
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($event->organization_id);
        /** @var TicketType|null $ticketType */
        $ticketType = $event->ticketTypes()->whereKey($ticketTypeId)->first();

        if ($ticketType === null) {
            $this->addError('ticketTypeId', 'The selected ticket tier is unavailable.');

            return;
        }

        $subdivision = null;

        if ($subdivisionId !== null) {
            $subdivision = Subdivision::query()
                ->whereKey($subdivisionId)
                ->where('organization_id', $organization->id)
                ->first();

            if ($subdivision === null) {
                $this->addError(
                    'subdivisionId',
                    'Please select a valid ' . strtolower($organization->subdivision_label) . '.',
                );

                return;
            }
        }

        $sessionId = session()->getId() ?: Str::uuid()->toString();

        try {
            $reservation = $reservations->holdOrReuse($ticketType, $quantity, $sessionId, $subdivision?->id);
        } catch (TicketSoldOutException $exception) {
            $this->addError('quantity', $exception->getMessage());
            $this->dispatch('ticket-hold-failed');

            return;
        } catch (InvalidArgumentException $exception) {
            $this->addError('subdivisionId', $exception->getMessage());

            return;
        }

        $netAmount = round((float) $ticketType->price * $validated['quantity'], 2);
        $platformFee = $this->platformFee($organization);
        $breakdown = PaystackFeeCalculator::breakdown($netAmount, $platformFee);

        $orderAttributes = [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'subdivision_id' => $subdivision?->id,
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
                'quantity' => (int) $validated['quantity'],
                'subdivision_name' => $subdivision?->name,
                'platform_fee' => $breakdown['platform_fee'],
                'reservation_expires_at' => $reservation->expires_at?->toIso8601String(),
                'custom_fields' => $this->formResponses,
                'form_responses' => $this->formResponses,
            ],
        ];

        try {
            $order = $this->persistOrder($reservation, $orderAttributes);
        } catch (InvalidArgumentException $exception) {
            $this->addError('payment', $exception->getMessage());

            return;
        }

        $this->orderReference = $order->reference;
        $this->flashMessage = 'Your seats are held for 15 minutes. Complete payment to receive your QR pass.';

        $this->dispatch('open-paystack', payload: [
            'key' => app(PayZephyrService::class)->publicKey(),
            'email' => $order->customer_email,
            'amount' => $order->gross_pesewas,
            'currency' => $order->currency ?: 'GHS',
            'reference' => $order->reference,
            'subaccount' => $subdivision?->paystack_subaccount_code,
            'metadata' => [
                'order_reference' => $order->reference,
                'ticket_type' => $ticketType->name,
                'quantity' => $validated['quantity'],
                'organization_id' => $organization->id,
            ],
        ]);
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
        } catch (\Throwable) {
            // The asynchronous Paystack webhook remains the fail-safe if the
            // browser loses connectivity during callback verification.
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
        if (! $event->relationLoaded('organization')) {
            $event->load('organization');
        }
        /** @var Organization $organization */
        $organization = $event->organization ?? Organization::query()->findOrFail($event->organization_id);

        if (! $event->relationLoaded('ticketTypes')) {
            $event->load('ticketTypes');
        }
        /** @var Collection<int, TicketType> $ticketTypes */
        $ticketTypes = $event->ticketTypes ? $event->ticketTypes->sortBy('price')->values() : $event->ticketTypes()->orderBy('price')->get();

        $selectedTicketType = $ticketTypes->firstWhere('id', $this->ticketTypeId);

        $subtotal = $selectedTicketType === null ? 0.0 : round((float) $selectedTicketType->price * $this->quantity, 2);

        $breakdown = PaystackFeeCalculator::breakdown($subtotal, $this->platformFee($organization));

        if (! $event->relationLoaded('speakers')) {
            $event->load(['speakers' => fn ($q) => $q->orderBy('sort_order')]);
        }
        if (! $event->relationLoaded('schedules')) {
            $event->load(['schedules' => fn ($q) => $q->orderBy('sort_order')]);
        }
        if (! $event->relationLoaded('formFields')) {
            $event->load(['formFields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);
        }
        if (! $event->relationLoaded('faqs')) {
            $event->load(['faqs' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')]);
        }

        $speakers = $event->speakers ?? $event->speakers()->orderBy('sort_order')->get();
        $schedules = $event->schedules ?? $event->schedules()->orderBy('sort_order')->get();
        $formFields = $event->formFields ?? $event->formFields()->where('is_active', true)->orderBy('sort_order')->get();
        $faqs = $event->faqs ?? $event->faqs()->where('is_published', true)->orderBy('sort_order')->get();

        if ($this->faqSearch !== '') {
            $search = Str::lower($this->faqSearch);
            $faqs = $faqs->filter(function ($faq) use ($search) {
                return Str::contains(Str::lower($faq->question), $search) || Str::contains(Str::lower($faq->answer), $search);
            });
        }

        $scheduleDays = $schedules->groupBy(function ($schedule) {
            return $schedule->day_label ?: 'Day 1';
        });

        return view('livewire.public.event-show', [
            'organization' => $organization,
            'event' => $event,
            'ticketTypes' => $ticketTypes,
            'subdivisions' => $organization->subdivisions()->orderBy('name')->get(),
            'selectedTicketType' => $selectedTicketType,
            'subtotal' => $subtotal,
            'breakdown' => $breakdown,
            'speakers' => $speakers,
            'schedules' => $schedules,
            'scheduleDays' => $scheduleDays,
            'formFields' => $formFields,
            'faqs' => $faqs,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function persistOrder(Reservation $reservation, array $attributes): Order
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn(): Order => $this->storeOrder($reservation, $attributes));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new InvalidArgumentException('Unable to create a checkout order.');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function storeOrder(Reservation $reservation, array $attributes): Order
    {
        /** @var Order|null $order */
        $order = Order::query()->where('reservation_id', $reservation->getKey())->lockForUpdate()->first();

        if ($order !== null) {
            if ($order->payment_status !== OrderStatus::Pending) {
                throw new InvalidArgumentException(
                    'This checkout already has a payment that is under review or completed.',
                );
            }

            $order->forceFill($attributes)->save();

            return $order->refresh();
        }

        return Order::query()->create(
            $attributes
            + [
                'user_id' => null,
                'reference' => $this->generateReference(),
                'payment_method' => PaymentMethod::MobileMoney,
                'payment_status' => OrderStatus::Pending,
                'paid_at' => null,
            ],
        );
    }

    private function event(): Event
    {
        /** @var Event */
        $event = Event::query()
            ->with([
                'organization',
                'speakers' => fn ($q) => $q->orderBy('sort_order'),
                'schedules' => fn ($q) => $q->orderBy('sort_order'),
                'faqs' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order'),
                'formFields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'ticketTypes',
            ])
            ->findOrFail($this->eventId);

        return $event;
    }

    private function selectedTicketType(): ?TicketType
    {
        if ($this->ticketTypeId === null) {
            return null;
        }

        /** @var TicketType|null */
        return $this->event()->ticketTypes()->whereKey($this->ticketTypeId)->first();
    }

    private function platformFee(Organization $organization): float
    {
        return max(0.0, (float) ($organization->settings['platform_fee'] ?? 0));
    }

    private function generateReference(): string
    {
        do {
            $reference = 'TKT-' . strtoupper((string) Str::ulid());
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
