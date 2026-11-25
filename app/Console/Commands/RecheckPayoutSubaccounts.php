<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\PayoutSubaccountRechecker;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Manual run of the scheduler's payout_recheck task (PayoutSubaccountRechecker explains what it does). */
class RecheckPayoutSubaccounts extends Command
{
    protected $signature = 'payouts:recheck-subaccounts';

    protected $description = 'Re-verify the zones due for a payout subaccount check with Paystack and alert organisation admins about invalid ones';

    public function handle(PayoutSubaccountRechecker $rechecker): int
    {
        $report = $rechecker->recheck(
            (int) config('scheduler.payout_recheck.batch_size'),
            CarbonImmutable::now()->addSeconds(max(1, (int) config('scheduler.payout_recheck.time_budget_seconds'))),
        );
        ['verified' => $verified, 'rejected' => $rejected, 'unreachable' => $unreachable] = $report['counts'];

        $this->info("Verified {$verified}, rejected {$rejected}, unreachable {$unreachable}.");

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
