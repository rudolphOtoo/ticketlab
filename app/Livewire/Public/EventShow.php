<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Exceptions\TicketSoldOutException;
use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\PaystackFeeCalculator;
use App\Services\Payment\PayZephyrService;
use App\Services\Payment\PendingOrderService;
use App\Services\Reservation\TicketReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

#[Layout('layouts.public')]
class EventShow extends Component
{
    public int $organizationId;

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
            ->with(['ticketTypes', 'formFields' => fn($q) => $q->where('is_active', true)])
            ->where('organization_id', $organization->id)
            ->where('slug', $event_slug)
            ->where('status', 'published')
            ->firstOrFail();

        $this->organizationId = $organization->id;
        $this->eventId = $event->id;

        $defaultTicket = $event
            ->ticketTypes
            ->filter(fn($ticket) => (int) ($ticket->available_quantity ?? 0) > 0)
            ->sortBy('price')
            ->first();

        $this->ticketTypeId = $defaultTicket?->id;
        $this->subdivisionId = $organization->subdivisions()->orderBy('name')->value('id');
    }

    public function selectTicketType(int $ticketTypeId): void
    {
        $ticketType = $this->event()->ticketTypes()->whereKey($ticketTypeId)->first();

        if ($ticketType === null) {
            return;
        }

        $this->ticketTypeId = $ticketType->id;

        $available = (int) ($ticketType->available_quantity ?? 0);
        if ($available > 0 && $this->quantity > $available) {
            $this->quantity = min(20, $available);
        }

        $this->resetValidation('ticketTypeId');
    }

    public function incrementQuantity(): void
    {
        $ticketType = $this->selectedTicketType();
        $maximum = $ticketType?->available_quantity ?? 20;
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
        $event = $this->event()->load(['formFields' => fn($q) => $q->where('is_active', true)]);
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($event->organization_id);

        $rules = [
            'ticketTypeId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'subdivisionId' => ['nullable', 'integer'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'string', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50'],
        ];

        $formFields = $event->formFields()->where('is_active', true)->orderBy('sort_order')->get();
        $messages = [];

        // Support both formResponses and customFieldResponses conventions
        $useFormResponses = count($this->formResponses) > 0 && count($this->customFieldResponses) === 0;
        $fieldPrefix = $useFormResponses ? 'formResponses' : 'customFieldResponses';

        foreach ($formFields as $field) {
            $key = $fieldPrefix . '.' . $field->field_key;
            $fieldRules = [];

            if ($field->is_required) {
                if ($field->field_type === 'checkbox') {
                    $fieldRules[] = 'accepted';
                    $messages[$key . '.accepted'] = "Please confirm/check {$field->label}.";
                } else {
                    $fieldRules[] = 'required';
                    $messages[$key . '.required'] = "Please provide an answer for {$field->label}.";
                }
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($field->field_type === 'textarea') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:1000';
            } elseif ($field->field_type === 'select' || $field->field_type === 'radio') {
                $fieldRules[] = 'string';
                if ($field->options !== null && $field->options !== []) {
                    $optionsList = is_array($field->options) ? array_values(array_filter($field->options)) : [];
                    $fieldRules[] = 'in:' . implode(',', $optionsList);
                    $messages[$key . '.in'] = "Please select a valid option for {$field->label}.";
                }
            } elseif ($field->field_type !== 'checkbox') {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:255';
            }

            $rules[$key] = $fieldRules;
        }

        $validated = $this->validate($rules, $messages);

        $ticketTypeId = (int) $validated['ticketTypeId'];
        $quantity = (int) $validated['quantity'];
        $subdivisionId = ($validated['subdivisionId'] ?? null) !== null ? (int) $validated['subdivisionId'] : null;

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

        $sessionId = (string) session()->getId();
        if ($sessionId === '') {
            $sessionId = Str::uuid()->toString();
        }

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

        $customFieldsData = [];
        $rawAnswers = [];
        foreach ($formFields as $field) {
            $val = $this->customFieldResponses[$field->field_key]
                ?? $this->formResponses[$field->field_key]
                ?? null;

            if ($val !== null && $val !== '') {
                $cleanVal = is_string($val) ? trim(strip_tags($val)) : $val;
                $customFieldsData[$field->field_key] = [
                    'label' => $field->label,
                    'value' => $cleanVal,
                ];
                $rawAnswers[$field->field_key] = $cleanVal;
            }
        }

        $netAmount = round((float) $ticketType->price * $quantity, precision: 2);
        $platformFee = $this->platformFee($organization);
        $breakdown = PaystackFeeCalculator::breakdown($netAmount, $platformFee);

        $orderAttributes = [
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
                'reservation_expires_at' => $reservation->expires_at?->toIso8601String(),
                'custom_fields' => $customFieldsData,
                'form_responses' => $rawAnswers,
            ],
        ];

        try {
            $order = $pendingOrders->persist($reservation, $orderAttributes);
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
            'currency' => $order->currency ?? 'GHS',
            'reference' => $order->reference,
            'subaccount' => $subdivision?->paystack_subaccount_code,
            'metadata' => [
                'order_reference' => $order->reference,
                'ticket_type' => $ticketType->name,
                'quantity' => $quantity,
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
        $event = $this->event()->load([
            'organization',
            'ticketTypes',
            'speakers',
            'schedules',
            'faqs' => fn($q) => $q->where('is_published', true),
            'formFields' => fn($q) => $q->where('is_active', true),
        ]);
        /** @var Organization $organization */
        $organization = $event->organization;

        /** @var Collection<int, TicketType> $ticketTypes */
        $ticketTypes = $event->ticketTypes->sortBy('price')->values();

        $selectedTicketType = $ticketTypes->firstWhere('id', $this->ticketTypeId);

        $subtotal = $selectedTicketType === null ? 0.0 : round((float) $selectedTicketType->price * $this->quantity, 2);

        $breakdown = PaystackFeeCalculator::breakdown($subtotal, $this->platformFee($organization));

        $speakers = $event->speakers()->orderBy('sort_order')->get();
        $schedules = $event->schedules()->orderBy('sort_order')->get();
        $faqs = $event->faqs()->where('is_published', true)->orderBy('sort_order')->get();

        if ($this->faqSearch !== '') {
            $search = Str::lower($this->faqSearch);
            $faqs = $faqs->filter(function ($faq) use ($search) {
                return (
                    Str::contains(Str::lower($faq->question), $search)
                    || Str::contains(Str::lower($faq->answer), $search)
                );
            });
        }

        $scheduleDays = $schedules->groupBy(function ($schedule) {
            return $schedule->day_label ?: 'Day 1';
        });

        $formFields = $event->formFields()->where('is_active', true)->orderBy('sort_order')->get();
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

    private function event(): Event
    {
        /** @var Event */
        return Event::query()->findOrFail($this->eventId);
    }

    private function selectedTicketType(): ?TicketType
    {
        if ($this->ticketTypeId === null) {
            return null;
        }

        /** @var TicketType|null */
        return TicketType::query()->where('event_id', $this->eventId)->find($this->ticketTypeId);
    }

    private function platformFee(Organization $organization): float
    {
        return max(0.0, (float) ($organization->settings['platform_fee'] ?? 0));
    }
}
