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
use App\Services\Payment\PaystackSubaccountResolver;
use App\Services\Payment\PendingOrderService;
use App\Services\PaystackFeeCalculator;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;
use App\Services\Reservation\ZoneCapacity;
use App\Support\ZoneVenue;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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

    private const int MAX_PER_ORDER = 20;

    /** Shown when Pay is refused because the page was out of date; the browser then gets fresh data. */
    public const string CHANGED_MESSAGE = 'Prices or seats changed. Please review.';

    #[Locked]
    public int $organizationId;

    #[Locked]
    public int $eventId;

    /** The event mount() already loaded, reused by the first render; not persisted to later requests. */
    protected ?Event $mountedEvent = null;

    /** Memoized event instance with eager-loaded relations for the current request lifecycle. */
    protected ?Event $resolvedEvent = null;

    public ?int $ticketTypeId = null;

    public int $quantity = 1;

    public ?int $subdivisionId = null;

    /**
     * The total (in pesewas) the buyer saw in the browser when they pressed Pay. Display only: the server prices the
     * order itself and refuses to continue if the two disagree, so nobody is charged an amount they were not shown.
     */
    public ?int $clientTotalPesewas = null;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

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
            ->where(Event::publiclyVisible(...))
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
        // No zone is pre-selected: the buyer must choose the zone they attend (and whose account is paid).
        $this->subdivisionId = null;
    }

    public function startCheckout(
        TicketReservationService $reservations,
        ?PendingOrderService $pendingOrders = null,
    ): void {
        $pendingOrders ??= app(PendingOrderService::class);
        $event = $this->eventOnSale();
        $organization = $event->organization;
        // Only the questions the buyer was shown: a switched-off required field must never block checkout.
        $formFields = $event->formFields->where('is_active', true)->values();

        $validated = $this->validate(...$this->checkoutRules($formFields, $organization));

        if ($this->checkoutThrottled()) {
            $this->addError('payment', 'Too many checkout attempts. Please wait a few minutes and try again.');

            return;
        }

        $quantity = (int) $validated['quantity'];
        $ticketType = $this->checkoutTicketType($event, (int) $validated['ticketTypeId']);
        $subdivision = $this->checkoutSubdivision($organization, $validated['subdivisionId'] ?? null);

        if ($this->pageWasOutOfDate($ticketType, $quantity, $organization)) {
            return;
        }

        if ($this->zoneCannotBePaid($ticketType, $subdivision)) {
            return;
        }

        $reservation = $this->holdSeats($reservations, $ticketType, $quantity, $subdivision);

        if ($reservation === null) {
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

    public function render(): View
    {
        $event = $this->event();
        $organization = $event->organization;

        /** @var Collection<int, TicketType> $ticketTypes */
        // Paused tiers are hidden; tiers outside their sales window stay visible but cannot be selected.
        $ticketTypes = $event->ticketTypes->where('is_active', true)->sortBy('price')->values();

        $subdivisions = $organization->subdivisions->sortBy('name')->values();
        // Each zone meets at its own venue (and possibly date), so buyers see them all before choosing.
        $zoneVenues = $subdivisions->mapWithKeys(static fn(Subdivision $zone): array => [
            $zone->id => ZoneVenue::for($event, $zone),
        ]);

        $checkout = $this->clientCheckout($event, $ticketTypes, $subdivisions, $organization);
        $initialTicket = collect($checkout['tiers'])->firstWhere('id', $this->ticketTypeId);

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
            'subdivisions' => $subdivisions,
            // Each zone meets at its own venue (and possibly date), so buyers see them all before choosing.
            'zoneVenues' => $zoneVenues,
            'checkout' => $checkout,
            // First paint before Alpine starts (and for crawlers); Alpine takes over from the same numbers.
            'initialTicket' => $initialTicket,
            'initialTotals' => PaystackFeeCalculator::breakdown(
                (($initialTicket['price'] ?? 0) * $this->quantity) / 100,
                $this->platformFee($organization),
            ),
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
     * Everything the browser needs to choose a tier, zone and quantity and show the total without a server round trip:
     * prices in pesewas, seats left per tier per zone, and the fee formula's constants. Display only; startCheckout()
     * re-checks every value.
     *
     * @param Collection<int, TicketType> $ticketTypes
     * @param SupportCollection<int, Subdivision> $zones
     *
     * @return array{
     *     tiers: list<array{id: int, name: string, price: int, onSale: bool, status: ?string, limitedAt: int, seats: array<int, int>}>,
     *     zones: list<array{id: int, name: string, when: string, place: ?string}>,
     *     zoneRequired: bool,
     *     zoneLabel: string,
     *     maxPerOrder: int,
     *     fee: array{basis: int, scale: int, platformFee: int},
     *     initial: array{ticketId: ?int, quantity: int, zoneId: ?int}
     * }
     */
    private function clientCheckout(
        Event $event,
        Collection $ticketTypes,
        SupportCollection $zones,
        Organization $organization,
    ): array {
        $zoneIds = array_values($zones->map(static fn(Subdivision $zone): int => $zone->id)->all());
        $seats = app(ZoneCapacity::class)->sellableGrid($ticketTypes, $zoneIds);

        return [
            'tiers' => array_values(
                $ticketTypes->map(static fn(TicketType $tier): array => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'price' => PaystackFeeCalculator::toPesewas($tier->price),
                    'onSale' => $tier->isOnSale(),
                    // Shown instead of a seat badge while the tier is outside its sales window.
                    'status' => match (true) {
                        $tier->isOnSale() => null,
                        $tier->sales_start_at?->isFuture() === true => 'Coming Soon',
                        default => 'Sales Closed',
                    },
                    'limitedAt' => max(10, (int) ceil($tier->total_capacity * 0.2)),
                    'seats' => $seats[$tier->id] ?? [0 => 0],
                ])->all(),
            ),
            'zones' => array_values(
                $zones->map(static function (Subdivision $zone) use ($event): array {
                    // Reads the event's eager-loaded zone rows; no query per zone.
                    $venue = ZoneVenue::for($event, $zone);

                    return [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'when' => $venue->when(),
                        'place' => $venue->place(),
                    ];
                })->all(),
            ),
            'zoneRequired' => $zones->isNotEmpty(),
            'zoneLabel' => strtolower($organization->subdivision_label),
            'maxPerOrder' => self::MAX_PER_ORDER,
            'fee' => [
                'basis' => PaystackFeeCalculator::DENOMINATOR_BASIS,
                'scale' => PaystackFeeCalculator::SCALE,
                'platformFee' => PaystackFeeCalculator::toPesewas($this->platformFee($organization)),
            ],
            'initial' => [
                'ticketId' => $this->ticketTypeId,
                'quantity' => $this->quantity,
                'zoneId' => $this->subdivisionId,
            ],
        ];
    }

    /**
     * True (and the buyer is sent fresh data) when the tier went off sale or the price changed since the page loaded.
     */
    private function pageWasOutOfDate(TicketType $ticketType, int $quantity, Organization $organization): bool
    {
        if (!$ticketType->isOnSale()) {
            $this->pricesOrSeatsChanged('ticketTypeId', 'This ticket tier is not on sale right now.');

            return true;
        }

        if ($this->shownTotalDiffers($ticketType, $quantity, $organization)) {
            $this->pricesOrSeatsChanged('clientTotalPesewas');

            return true;
        }

        return false;
    }

    /**
     * Refuses when the total the buyer was shown is not what the server would charge (a tampered page, or prices
     * changed since the page loaded). Nothing is held and no transaction is opened.
     */
    private function shownTotalDiffers(TicketType $ticketType, int $quantity, Organization $organization): bool
    {
        $expected = PaystackFeeCalculator::grossPesewas(
            round((float) $ticketType->price * $quantity, precision: 2),
            $this->platformFee($organization),
        );

        if ($this->clientTotalPesewas === $expected) {
            return false;
        }

        Log::notice('Checkout refused: the total shown in the browser differs from the server price.', [
            'event_id' => $this->eventId,
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
            'shown_pesewas' => $this->clientTotalPesewas,
            'expected_pesewas' => $expected,
        ]);

        return true;
    }

    /**
     * Pay was refused because the page was out of date (a price, the seats left or a sales window changed after it
     * loaded). Tells the buyer, and sends the browser the current prices and seats: the page keeps their tier, zone
     * and quantity (trimmed to what is left) and shows the new total, ready to pay again.
     */
    private function pricesOrSeatsChanged(string $field, ?string $reason = null): void
    {
        $this->addError($field, trim(self::CHANGED_MESSAGE . ' ' . ($reason ?? '')));

        $event = $this->event();
        // The tiers loaded at the start of this request may predate the change that caused the refusal.
        $event->load('ticketTypes');
        $organization = $event->organization;

        $this->dispatch('checkout-refreshed', config: $this->clientCheckout(
            $event,
            $event->ticketTypes->where('is_active', true)->sortBy('price')->values(),
            $organization->subdivisions->sortBy('name')->values(),
            $organization,
        ));
    }

    /**
     * Each attempt holds seats and opens a Paystack transaction, so scripted retries are capped per client.
     */
    /**
     * The page may have been open since before the event was archived; nothing is sold after that.
     */
    private function eventOnSale(): Event
    {
        $event = $this->event();
        $closed = $event->salesClosedReason();

        if ($closed !== null) {
            throw ValidationException::withMessages(['payment' => $closed]);
        }

        return $event;
    }

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
     * Holds the seats for 15 minutes; null (with the reason shown to the buyer) when the tier or zone is sold out.
     */
    private function holdSeats(
        TicketReservationService $reservations,
        TicketType $ticketType,
        int $quantity,
        ?Subdivision $zone,
    ): ?Reservation {
        try {
            return $reservations->holdOrReuse($ticketType, $quantity, $this->checkoutSessionId(), $zone?->id);
        } catch (TicketSoldOutException $exception) {
            $this->pricesOrSeatsChanged('quantity', $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            $this->addError('subdivisionId', $exception->getMessage());
        }

        return null;
    }

    /**
     * Refuses before any seats are held or an order is created: a zone that cannot be paid must never lock stock,
     * and there is no fallback to the platform's main account.
     */
    private function zoneCannotBePaid(TicketType $ticketType, ?Subdivision $zone): bool
    {
        if ((float) $ticketType->price <= 0 || app(PaystackSubaccountResolver::class)->forSubdivision($zone) !== null) {
            return false;
        }

        Log::warning('Checkout refused before hold: no verified payout subaccount for the zone.', [
            'organization_id' => $this->organizationId,
            'event_id' => $this->eventId,
            'subdivision_id' => $zone?->id,
        ]);
        $this->addError('payment', PaystackCheckout::unpayableZoneMessage($zone));

        return true;
    }

    /**
     * The tier being bought, which must belong to this event. Whether it is on sale is checked by the caller.
     */
    private function checkoutTicketType(Event $event, int $ticketTypeId): TicketType
    {
        return (
            $event->ticketTypes()->whereKey($ticketTypeId)->first() ?? throw ValidationException::withMessages([
                'ticketTypeId' => 'The selected ticket tier is unavailable.',
            ])
        );
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
    private function checkoutRules(Collection $formFields, Organization $organization): array
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
                'quantity' => ['required', 'integer', 'min:1', 'max:' . self::MAX_PER_ORDER],
                'clientTotalPesewas' => ['required', 'integer', 'min:0'],
                // When the organisation has zones, every order belongs to one: it decides the gate and the payout.
                'subdivisionId' => [$organization->subdivisions->isNotEmpty() ? 'required' : 'nullable', 'integer'],
                'customerName' => ['required', 'string', 'max:255'],
                'customerEmail' => ['required', 'string', 'email', 'max:255'],
                'customerPhone' => ['required', 'string', 'max:50'],
                ...$fieldRules,
            ],
            [
                ...$messages,
                'subdivisionId.required' =>
                    'Choose your ' . strtolower($organization->subdivision_label) . ' to continue.',
            ],
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
        if ($this->resolvedEvent !== null) {
            return $this->resolvedEvent;
        }

        if ($this->mountedEvent !== null) {
            return $this->resolvedEvent = $this->mountedEvent->loadMissing([
                'organization.subdivisions',
                'ticketTypes',
                'zones',
                'speakers' => static fn($q) => $q->orderBy('sort_order'),
                'schedules' => static fn($q) => $q->orderBy('sort_order'),
                'faqs' => static fn($q) => $q->where('is_published', true)->orderBy('sort_order'),
                'formFields' => static fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ]);
        }

        return $this->resolvedEvent = Event::query()
            ->where('organization_id', $this->organizationId)
            ->with([
                'organization.subdivisions',
                'ticketTypes',
                'zones',
                'speakers' => static fn($q) => $q->orderBy('sort_order'),
                'schedules' => static fn($q) => $q->orderBy('sort_order'),
                'faqs' => static fn($q) => $q->where('is_published', true)->orderBy('sort_order'),
                'formFields' => static fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])
            ->findOrFail($this->eventId);
    }

    private function platformFee(Organization $organization): float
    {
        return max(0.0, (float) ($organization->settings['platform_fee'] ?? 0));
    }
}
