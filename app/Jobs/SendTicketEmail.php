<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Mail\TicketEmailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kept so ticket-email jobs queued before this release still run after it deploys. New ticket emails are sent by
 * TicketEmailer right after payment is confirmed, and retried by `tickets:email-pending`.
 */
class SendTicketEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(TicketEmailer $emailer): void
    {
        $emailer->sendSoon($this->orderId);
    }
}
