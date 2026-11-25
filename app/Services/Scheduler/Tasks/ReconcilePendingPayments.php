<?php

declare(strict_types=1);

namespace App\Services\Scheduler\Tasks;

use App\Services\Payment\PendingPaymentReconciler;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;

/**
 * Orders stuck in "pending" (webhook never arrived): confirmed or closed after asking Paystack, oldest first, one
 * batch per run. A confirmed order owes its ticket email, which the ticket_emails task sends right after.
 */
final readonly class ReconcilePendingPayments implements SchedulerTask
{
    public function __construct(
        private PendingPaymentReconciler $reconciler,
    ) {}

    public function name(): string
    {
        return 'reconcile';
    }

    public function isDue(): bool
    {
        return PendingPaymentReconciler::pending(self::olderThan())->exists();
    }

    public function run(CarbonImmutable $deadline): TaskResult
    {
        $report = $this->reconciler->reconcile(
            self::olderThan(),
            (int) config('scheduler.reconcile.batch_size'),
            $deadline,
            execute: true,
        );
        $result = new TaskResult();

        if ($report['locked']) {
            // A manual `payments:reconcile-pending` is running; it does the same work.
            $result->add('locked');

            return $result;
        }

        foreach ($report['rows'] as $row) {
            if ($row['outcome'] === 'error') {
                $result->error("order {$row['reference']}: {$row['action']}");

                continue;
            }

            $result->add($row['outcome']);
        }

        if ($report['waiting'] > 0) {
            $result->add('waiting', $report['waiting']);
        }

        return $result;
    }

    private static function olderThan(): int
    {
        return (int) config('scheduler.reconcile.older_than_minutes');
    }
}
