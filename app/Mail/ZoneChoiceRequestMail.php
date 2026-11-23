<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\MailCategory;
use App\Jobs\Concerns\WaitsForMailQuota;
use App\Jobs\Middleware\MailQuotaGate;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Asks a buyer whose paid passes have no zone to choose one, once, on their ticket page.
 */
class ZoneChoiceRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use WaitsForMailQuota;

    public function __construct(
        public Order $order,
    ) {
        $this->onQueue('emails');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new MailQuotaGate(MailCategory::ZoneChoice)];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Action needed: choose your {$this->label()} · {$this->order->reference}");
    }

    public function content(): Content
    {
        $event = $this->order->attendees->first()?->ticketType->event;

        return new Content(view: 'mail.zone-choice-request', with: [
            'order' => $this->order,
            'eventTitle' => $event->title ?? 'your event',
            'label' => $this->label(),
            'ticketUrl' => route('public.tickets.show', ['order' => $this->order->reference]),
        ]);
    }

    private function label(): string
    {
        $label = trim($this->order->organization->subdivision_label);

        return strtolower($label !== '' ? $label : 'Zone');
    }
}
