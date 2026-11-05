<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TicketIssuedMail;
use App\Models\Order;
use App\Services\Payment\TicketPassPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Renders the buyer's branded PDF passes (one scannable QR per attendee) and emails them once the order is paid.
 */
class SendTicketEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 60;

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(TicketPassPdf $passes): void
    {
        $order = Order::query()
            ->with(['attendees.ticketType.event', 'attendees.subdivision', 'organization'])
            ->find($this->orderId);

        if (
            $order === null
            || $order->attendees->isEmpty()
            || !filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        $pdf = $passes->render($order);

        Mail::to($order->customer_email, $order->customer_name)->send(new TicketIssuedMail($order, $pdf->output()));
    }
}
