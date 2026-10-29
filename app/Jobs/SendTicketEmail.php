<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TicketIssuedMail;
use App\Models\Attendee;
use App\Models\Order;
use App\Services\Payment\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
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

    public function handle(QrCodeService $qrCodes): void
    {
        $order = Order::query()->with(['attendees.ticketType.event', 'organization'])->find($this->orderId);

        if (
            $order === null
            || $order->attendees->isEmpty()
            || !filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        $event = $order->attendees->firstOrFail()->ticketType->event;
        $customization = is_array($event->customization) ? $event->customization : [];

        $pdf = Pdf::loadView('pdf.ticket', [
            'order' => $order,
            'event' => $event,
            'organizationName' => $order->organization->name,
            'accent' => (string) ($customization['accent_hex'] ?? '#18181b'),
            'qrCodes' => $order->attendees->mapWithKeys(static fn(Attendee $attendee): array => [
                $attendee->id => $qrCodes->pngDataUri($attendee->qr_code_hash, 8, 2),
            ]),
        ])->setPaper('a5');

        Mail::to($order->customer_email, $order->customer_name)->send(new TicketIssuedMail($order, $pdf->output()));
    }
}
