<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Attendee;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders an order's admission passes as an A4 PDF, one dark-themed pass per page, mirroring the web pass.
 *
 * The same document is used for the "Save as PDF" download and the ticket email, so the two never drift apart.
 * Every asset is embedded (the QR codes are PNG data URIs), so DomPDF never fetches remote URLs.
 */
class TicketPassPdf
{
    private const string DEFAULT_ACCENT = '#10b981';

    public function __construct(
        private readonly QrCodeService $qrCodes,
    ) {}

    public function render(Order $order): DomPdf
    {
        $order->loadMissing(['organization', 'attendees.ticketType.event', 'attendees.subdivision']);

        $event = $order->attendees->first()?->ticketType?->event;
        $customization = is_array($event?->customization) ? $event->customization : [];

        return Pdf::loadView('pdf.ticket-pass', [
            'order' => $order,
            'event' => $event,
            'organization' => $order->organization,
            'accent' => self::accent($customization['accent_hex'] ?? null),
            'qrCodes' => $order->attendees->mapWithKeys(fn(Attendee $attendee): array => [
                $attendee->id => $this->qrDataUri($attendee),
            ]),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption([
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
    }

    /**
     * A high-resolution PNG (12px per module) so the code stays crisp when the PDF is printed or zoomed.
     */
    public function qrDataUri(Attendee $attendee): string
    {
        return $this->qrCodes->pngDataUri($attendee->qr_code_hash, 12, 2);
    }

    public static function fileName(Order $order): string
    {
        return 'tickets-' . (string) preg_replace('/[^A-Za-z0-9_-]/', '', $order->reference) . '.pdf';
    }

    /**
     * The event accent is admin input that lands inside a style block, so only a plain hex colour is accepted.
     */
    private static function accent(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : self::DEFAULT_ACCENT;
    }
}
