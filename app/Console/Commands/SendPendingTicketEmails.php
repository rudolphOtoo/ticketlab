<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mail\TicketEmailer;
use App\Support\Scheduler\SchedulerBudget;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The cron's retry for ticket emails. They are sent right after payment is confirmed; this only picks up the ones
 * still owed (a failed send, a request killed mid-send, or one waiting for the next day's quota). Exits after one
 * indexed query when there is nothing to do.
 */
final class SendPendingTicketEmails extends Command
{
    protected $signature = 'tickets:email-pending';

    protected $description = 'Retry ticket emails that are still owed (they are normally sent right after payment)';

    public function handle(TicketEmailer $emailer): int
    {
        if (!$emailer->hasDue()) {
            $this->line('No ticket emails owed.');

            return self::SUCCESS;
        }

        $budget = SchedulerBudget::clamp((int) config('scheduler.ticket_emails.time_budget_seconds'));

        if ($budget < 1) {
            $this->warn('No time left in this run; the owed ticket emails wait for the next one.');

            return self::SUCCESS;
        }

        $outcomes = $emailer->retryDue(
            (int) config('scheduler.ticket_emails.batch_size'),
            CarbonImmutable::now()->addSeconds($budget),
        );

        ksort($outcomes);
        $this->info(
            $outcomes === []
                ? 'No ticket emails sent.'
                : implode(', ', array_map(
                    static fn(string $k, int $v): string => "{$k} {$v}",
                    array_keys($outcomes),
                    $outcomes,
                )),
        );

        return self::SUCCESS;
    }
}
