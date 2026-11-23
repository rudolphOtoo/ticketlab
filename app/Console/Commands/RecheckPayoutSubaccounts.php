<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\PayoutAccountRejected;
use App\Services\Payment\PaystackSubaccountResolver;
use App\Support\Scheduler\SchedulerBudget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Daily re-check of every zone's Paystack subaccount against the active key.
 *
 * Paystack definitively rejects the code: the zone stops taking payments and its organisation admins are emailed.
 * Paystack cannot be reached: the last stored verdict stands, so a network error never pauses sales.
 */
class RecheckPayoutSubaccounts extends Command
{
    protected $signature = 'payouts:recheck-subaccounts';

    protected $description = 'Re-verify the zones due for a payout subaccount check with Paystack and alert organisation admins about invalid ones';

    public function handle(PaystackSubaccountResolver $subaccounts): int
    {
        $counts = ['verified' => 0, 'rejected' => 0, 'unreachable' => 0];

        // Cut to what is left of the scheduler run, when it runs inside one.
        $deadline = now()->addSeconds(max(
            1,
            SchedulerBudget::clamp((int) config('scheduler.payout_recheck.time_budget_seconds')),
        ));
        $dueBefore = now()->subHours((int) config('scheduler.payout_recheck.interval_hours'));

        // Zones checked longest ago first, a small batch per run, so each run fits the serverless time limit and
        // every zone still comes round about once a day.
        $zones = Subdivision::query()
            ->whereNotNull('paystack_subaccount_code')
            ->whereIn('organization_id', Organization::activeIds())
            ->where(
                static fn($query) => $query->whereNull('payout_checked_at')->orWhere(
                    'payout_checked_at',
                    '<',
                    $dueBefore,
                ),
            )
            ->with('organization')
            ->orderByRaw('payout_checked_at is not null')
            ->orderBy('payout_checked_at')
            ->orderBy('id')
            ->limit(max(1, (int) config('scheduler.payout_recheck.batch_size')))
            ->get();

        foreach ($zones as $zone) {
            if (now()->greaterThanOrEqualTo($deadline)) {
                $this->warn('Time budget reached; the remaining zones are re-checked on the next run.');

                break;
            }

            $counts[self::recheck($zone, $subaccounts)]++;
        }

        $this->info(
            "Verified {$counts['verified']}, rejected {$counts['rejected']}, unreachable {$counts['unreachable']}.",
        );

        return self::SUCCESS;
    }

    /**
     * Re-verifies one zone and returns the outcome: "verified", "unreachable" or "rejected".
     */
    private static function recheck(Subdivision $zone, PaystackSubaccountResolver $subaccounts): string
    {
        $wasPayable = $subaccounts->forSubdivision($zone) !== null;
        $result = $subaccounts->verify($zone);

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

        $reason = (string) $subaccounts->rejectionReason($result);
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
