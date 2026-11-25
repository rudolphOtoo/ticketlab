<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Settles orders stuck in "pending" because the browser closed and the webhook never arrived (or failed). Used by
 * the scheduler (reconcile task) and by `payments:reconcile-pending` (dry run by default).
 *
 * For each pending order older than $olderThanMinutes it asks Paystack's verify endpoint:
 *  - success: confirmed through exactly the webhook's path (same amount, currency and subaccount checks, same row
 *    lock, idempotent: an order the webhook already settled is left as it is);
 *  - failed / reversed / abandoned, or unknown to Paystack once its seat hold has lapsed: the order is closed and its
 *    seats are released;
 *  - anything else (still processing) or Paystack unreachable: left for the next run.
 *
 * Oldest first, at most one batch, until the deadline. One order's error is logged and the others continue.
 */
final class PendingPaymentReconciler
{
    public const string LOCK = 'payments:reconcile-pending';

    private const array RELEASE_STATUSES = [
        'failed' => OrderStatus::Failed,
        'reversed' => OrderStatus::Failed,
        'abandoned' => OrderStatus::Cancelled,
    ];

    private bool $execute = false;

    public function __construct(
        private readonly PaystackGateway $paystack,
    ) {}

    /**
     * Pending orders old enough to reconcile. Suspended and archived organisations are skipped (Paystack webhooks
     * still confirm their payments).
     *
     * @return Builder<Order>
     */
    public static function pending(int $olderThanMinutes): Builder
    {
        return Order::query()
            ->where('payment_status', OrderStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes(max(1, $olderThanMinutes)))
            ->whereIn('organization_id', Organization::activeIds());
    }

