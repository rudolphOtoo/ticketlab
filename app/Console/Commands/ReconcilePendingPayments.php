<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\PendingPaymentReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Manual run of the scheduler's reconcile task (PendingPaymentReconciler explains what it does). Dry run by default.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile-pending
        {--older-than=10 : Only orders pending for at least this many minutes}
        {--execute : Actually confirm or release (otherwise a dry run that changes nothing)}';

    protected $description = 'Confirm or release orders stuck in pending by asking Paystack (dry run by default)';

    public function handle(PendingPaymentReconciler $reconciler): int
    {
        $execute = (bool) $this->option('execute');
        $report = $reconciler->reconcile(
            max(1, (int) (is_numeric($this->option('older-than')) ? $this->option('older-than') : 10)),
            (int) config('scheduler.reconcile.batch_size'),
            CarbonImmutable::now()->addSeconds(max(1, (int) config('scheduler.reconcile.time_budget_seconds'))),
            $execute,
        );

        if ($report['locked']) {
            $this->warn('Another reconciliation run is in progress; skipping this one.');

            return self::SUCCESS;
        }

        if ($report['waiting'] > 0) {
            $this->warn("Time budget reached; {$report['waiting']} order(s) wait for the next run.");
        }

        $this->table(['Order', 'Created', 'Paystack', $execute ? 'Done' : 'Would do'], array_map(
            static fn(array $row): array => [
                $row['reference'],
                $row['created'],
                $row['paystack'],
                $row['action'],
            ],
            $report['rows'],
        ));
        $this->info(
            $execute
                ? 'Reconciled ' . count($report['rows']) . ' pending order(s).'
                : 'Dry run: nothing was changed. Re-run with --execute.',
        );

        return self::SUCCESS;
    }
}
