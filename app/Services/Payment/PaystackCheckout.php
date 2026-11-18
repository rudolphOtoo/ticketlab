<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Subdivision;
use App\Services\PaystackFeeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Opens the Paystack transaction for a pending order on the server, with the zone payout split fixed there.
 *
 * Every paid order belongs to a zone with a verified subaccount (otherwise checkout is refused). The zone receives exactly the ticket value:
 * the main account keeps a flat transaction_charge of (platform fee + processing fee) and bears Paystack's fee,
 * which the buyer already covered through the gross-up. This overrides whatever percentage the subaccount was
 * created with, so payouts never depend on dashboard settings.
 */
class PaystackCheckout
{
    private const int MAX_ATTEMPTS = 5;

    /** Paystack sessions are only resumed while the seat hold could still be valid. */
    private const int RESUMABLE_SECONDS = 3_600;

    public function __construct(
        private readonly PaystackGateway $paystack,
        private readonly PaystackSubaccountResolver $subaccounts,
    ) {}

    /**
     * @param array<string, mixed> $metadata Shown on the Paystack transaction record.
     *
     * @return array{access_code: string, reference: string}
     */
    public function begin(Order $order, ?Subdivision $zone, array $metadata = []): array
    {
        $subaccount = $this->payoutSubaccount($order, $zone);
        $terms = $this->terms($order, $subaccount, $metadata);
        $fingerprint = hash(
            'sha256',
            (string) json_encode([$terms, sha1((string) config('services.paystack.secret_key'))]),
        );

        try {
            // One opener per order: two tabs racing would otherwise burn references and orphan a popup.
            return Cache::lock(
                'paystack-checkout:' . $order->getKey(),
                30,
            )->block(10, fn(): array => $this->openOrResume($order->refresh(), $subaccount, $terms, $fingerprint));
        } catch (LockTimeoutException) {
            throw new PaystackCheckoutException('Your payment is already being prepared. Please try again.');
        }
    }

    /**
     * @param array<string, mixed> $terms
     *
     * @return array{access_code: string, reference: string}
     */
    private function openOrResume(Order $order, ?string $subaccount, array $terms, string $fingerprint): array
    {
        $previous = $order->metadata['paystack'] ?? [];

        // Re-opening an unchanged checkout resumes the same Paystack transaction instead of minting another.
        if ($this->resumable($previous, $fingerprint)) {
            return ['access_code' => $previous['access_code'], 'reference' => $order->gatewayReference()];
        }

        $attempt = is_int($previous['attempt'] ?? null) ? $previous['attempt'] : 0;
        [$session, $attempt] = $this->open($order, $terms, $attempt);

        $this->record($order, $subaccount, [
            'reference' => $session['reference'],
            'access_code' => $session['access_code'],
            'attempt' => $attempt,
            'fingerprint' => $fingerprint,
            'subaccount' => $subaccount,
            'transaction_charge_pesewas' => $terms['transaction_charge'] ?? null,
            'mode' => $this->subaccounts->mode(),
            'initialized_at' => now()->format(DATE_ATOM),
        ]);

        return $session;
    }

    /**
     * @param array<string, mixed> $previous
     *
     * @phpstan-assert-if-true array{access_code: string} $previous
     */
    private function resumable(array $previous, string $fingerprint): bool
    {
        $initializedAt = $previous['initialized_at'] ?? null;

        return (
            ($previous['fingerprint'] ?? null) === $fingerprint
            && is_string($previous['access_code'] ?? null)
            && is_string($initializedAt)
            && CarbonImmutable::parse($initializedAt)->addSeconds(self::RESUMABLE_SECONDS)->isFuture()
        );
    }

    /**
     * Paystack never reuses a reference, so each new attempt pays against "{reference}.{attempt}".
     *
     * @param array<string, mixed> $terms
     *
     * @return array{array{access_code: string, reference: string}, int}
     */
    private function open(Order $order, array $terms, int $attempt): array
    {
        for ($try = 0; $try < self::MAX_ATTEMPTS; $try++) {
            $attempt++;
            $reference = $attempt === 1 ? $order->reference : $order->reference . '.' . $attempt;

            try {
                return [$this->paystack->initialize([...$terms, 'reference' => $reference]), $attempt];
            } catch (PaystackCheckoutException $exception) {
                if (!$exception->isDuplicateReference()) {
                    throw $exception;
                }
            }
        }

        throw new PaystackCheckoutException('We could not start this payment. Please try again shortly.');
    }

    /**
     * Every paid order settles into its zone's own verified Paystack subaccount. There is no fallback to the
     * platform's main account: an order without a zone, or whose zone cannot take payments, is refused and logged.
     */
    private function payoutSubaccount(Order $order, ?Subdivision $zone): ?string
    {
        // Free orders move no money, so there is nothing to route.
        if ($order->gross_pesewas <= 0) {
            return null;
        }

        $subaccount = $this->subaccounts->forSubdivision($zone);

        if ($subaccount !== null) {
            return $subaccount;
        }

        Log::warning('Checkout refused: the order has no zone with a verified payout subaccount.', [
            'order_reference' => $order->reference,
            'organization_id' => $order->organization_id,
            'subdivision_id' => $zone?->id,
            'subaccount' => $zone?->paystack_subaccount_code,
            'paystack_mode' => $this->subaccounts->mode(),
        ]);

        throw new PaystackCheckoutException(self::unpayableZoneMessage($zone));
    }

    /**
     * What the buyer is told when their zone cannot be paid (no zone, no verified subaccount, or Paystack unreachable).
     */
    public static function unpayableZoneMessage(?Subdivision $zone): string
    {
        return $zone === null
            ? 'Online payments are not open for this event yet. Please contact the organisers.'
            : "Online payments for {$zone->name} are unavailable right now. Please try again later or contact the organisers.";
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function terms(Order $order, ?string $subaccount, array $metadata): array
    {
        $terms = [
            'email' => $order->customer_email,
            'amount' => $order->gross_pesewas,
            'currency' => strtoupper($order->currency),
            'metadata' => ['order_reference' => $order->reference, ...$metadata],
        ];

        if ($subaccount === null) {
            return $terms;
        }

        return [
            ...$terms,
            'subaccount' => $subaccount,
            'transaction_charge' => max(
                0,
                $order->gross_pesewas - PaystackFeeCalculator::toPesewas($order->net_amount),
            ),
            'bearer' => 'account',
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function record(Order $order, ?string $subaccount, array $state): void
    {
        DB::transaction(static function () use ($order, $subaccount, $state): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->payment_status !== OrderStatus::Pending) {
                throw new PaystackCheckoutException(
                    'This checkout already has a payment that is under review or completed.',
                );
            }

            $locked->forceFill([
                'payout_subaccount_code' => $subaccount,
                'metadata' => [...($locked->metadata ?? []), 'paystack' => $state],
            ])->save();
        });

        $order->refresh();
    }
}