    /**
     * @return array{
     *     locked: bool,
     *     waiting: int,
     *     rows: list<array{reference: string, created: ?string, paystack: string, action: string, outcome: string}>,
     * }
     *     outcome: confirmed, released, review, skipped, unreachable, error, or dry_run
     */
    public function reconcile(int $olderThanMinutes, int $batch, CarbonImmutable $deadline, bool $execute): array
    {
        // Guards a manual run against the scheduled one; expires on its own if the request is killed.
        $store = Cache::store((string) config('scheduler.lock_store'))->getStore();

        if (!$store instanceof LockProvider) {
            throw new RuntimeException('SCHEDULER_LOCK_STORE must support locks: use database or redis.');
        }

        $lock = $store->lock(self::LOCK, 600);

        if (!$lock->get()) {
            return ['locked' => true, 'waiting' => 0, 'rows' => []];
        }

        try {
            return ['locked' => false, ...$this->reconcileBatch($olderThanMinutes, $batch, $deadline, $execute)];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{
     *     waiting: int,
     *     rows: list<array{reference: string, created: ?string, paystack: string, action: string, outcome: string}>,
     * }
     */
    private function reconcileBatch(int $olderThanMinutes, int $batch, CarbonImmutable $deadline, bool $execute): array
    {
        $this->execute = $execute;
        $rows = [];

        $orders = self::pending($olderThanMinutes)->orderBy('created_at')->orderBy('id')->limit(max(1, $batch))->get();

        foreach ($orders as $order) {
            if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                return ['waiting' => $orders->count() - count($rows), 'rows' => $rows];
            }

            try {
                [$status, $action, $outcome] = $this->reconcileOne($order);
            } catch (Throwable $exception) {
                Log::error('Could not reconcile a pending order; it is retried on the next run.', [
                    'task' => 'reconcile',
                    'organization_id' => $order->organization_id,
                    'order_reference' => $order->reference,
                    'error' => $exception->getMessage(),
                ]);
                [$status, $action, $outcome] = ['error', 'skip (error: ' . $exception->getMessage() . ')', 'error'];
            }

            $created = $order->created_at?->diffForHumans();
            $rows[] = [
                'reference' => $order->reference,
                'created' => is_string($created) ? $created : null,
                'paystack' => $status,
                'action' => $action,
                'outcome' => $this->execute ? $outcome : 'dry_run',
            ];
        }

        return ['waiting' => 0, 'rows' => $rows];
    }

    /**
     * @return array{string, string, string} Paystack status, what was done, outcome
     */
    private function reconcileOne(Order $order): array
    {
        $reference = $order->gatewayReference();

        try {
            $data = $this->paystack->verify($reference);
            $status = is_string($data['status'] ?? null) ? $data['status'] : 'unknown';
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                return ['unreachable', 'skip (try next run)', 'unreachable'];
            }

            // Paystack never saw a payment attempt. Only close it once its seat hold can no longer be paid for.
            return (
                $this->holdStillLive($order)
                    ? ['not found', 'skip (seat hold still live)', 'skipped']
                    : ['not found', ...$this->release($order, OrderStatus::Cancelled)]
            );
        } catch (Throwable $exception) {
            Log::warning('Pending order reconciliation could not reach Paystack.', [
                'organization_id' => $order->organization_id,
                'order_reference' => $order->reference,
                'error' => $exception->getMessage(),
            ]);

            return ['unreachable', 'skip (try next run)', 'unreachable'];
        }

        if ($status === 'success') {
            return [$status, ...$this->confirmPayment($order, $data)];
        }

        if (array_key_exists($status, self::RELEASE_STATUSES)) {
            return [$status, ...$this->release($order, self::RELEASE_STATUSES[$status])];
        }

        return [$status, 'skip (still in progress)', 'skipped'];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{string, string}
     */
    private function confirmPayment(Order $order, array $data): array
    {
        if (!$this->execute) {
            return ['confirm and issue passes', 'confirmed'];
        }

        try {
            // The webhook's own path: same matching rules, same row lock; a no-op for an already settled order.
            $settled = $this->paystack->handleChargeSuccess($data);
        } catch (OrderFulfillmentException $exception) {
            Log::critical('Reconciled payment needs manual review.', [
                'organization_id' => $order->organization_id,
                'order_reference' => $order->reference,
                'reason' => $exception->getMessage(),
            ]);

            return ['held for review: ' . $exception->getMessage(), 'review'];
        }

        Log::info('Pending order confirmed by reconciliation.', [
            'order_reference' => $order->reference,
            'organization_id' => $order->organization_id,
        ]);

        return (
            $settled === null
                ? ['nothing to confirm', 'skipped']
                : ['confirmed (' . $settled->attendees->count() . ' pass(es))', 'confirmed']
        );
    }

    /**
     * Closes the order and returns its held seats. Locks the tier, then the reservation, then the order (the same
     * order as checkout and the expiry task), and does nothing if a webhook has settled the order in the meantime.
     *
     * @return array{string, string}
     */
    private function release(Order $order, OrderStatus $outcome): array
    {
        if (!$this->execute) {
            return ['close as ' . $outcome->value . ' and release seats', 'released'];
        }

        return DB::transaction(static function () use ($order, $outcome): array {
            $reservation = $order->reservation_id === null
                ? null
                : Reservation::query()->select(['id', 'ticket_type_id'])->find($order->reservation_id);
            $tier = $reservation === null
                ? null
                : TicketType::query()->whereKey($reservation->ticket_type_id)->lockForUpdate()->first();
            $hold = $reservation === null
                ? null
                : Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->payment_status !== OrderStatus::Pending) {
                return ['already settled elsewhere, left as is', 'skipped'];
            }

            $locked->forceFill(['payment_status' => $outcome])->save();
            $seats = 0;

            if ($hold !== null && $hold->status === ReservationStatus::Active) {
                $hold->update(['status' => ReservationStatus::Released]);
                $tier?->restock((int) $hold->quantity);
                $seats = (int) $hold->quantity;
            }

            Log::info('Pending order closed by reconciliation.', [
                'organization_id' => $locked->organization_id,
                'order_reference' => $locked->reference,
                'status' => $outcome->value,
                'seats_released' => $seats,
            ]);

            return ["closed as {$outcome->value}, {$seats} seat(s) released", 'released'];
        });
    }

    private function holdStillLive(Order $order): bool
    {
        return (
            $order->reservation_id !== null
            && Reservation::query()
                ->whereKey($order->reservation_id)
                ->where('status', ReservationStatus::Active)
                ->where('expires_at', '>', now())
                ->exists()
        );
    }
}
