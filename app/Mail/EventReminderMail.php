<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Broadcast;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventReminderMail extends Mailable
{
    public function __construct(
        public Broadcast $broadcast,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->broadcast->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-reminder', with: [
            'broadcast' => $this->broadcast,
            'event' => $this->broadcast->event,
            'organizationName' => $this->broadcast->organization->name,
            'recipientName' => $this->recipientName,
        ]);
    }
}
