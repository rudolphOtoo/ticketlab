<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\PaystackCheckout;
use App\Services\Payment\PaystackCheckoutException;
use App\Services\Payment\PaystackGateway;
use App\Services\Payment\PendingOrderService;
use App\Services\PaystackFeeCalculator;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

#[Layout('layouts.public')]
class EventShow extends Component
{
    private const int CHECKOUTS_PER_WINDOW = 15;

    #[Locked]
    public int $organizationId;

    #[Locked]
    public int $eventId;

    /** The event mount() already loaded, reused by the first render; not persisted to later requests. */
    protected ?Event $mountedEvent = null;

    public ?int $ticketTypeId = null;

    public int $quantity = 1;

    public ?int $subdivisionId = null;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public bool $checkoutOpen = false;

    public ?string $orderReference = null;

    public ?string $flashMessage = null;

    /** @var array<string, mixed> */
    public array $customFieldResponses = [];

    /** @var array<string, mixed> */
    public array $formResponses = [];

    public string $faqSearch = '';

    public ?int $activeFaqId = null;

    public ?int $viewingSpeakerId = null;

    public function viewSpeaker(int $speakerId): void
    {
        $this->viewingSpeakerId = $speakerId;
    }

    public function closeSpeakerModal(): void
    {
        $this->viewingSpeakerId = null;
    }

    public function mount(string $org_slug, string $event_slug): void
    {
        $organization = Organization::query()->where('slug', $org_slug)->firstOrFail();

        $event = Event::query()
            ->with('ticketTypes')
            ->where('organization_id', $organization->id)
            ->where('slug', $event_slug)
            ->where('status', 'published')
            ->firstOrFail();

        $this->organizationId = $organization->id;
        $this->eventId = $event->id;
        $this->mountedEvent = $event;

        $defaultTicket = $event
            ->ticketTypes
            ->filter(static fn(TicketType $ticket): bool => $ticket->isOnSale() && $ticket->available_quantity > 0)
            ->sortBy('price')
            ->first();

        $this->ticketTypeId = $defaultTicket?->id;
        $this->subdivisionId = $organization->subdivisions()->orderBy('name')->value('id');
    }

    public function selectTicketType(int $ticketTypeId): void
    {
        $ticketType = $this->event()->ticketTypes()->whereKey($ticketTypeId)->first();

        if ($ticketType === null || !$ticketType->isOnSale()) {
            return;
        }

        $this->ticketTypeId = $ticketType->id;

        $available = $ticketType->available_quantity;
        if ($available > 0 && $this->quantity > $available) {
            $this->quantity = min(20, $available);
        }

        $this->resetValidation('ticketTypeId');
    }

