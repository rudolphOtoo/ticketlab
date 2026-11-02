<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
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

    private function eventTitle(): string
    {
        $firstPass = $this->order->attendees->first();

        return $firstPass === null ? 'your event' : $firstPass->ticketType->event->title;
    }
}
