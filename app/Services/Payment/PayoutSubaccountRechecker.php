<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\PayoutAccountRejected;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Daily re-check of every zone's Paystack subaccount against the active key (scheduler payout_recheck task and
 * `payouts:recheck-subaccounts`).
 *
 * Paystack definitively rejects the code: the zone stops taking payments and its organisation admins are emailed.
 * Paystack cannot be reached: the last stored verdict stands, so a network error never pauses sales.
 */
final readonly class PayoutSubaccountRechecker
{
    public function __construct(
        private PaystackSubaccountResolver $subaccounts,
    ) {}

    /**
     * Zones whose last check is older than interval_hours, in active organisations only.
     *
     * @return Builder<Subdivision>
     */
    public static function due(): Builder
    {
        $dueBefore = now()->subHours((int) config('scheduler.payout_recheck.interval_hours'));

        return Subdivision::query()
            ->whereNotNull('paystack_subaccount_code')
            ->whereIn('organization_id', Organization::activeIds())
            ->where(
                static fn($query) => $query->whereNull('payout_checked_at')->orWhere(
                    'payout_checked_at',
                    '<',
                    $dueBefore,
                ),
            );
    }

    /**
     * Zones checked longest ago first, a small batch per run, so each run fits the serverless time limit and every
     * zone still comes round about once a day.
     *
     * @return array{counts: array{verified: int, rejected: int, unreachable: int}, errors: list<string>}
     */
    public function recheck(int $batch, CarbonImmutable $deadline): array
    {
        $counts = ['verified' => 0, 'rejected' => 0, 'unreachable' => 0];
        $errors = [];

        $zones = self::due()
            ->with('organization')
            ->orderByRaw('payout_checked_at is not null')
            ->orderBy('payout_checked_at')
            ->orderBy('id')
            ->limit(max(1, $batch))
            ->get();

        foreach ($zones as $zone) {
            if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                break;
            }

            try {
                $counts[$this->recheckZone($zone)]++;
            } catch (Throwable $exception) {
                Log::error('Could not re-check a payout subaccount; it is retried on the next run.', [
                    'task' => 'payout_recheck',
                    'organization_id' => $zone->organization_id,
                    'subdivision_id' => $zone->id,
                    'error' => $exception->getMessage(),
                ]);
                $errors[] = "zone {$zone->id}: {$exception->getMessage()}";
            }
        }

        return ['counts' => $counts, 'errors' => $errors];
    }

    /**
     * @return 'verified'|'unreachable'|'rejected'
     */
    private function recheckZone(Subdivision $zone): string
    {
        $wasPayable = $this->subaccounts->forSubdivision($zone) !== null;
        $result = $this->subaccounts->verify($zone);

        if ($result['recognised'] === true) {
            return 'verified';
        }

        if ($result['recognised'] === null) {
            Log::warning('Payout subaccount re-check could not reach Paystack; keeping the last verdict.', [
                'organization_id' => $zone->organization_id,
                'subdivision_id' => $zone->id,
            ]);

            return 'unreachable';
        }

        $reason = (string) $this->subaccounts->rejectionReason($result);
        Log::error('Payout subaccount rejected on re-check; the zone can no longer take payments.', [
            'organization_id' => $zone->organization_id,
            'subdivision_id' => $zone->id,
            'reason' => $reason,
        ]);

        // Alert once, when a working zone stops working; an already-broken zone is not re-announced every run.
        if ($wasPayable) {
            Notification::send(
                User::query()
                    ->where('organization_id', $zone->organization_id)
                    ->where('role', 'admin')
                    ->whereNull('subdivision_id')
                    ->get(),
                new PayoutAccountRejected($zone, $reason),
            );
        }

        return 'rejected';
    }
}