    public function incrementQuantity(): void
    {
        $ticketType = $this->selectedTicketType();
        $maximum = $ticketType === null ? 20 : $ticketType->available_quantity;
        $maximum = max(1, min(20, $maximum));

        if ($this->quantity < $maximum) {
            $this->quantity++;
            $this->resetValidation('quantity');
        }
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
        $maximum = $ticketType->available_quantity ?? 20;
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

    public function startCheckout(
        TicketReservationService $reservations,
        ?PendingOrderService $pendingOrders = null,
    ): void {
        $pendingOrders ??= app(PendingOrderService::class);
        $event = $this->event();
        $organization = Organization::query()->findOrFail($event->organization_id);
        // Only the questions the buyer was shown: a switched-off required field must never block checkout.
        $formFields = $event->formFields()->where('is_active', true)->get();

        $validated = $this->validate(...$this->checkoutRules($formFields));

        if ($this->checkoutThrottled()) {
            $this->addError('payment', 'Too many checkout attempts. Please wait a few minutes and try again.');

            return;
        }

        $quantity = (int) $validated['quantity'];
        $ticketType = $this->checkoutTicketType($event, (int) $validated['ticketTypeId']);
        $subdivision = $this->checkoutSubdivision($organization, $validated['subdivisionId'] ?? null);

        try {
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

        $orderAttributes = $this->orderAttributes($validated, $ticketType, $subdivision, $reservation, $formFields);

        try {
            $order = $pendingOrders->persist($reservation, $orderAttributes);
            $session = app(PaystackCheckout::class)->begin($order, $subdivision, [
                'ticket_type' => $ticketType->name,
                'quantity' => $reservation->quantity,
                'organization_id' => $order->organization_id,
                'subdivision' => $subdivision?->name,
            ]);
        } catch (InvalidArgumentException|PaystackCheckoutException $exception) {
            $this->addError('payment', $exception->getMessage());

            return;
        }

        $this->orderReference = $order->reference;
        $this->flashMessage = 'Your seats are held for 15 minutes. Complete payment to receive your QR pass.';

        $this->dispatch('open-paystack', accessCode: $session['access_code'], reference: $session['reference']);
    }

    #[On('payment-completed')]
    public function paymentCompleted(string $reference): void
    {
        $order = Order::findByGatewayReference($reference);

        if ($order === null) {
            $this->addError('payment', 'We could not locate that order.');

            return;
        }

        try {
            $paystack = app(PaystackGateway::class);
            $verification = $paystack->verify($reference);

            if ($paystack->isSuccessful($verification)) {
                app(OrderFulfillmentService::class)->fulfill($order, $verification);
            }
        } catch (Throwable $exception) {
            Log::warning('Paystack browser callback verification deferred to webhook', [
                'order_reference' => $order->reference,
                'error' => $exception->getMessage(),
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
        if (!$ticketType->isOnSale()) {
            return $ticketType->sales_start_at?->isFuture() === true ? 'Coming Soon' : 'Sales Closed';
        }

        if ($ticketType->available_quantity <= 0) {
            return 'Sold Out';
        }

        $limitedThreshold = max(10, (int) ceil($ticketType->total_capacity * 0.2));

        return $ticketType->available_quantity <= $limitedThreshold ? 'Limited' : 'Available';
    }

    public function availabilityBadgeClass(TicketType $ticketType): string
    {
        return match ($this->availabilityBadge($ticketType)) {
            'Sold Out', 'Sales Closed', 'Coming Soon' => 'bg-zinc-200 text-zinc-600',
            'Limited' => 'bg-amber-100 text-amber-800',
            default => 'bg-emerald-100 text-emerald-800',
        };
    }

    public function render(): View
    {
        // One query per relation, already ordered for display; nothing below re-queries them.
        $event = ($this->mountedEvent ?? $this->event())->loadMissing([
            'organization',
            'ticketTypes',
            'speakers' => static fn($q) => $q->orderBy('sort_order'),
            'schedules' => static fn($q) => $q->orderBy('sort_order'),
            'faqs' => static fn($q) => $q->where('is_published', true)->orderBy('sort_order'),
            'formFields' => static fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ]);
        $organization = $event->organization;

        /** @var Collection<int, TicketType> $ticketTypes */
        // Paused tiers are hidden; tiers outside their sales window stay visible but cannot be selected.
        $ticketTypes = $event->ticketTypes->where('is_active', true)->sortBy('price')->values();

        $selectedTicketType = $ticketTypes->firstWhere('id', $this->ticketTypeId);

        $subtotal = $selectedTicketType === null ? 0.0 : round((float) $selectedTicketType->price * $this->quantity, 2);

        $breakdown = PaystackFeeCalculator::breakdown($subtotal, $this->platformFee($organization));

        $speakers = $event->speakers;
        $schedules = $event->schedules;
        $faqs = $event->faqs;

        if ($this->faqSearch !== '') {
            $search = Str::lower($this->faqSearch);
            $faqs = $faqs->filter(
                static fn($faq): bool => (
                    Str::contains(Str::lower($faq->question), $search)
                    || Str::contains(Str::lower($faq->answer), $search)
                ),
            );
        }

        $scheduleDays = $schedules->groupBy(static fn($schedule): string => $schedule->day_label !== ''
            ? $schedule->day_label
            : 'Day 1');

        $formFields = $event->formFields;
        $customization = is_array($event->customization) ? $event->customization : [];
        $viewingSpeaker = $this->viewingSpeakerId !== null
            ? $speakers->firstWhere('id', $this->viewingSpeakerId)
            : null;

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
            'faqs' => $faqs,
            'formFields' => $formFields,
            'customization' => $customization,
            'viewingSpeaker' => $viewingSpeaker,
        ]);
    }

    /**
     * Each attempt holds seats and opens a Paystack transaction, so scripted retries are capped per client.
     */
    private function checkoutThrottled(): bool
    {
        $limiterKey = 'checkout:' . (request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($limiterKey, self::CHECKOUTS_PER_WINDOW)) {
            return true;
        }

        RateLimiter::hit($limiterKey, 600);

        return false;
    }

    /**
     * Online sales honour the tier's pause switch and sales window set in Tickets & Pricing.
     */
    private function checkoutTicketType(Event $event, int $ticketTypeId): TicketType
    {
        $ticketType = $event
            ->ticketTypes()
            ->whereKey($ticketTypeId)
            ->first() ?? throw ValidationException::withMessages([
            'ticketTypeId' => 'The selected ticket tier is unavailable.',
        ]);

        if (!$ticketType->isOnSale()) {
            throw ValidationException::withMessages(['ticketTypeId' => 'This ticket tier is not on sale right now.']);
        }

        return $ticketType;
    }

    /**
     * The buyer's chosen zone, which must belong to the event's organisation.
     */
    private function checkoutSubdivision(Organization $organization, mixed $subdivisionId): ?Subdivision
    {
        if ($subdivisionId === null) {
            return null;
        }

        return (
            Subdivision::query()
                ->whereKey((int) $subdivisionId)
                ->where('organization_id', $organization->id)
                ->first() ?? throw ValidationException::withMessages([
                'subdivisionId' => 'Please select a valid ' . strtolower($organization->subdivision_label) . '.',
            ])
        );
    }

    /**
     * Buyer details plus the organiser's questionnaire, as arguments for validate().
     *
     * @param Collection<int, EventFormField> $formFields
     *
     * @return array{array<string, list<mixed>>, array<string, string>}
     */
    private function checkoutRules(Collection $formFields): array
    {
        // Support both formResponses and customFieldResponses conventions
        $useFormResponses = count($this->formResponses) > 0 && count($this->customFieldResponses) === 0;

        [$fieldRules, $messages] = $this->customFieldRules(
            $formFields,
            $useFormResponses ? 'formResponses' : 'customFieldResponses',
        );

        return [
            [
                'ticketTypeId' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1', 'max:20'],
                'subdivisionId' => ['nullable', 'integer'],
                'customerName' => ['required', 'string', 'max:255'],
                'customerEmail' => ['required', 'string', 'email', 'max:255'],
                'customerPhone' => ['required', 'string', 'max:50'],
                ...$fieldRules,
            ],
            $messages,
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @param Collection<int, EventFormField> $formFields
     *
     * @return array<string, mixed>
     */
    private function orderAttributes(
        array $validated,
        TicketType $ticketType,
        ?Subdivision $subdivision,
        Reservation $reservation,
        Collection $formFields,
    ): array {
        // The reservation is resized to the requested quantity, so it is the source of truth here.
        $quantity = $reservation->quantity;
        [$customFieldsData, $rawAnswers] = $this->questionnaireAnswers($formFields);

        $organization = $ticketType->event->organization;
        $netAmount = round((float) $ticketType->price * $quantity, precision: 2);
        $breakdown = PaystackFeeCalculator::breakdown($netAmount, $this->platformFee($organization));

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
                'custom_fields' => $customFieldsData,
                'form_responses' => $rawAnswers,
            ],
        ];
    }

    /**
     * Validation rules and messages for the organiser's dynamic questionnaire.
     *
     * @param Collection<int, EventFormField> $formFields
     *
     * @return array{array<string, list<mixed>>, array<string, string>}
     */
    private function customFieldRules(Collection $formFields, string $prefix): array
    {
        $rules = [];
        $messages = [];

        foreach ($formFields as $field) {
            $key = $prefix . '.' . $field->field_key;
            $isCheckbox = $field->field_type === 'checkbox';

            $presence = match (true) {
                !$field->is_required => 'nullable',
                $isCheckbox => 'accepted',
                default => 'required',
            };

            if ($presence !== 'nullable') {
                $messages["{$key}.{$presence}"] = $isCheckbox
                    ? "Please confirm/check {$field->label}."
                    : "Please provide an answer for {$field->label}.";
            }

            $options = array_values(array_filter($field->options ?? []));
            $hasChoices = in_array($field->field_type, ['select', 'radio'], true) && $options !== [];

            $typeRules = match (true) {
                $field->field_type === 'textarea' => ['string', 'max:1000'],
                // Rule::in quotes each option, so answers containing commas validate correctly.
                $hasChoices => ['string', Rule::in($options)],
                in_array($field->field_type, ['select', 'radio'], true) => ['string', 'max:255'],
                $isCheckbox => ['boolean'],
                default => ['string', 'max:255'],
            };

            if ($hasChoices) {
                $messages["{$key}.in"] = "Please select a valid option for {$field->label}.";
            }

            $rules[$key] = [$presence, ...$typeRules];
        }

        return [$rules, $messages];
    }

    /**
     * Sanitised questionnaire answers, both labelled (for staff exports) and raw (for re-validation).
     *
     * @param Collection<int, EventFormField> $formFields
     *
     * @return array{array<string, array{label: string, value: mixed}>, array<string, mixed>}
     */
    private function questionnaireAnswers(Collection $formFields): array
    {
        $customFieldsData = [];
        $rawAnswers = [];
        foreach ($formFields as $field) {
            $val = $this->customFieldResponses[$field->field_key] ?? $this->formResponses[$field->field_key] ?? null;

            if ($val !== null && $val !== '') {
                $cleanVal = is_string($val) ? trim(strip_tags($val)) : $val;
                $customFieldsData[$field->field_key] = [
                    'label' => $field->label,
                    'value' => $cleanVal,
                ];
                $rawAnswers[$field->field_key] = $cleanVal;
            }
        }

        return [$customFieldsData, $rawAnswers];
    }

    /**
     * Reservations are keyed to the browser session; fall back to a one-off id if the session has none yet.
     */
    private function checkoutSessionId(): string
    {
        $sessionId = session()->getId();

        return $sessionId !== '' ? $sessionId : Str::uuid()->toString();
    }

    private function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }

    private function selectedTicketType(): ?TicketType
    {
        if ($this->ticketTypeId === null) {
            return null;
        }

        return TicketType::query()->where('event_id', $this->eventId)->find($this->ticketTypeId);
    }

    private function platformFee(Organization $organization): float
    {
        return max(0.0, (float) ($organization->settings['platform_fee'] ?? 0));
    }
}
