<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Support\ZoneVenue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketIssuedMail extends Mailable
{
    public function __construct(
        public Order $order,
        private readonly string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your passes for {$this->eventTitle()} · {$this->order->reference}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-issued', with: [
            'order' => $this->order,
            'eventTitle' => $this->eventTitle(),
            'venue' => $this->venue(),
            'ticketUrl' => route('public.tickets.show', ['order' => $this->order->reference]),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn(): string => $this->pdf, "tickets-{$this->order->reference}.pdf")->withMime(
                'application/pdf',
            ),
        ];
    }

    /**
     * The buyer's zone venue and time, so a Cape Coast buyer is never sent to the Kumasi hall.
     */
    private function venue(): ?ZoneVenue
    {
        $event = $this->order->attendees->first()?->ticketType->event;

        return $event === null ? null : ZoneVenue::for($event, $this->order->subdivision);
    }

    private function eventTitle(): string
    {
        $firstPass = $this->order->attendees->first();

        return $firstPass === null ? 'your event' : $firstPass->ticketType->event->title;
    }
}
