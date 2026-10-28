<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Str;

class AttendeeIssuer
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function issue(Order $order, TicketType $ticketType, int $quantity, array $metadata): void
    {
        $attendeeNames = $metadata['attendee_names'] ?? [];
        $attendeeEmails = $metadata['attendee_emails'] ?? [];
        $customFields = $metadata['custom_fields'] ?? $metadata['form_responses'] ?? [];

        for ($index = 0; $index < $quantity; $index++) {
            $name =
                $this->attendeeValue($attendeeNames, $index)
                ?? $order->customer_name . ($quantity > 1 ? ' #' . ($index + 1) : '');

            $email = $this->attendeeValue($attendeeEmails, $index) ?? $order->customer_email;

            Attendee::query()->create([
                'order_id' => $order->getKey(),
                'organization_id' => $order->organization_id,
                'subdivision_id' => $order->subdivision_id,
                'ticket_type_id' => $ticketType->getKey(),
                'name' => $name,
                'email' => $email,
                'phone' => $order->customer_phone,
                'qr_code_hash' => $this->generateQrHash($order, $index),
                'custom_fields' => $metadata['custom_fields'] ?? null,
                'checked_in_at' => null,
                'checked_in_by' => null,
                'custom_fields' => is_array($customFields) ? $customFields : null,
            ]);
        }
    }

    private function generateQrHash(Order $order, int $index): string
    {
        return hash('sha256', implode('|', [
            (string) $order->reference,
            (string) $order->getKey(),
            (string) $index,
            Str::uuid()->toString(),
            (string) now()->getTimestampMs(),
        ]));
    }

    private function attendeeValue(mixed $values, int $index): ?string
    {
        if (!is_array($values)) {
            return null;
        }

        $value = $values[$index] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
