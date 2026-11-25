<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;

/**
 * Sends through Brevo's HTTP API (POST /v3/smtp/email) rather than its SMTP relay.
 *
 * This is how the earlier conference-platform sent mail: one HTTPS request, authenticated with the `xkeysib-` API key
 * from Brevo → SMTP & API → API keys, so no SMTP login or SMTP key is involved. It plugs into Laravel's mailer, so
 * every mailable, MailQuota slot and queue job keeps working unchanged.
 *
 * IPv4 is pinned because Brevo's API resolves IPv6 first on Vercel, where it is unreachable and the request hangs
 * until the timeout (the same fix the earlier platform needed).
 *
 * No internal retries: one request per attempt, bounded by MAIL_TIMEOUT, so a slow API fails fast inside the
 * serverless request and the existing retry (TicketEmailer backoff, queue tries) takes over.
 */
final class BrevoApiTransport implements TransportInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email',
        private readonly int $timeout = 8,
        private readonly bool $forceIpv4 = true,
    ) {}

    /**
     * @throws TransportException
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): SentMessage
    {
        $sentMessage = new SentMessage($message, $envelope ?? Envelope::create($message));

        if (!$message instanceof Message) {
            throw new TransportException('The Brevo API transport can only send Symfony Mime messages.');
        }

        try {
            $response = $this->request()->post($this->endpoint, $this->payload(MessageConverter::toEmail($message)));
        } catch (ConnectionException $exception) {
            throw new TransportException('Brevo API unreachable: ' . $exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            throw new TransportException(
                'Brevo API rejected the email (' . $response->status() . '): ' . $response->body(),
            );
        }

        $messageId = $response->json('messageId');

        if (is_string($messageId) && $messageId !== '') {
            $sentMessage->setMessageId($messageId);
        }

        return $sentMessage;
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }

    /**
     * The request Brevo expects: one sender, flat recipients, HTML and/or text, base64 attachments.
     *
     * @return array<string, mixed>
     */
    private function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? throw new TransportException('An email needs a From address.');

        $payload = [
            'sender' => $this->address($from),
            'to' => $this->addresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        /** @var array<string, array<Address>> $copies */
        $copies = ['cc' => $email->getCc(), 'bcc' => $email->getBcc(), 'replyTo' => $email->getReplyTo()];

        foreach ($copies as $key => $list) {
            if ($list === []) {
                continue;
            }

            $payload[$key] = $this->addresses($list);
        }

        if (($html = $email->getHtmlBody()) !== null) {
            $payload['htmlContent'] = $html;
        }

        if (($text = $email->getTextBody()) !== null) {
            $payload['textContent'] = $text;
        }

        if ($payload['to'] === []) {
            throw new TransportException('An email needs at least one To address.');
        }

        $headers = $this->headers($email->getHeaders());

        if ($headers !== []) {
            $payload['headers'] = $headers;
        }

        $attachments = array_map($this->attachment(...), $email->getAttachments());

        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        return $payload;
    }

    /**
     * @return array{name: string, email: string}
     */
    private function address(Address $address): array
    {
        return ['name' => $address->getName(), 'email' => $address->getAddress()];
    }

    /**
     * @param array<Address> $addresses
     *
     * @return list<array{name: string, email: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(static fn(Address $address): array => [
            'name' => $address->getName(),
            'email' => $address->getAddress(),
        ], $addresses));
    }

    /**
     * Custom headers (X-Mailer, In-Reply-To, …); the structural ones are rebuilt by the API itself.
     *
     * @return array<string, string>
     */
    private function headers(Headers $headers): array
    {
        $skip = [
            'from',
            'to',
            'cc',
            'bcc',
            'reply-to',
            'subject',
            'date',
            'message-id',
            'mime-version',
            'content-type',
            'content-transfer-encoding',
        ];
        $mapped = [];

        foreach ($headers->all() as $header) {
            if (in_array(strtolower($header->getName()), $skip, true)) {
                continue;
            }

            $mapped[$header->getName()] = $header->getBodyAsString();
        }

        return $mapped;
    }

    /**
     * The ticket PDF travels as base64 in the JSON body, exactly as Brevo's API expects.
     *
     * @return array{name: string, content: string, contentId?: string}
     */
    private function attachment(DataPart $part): array
    {
        $attachment = [
            'name' => $part->getFilename() ?? 'attachment',
            'content' => base64_encode($part->getBody()),
        ];

        // A Content-ID means an inline image the HTML refers to by cid.
        if ($part->getHeaders()->has('Content-ID')) {
            $attachment['contentId'] = trim($part->getContentId(), '<>');
        }

        return $attachment;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])->timeout($this->timeout);

        if ($this->forceIpv4 && extension_loaded('curl') && defined('CURLOPT_IPRESOLVE')) {
            $request = $request->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);
        }

        return $request;
    }
}
