<?php

declare(strict_types=1);

namespace App\Services\Scheduler\Tasks;

use App\Services\Mail\TicketEmailer;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;

/**
 * Ticket emails still owed: a failed send, a request killed mid-send, a payment reconcile just confirmed, or one that
 * waited for the next day's quota. TicketEmailer claims each order with one conditional UPDATE (never sent twice),
 * takes a slot of the ticket share of the provider's daily cap (BREVO_DAILY_LIMIT) and sends through the configured
 * mailer (MAIL_MAILER=brevo in production). Suspended and archived organisations' emails wait.
 */
final readonly class SendOwedTicketEmails implements SchedulerTask
{
    public function __construct(
        private TicketEmailer $emailer,
    ) {}

    public function name(): string
    {
        return 'ticket_emails';
    }

    public function isDue(): bool
    {
        return $this->emailer->hasDue();
    }

    public function run(CarbonImmutable $deadline): TaskResult
    {
        // sent, deferred (daily cap), failed (retried with backoff), gave_up, skipped; failures are logged by the
        // emailer with the order reference.
        return new TaskResult($this->emailer->retryDue((int) config('scheduler.ticket_emails.batch_size'), $deadline));
    }
}
