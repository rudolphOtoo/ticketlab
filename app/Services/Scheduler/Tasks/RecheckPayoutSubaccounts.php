<?php

declare(strict_types=1);

namespace App\Services\Scheduler\Tasks;

use App\Services\Payment\PayoutSubaccountRechecker;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;

/** Zones' Paystack subaccounts, re-verified about once a day each, a small batch per run. */
final readonly class RecheckPayoutSubaccounts implements SchedulerTask
{
    public function __construct(
        private PayoutSubaccountRechecker $rechecker,
    ) {}

    public function name(): string
    {
        return 'payout_recheck';
    }

    public function isDue(): bool
    {
        return PayoutSubaccountRechecker::due()->exists();
    }

    public function run(CarbonImmutable $deadline): TaskResult
    {
        $report = $this->rechecker->recheck((int) config('scheduler.payout_recheck.batch_size'), $deadline);
        $result = new TaskResult($report['counts']);

        foreach ($report['errors'] as $error) {
            $result->error($error);
        }

        return $result;
    }
}
