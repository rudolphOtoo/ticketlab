<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Subdivision;
use App\Services\Mail\TicketEmailer;
use App\Services\Payment\QrCodeService;
use App\Services\Zones\ZoneAssignment;
use App\Support\ZoneVenue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
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

    public ?string $resendMessage = null;

    /** "Resend my ticket": at most this many per order per hour, and never twice within RESEND_GAP_SECONDS. */
    public const int RESEND_PER_HOUR = 3;

    public const int RESEND_GAP_SECONDS = 120;

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

    /**
     * Emails the passes again to the address on the order (never another one). The page itself stays the ticket; the
     * email is the backup copy, so this is limited per order.
     */
    public function resendTicket(TicketEmailer $emailer): void
    {
        $order = Order::query()->withCount('attendees')->findOrFail($this->orderId);

        if (
            $order->payment_status !== OrderStatus::Paid
            || $order->attendees_count === 0
            || !filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->resendMessage = 'There is no ticket email to send for this order.';

            return;
        }

        $hourly = "ticket-resend:{$order->id}";
        $gap = "ticket-resend-gap:{$order->id}";

        if (RateLimiter::tooManyAttempts($gap, 1) || RateLimiter::tooManyAttempts($hourly, self::RESEND_PER_HOUR)) {
            $wait = max(RateLimiter::availableIn($gap), RateLimiter::availableIn($hourly));
            $this->resendMessage =
                'We just sent it. You can ask again in '
                . self::waitText($wait)
                . '; your ticket is on this page meanwhile.';

            return;
        }

        RateLimiter::hit($gap, self::RESEND_GAP_SECONDS);
        RateLimiter::hit($hourly, 3600);

        $emailer->sendSoon($order);
        $this->resendMessage = 'Sending your ticket again. Check your inbox (and spam folder) in a few minutes.';
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
            'ticketUrl' => route('public.tickets.show', ['order' => $order->reference]),
            'emailStatus' => self::emailStatus($order),
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

    /**
     * Where the backup email stands, in the buyer's words.
     *
     * @return array{state: 'sent'|'sending'|'tomorrow'|'failed'|'none', at: \Carbon\CarbonImmutable|null}
     */
    private static function emailStatus(Order $order): array
    {
        $state = match (true) {
            $order->ticket_email_due_at !== null
                && str_contains((string) $order->ticket_email_error, 'Daily email limit')
                => 'tomorrow',
            $order->ticket_email_due_at !== null => 'sending',
            $order->ticket_emailed_at !== null => 'sent',
            $order->ticket_email_attempts > 0 => 'failed',
            default => 'none',
        };

        return ['state' => $state, 'at' => $order->ticket_emailed_at];
    }

    private static function waitText(int $seconds): string
    {
        return $seconds < 90 ? max(1, $seconds) . ' seconds' : (int) ceil($seconds / 60) . ' minutes';
    }
}
