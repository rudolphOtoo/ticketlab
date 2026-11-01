<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\OrderFulfillmentException;
use App\Services\Payment\PaystackGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaystackGateway $paystack): JsonResponse
    {
        $header = $request->header('x-paystack-signature');
        $signature = is_string($header) ? $header : null;

        if (!$paystack->verifySignature($request->getContent(), $signature)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $event = $request->input('event');

        if ($event !== 'charge.success') {
            return response()->json([
                'status' => 'ignored',
                'event' => $event,
            ]);
        }

        $data = $request->input('data', []);

        if (!is_array($data) || ($data['reference'] ?? null) === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing transaction reference.',
            ], 422);
        }

        try {
            /** @var array<string, mixed> $data */
            $order = $paystack->handleChargeSuccess($data);
        } catch (OrderFulfillmentException) {
            return response()->json([
                'status' => 'requires_review',
                'message' => 'The payment was received but fulfilment requires manual review.',
            ], 409);
        }

        return response()->json([
            'status' => $order === null ? 'ignored' : 'success',
            'order' => $order?->reference,
        ]);
    }
}
