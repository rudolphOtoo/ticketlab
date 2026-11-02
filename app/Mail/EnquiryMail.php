<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EnquiryMail extends Mailable
{
    /**
     * @param array{name: string, email: string, subject: string, message: string} $enquiry
     */
    public function __construct(
        public array $enquiry,
        public string $organizationName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(replyTo: [new Address(
            $this->enquiry['email'],
            $this->enquiry['name'],
        )], subject: "New enquiry: {$this->enquiry['subject']}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enquiry', with: [
            'enquiry' => $this->enquiry,
            'organizationName' => $this->organizationName,
        ]);
    }
}
