<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AttendeeIssuer
{
    /**
     * Issue one QR pass per ticket for every sales channel: online checkout, the cash desk, and comps.
     *
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, Attendee>
     */
    public function issue(Order $order, TicketType $ticketType, int $quantity, array $metadata): Collection
    {
        /** @var list<Attendee> $passes */
        $passes = [];

        $attendeeNames = $metadata['attendee_names'] ?? [];
        $attendeeEmails = $metadata['attendee_emails'] ?? [];
        $customFields = $metadata['custom_fields'] ?? $metadata['form_responses'] ?? [];

        for ($index = 0; $index < $quantity; $index++) {
            $name =
                $this->attendeeValue($attendeeNames, $index)
                ?? $order->customer_name . ($quantity > 1 ? ' #' . ($index + 1) : '');

            $email = $this->attendeeValue($attendeeEmails, $index) ?? $order->customer_email;

            $passes[] = Attendee::query()->create([
                'order_id' => $order->getKey(),
                'organization_id' => $order->organization_id,
                'subdivision_id' => $order->subdivision_id,
                'ticket_type_id' => $ticketType->getKey(),
                'name' => $name,
                'email' => $email,
                'phone' => $order->customer_phone,
                'qr_code_hash' => $this->generateQrHash($order, $index),
                'checked_in_at' => null,
                'checked_in_by' => null,
                'custom_fields' => is_array($customFields) ? $customFields : null,
            ]);
        }

        return Collection::make($passes);
    }

    private function generateQrHash(Order $order, int $index): string
    {
        return hash('sha256', implode('|', [
            $order->reference,
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
