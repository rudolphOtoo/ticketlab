<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\Sms\Contracts\SmsGatewayInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queued SMS notification dispatched upon ticket order fulfillment.
 */
class SendTicketSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $orderId) {}

    public function handle(SmsGatewayInterface $sms): void
    {
        if (! $sms->isEnabled()) {
            return;
        }

        $order = Order::query()
            ->with(['attendees.ticketType', 'organization'])
            ->find($this->orderId);

        if ($order === null || blank($order->customer_phone)) {
            return;
        }

        $attendeeCount = $order->attendees->count();
        if ($attendeeCount === 0) {
            return;
        }

        $orgName = $order->organization?->name ?? 'TicketLab';
        $passUrl = route('public.tickets.show', ['order' => $order->reference]);

        $message = "Your {$orgName} ticket order #{$order->reference} ({$attendeeCount} pass(es)) is confirmed! View digital passes: {$passUrl}";

        $sent = $sms->send($order->customer_phone, $message, [
            'order_id' => $order->id,
            'order_reference' => $order->reference,
        ]);

        if (! $sent) {
            Log::warning('SendTicketSms dispatch was skipped or failed.', [
                'order_id' => $order->id,
                'phone' => $order->customer_phone,
            ]);
        }
    }
}

