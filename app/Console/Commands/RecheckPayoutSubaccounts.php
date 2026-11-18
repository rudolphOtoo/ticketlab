<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\PayoutAccountRejected;
use App\Services\Payment\PaystackSubaccountResolver;
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

    protected $description = 'Re-verify every zone payout subaccount with Paystack and alert organisation admins about invalid ones';

    public function handle(PaystackSubaccountResolver $subaccounts): int
    {
        $counts = ['verified' => 0, 'rejected' => 0, 'unreachable' => 0];

        Subdivision::query()
            ->whereNotNull('paystack_subaccount_code')
            ->with('organization')
            ->orderBy('id')
            ->each(static function (Subdivision $zone) use ($subaccounts, &$counts): void {
                $wasPayable = $subaccounts->forSubdivision($zone) !== null;
                $result = $subaccounts->verify($zone);

                if ($result['recognised'] === true) {
                    $counts['verified']++;

                    return;
                }

                if ($result['recognised'] === null) {
                    $counts['unreachable']++;
                    Log::warning('Payout subaccount re-check could not reach Paystack; keeping the last verdict.', [
                        'organization_id' => $zone->organization_id,
                        'subdivision_id' => $zone->id,
                    ]);

                    return;
                }

                $counts['rejected']++;
                $reason = (string) $subaccounts->rejectionReason($result);
                Log::error('Payout subaccount rejected on re-check; the zone can no longer take payments.', [
                    'organization_id' => $zone->organization_id,
                    'subdivision_id' => $zone->id,
                    'reason' => $reason,
                ]);

                // Alert once, when a working zone stops working; an already-broken zone is not re-announced daily.
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
            });

        $this->info(
            "Verified {$counts['verified']}, rejected {$counts['rejected']}, unreachable {$counts['unreachable']}.",
        );

        return self::SUCCESS;
    }
}
