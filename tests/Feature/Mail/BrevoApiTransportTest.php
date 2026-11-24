<?php

declare(strict_types=1);

use App\Services\Mail\BrevoApiTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
 * Brevo sends through its HTTP API (POST /v3/smtp/email) with the account's xkeysib- API key — no SMTP login or SMTP
 * key. The ticket PDF travels as base64. A rejected request throws, so the queue retries and MailQuota gives the slot
 * back instead of the email vanishing.
 */

function brevo_email(): Email
{
    return new Email()
        ->from(new Address('tickets@example.com', 'TicketLab'))
        ->to(new Address('buyer@example.com', 'Buyer Name'))
        ->subject('Your passes · TKT-123')
        ->html('<p>Here are your passes.</p>')
        ->attach('%PDF-1.4 fake ticket bytes', 'tickets-TKT-123.pdf', 'application/pdf');
}

beforeEach(function (): void {
    config(['mail.mailers.brevo.key' => 'xkeysib-test-key']);
    Mail::purge('brevo');
});

it('is the configured mailer, built from the mail config', function (): void {
    $mailer = Mail::mailer('brevo');

    expect($mailer->getSymfonyTransport())
        ->toBeInstanceOf(BrevoApiTransport::class)
        ->and((string) $mailer->getSymfonyTransport())
        ->toBe('brevo+api://api.brevo.com');
});

it('posts the message to the Brevo API with the API key and the PDF as base64', function (): void {
    Http::fake([
        'api.brevo.com/*' => Http::response(['messageId' => '<20261124@smtp-relay.mailin.fr>'], 201),
    ]);

    $sent = Mail::mailer('brevo')->getSymfonyTransport()->send(brevo_email());

    expect($sent?->getMessageId())->toBe('<20261124@smtp-relay.mailin.fr>');

    Http::assertSent(static function (Request $request): bool {
        $payload = $request->data();

        return (
            $request->method() === 'POST'
            && $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'xkeysib-test-key')
            && $payload['sender'] === ['name' => 'TicketLab', 'email' => 'tickets@example.com']
            && $payload['to'] === [['name' => 'Buyer Name', 'email' => 'buyer@example.com']]
            && $payload['subject'] === 'Your passes · TKT-123'
            && $payload['htmlContent'] === '<p>Here are your passes.</p>'
            && $payload['attachment'] === [[
                'name' => 'tickets-TKT-123.pdf',
                'content' => base64_encode('%PDF-1.4 fake ticket bytes'),
            ]]
        );
    });
});

it('throws when Brevo rejects the email, so the send is retried instead of dropped', function (): void {
    Http::fake([
        'api.brevo.com/*' => Http::response(['message' => 'sender not valid'], 400),
    ]);

    Mail::mailer('brevo')->getSymfonyTransport()->send(brevo_email());
})->throws(TransportException::class, 'Brevo API rejected the email (400)');
