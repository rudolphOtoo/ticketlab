<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payment\TicketPassPdf;
use Illuminate\Http\Response;

/**
 * Downloads an order's admission passes as a PDF. Only paid orders with issued passes have a document to download.
 */
class TicketPassPdfController extends Controller
{
    public function __invoke(Order $order, TicketPassPdf $passes): Response
    {
        abort_unless($order->payment_status === OrderStatus::Paid && $order->attendees()->exists(), 404);

        $response = $passes->render($order)->download(TicketPassPdf::fileName($order));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
