<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Jobs\SendEventReminders;
use App\Models\Broadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

/**
 * Queues one email per recipient of a broadcast, spread over as many days as the daily cap needs (BroadcastSchedule),
 * as one batch that can be cancelled from the Broadcast Hub.
 */
final readonly class BroadcastSender
{
    public function __construct(
        private MailQuota $quota,
    ) {}

    /**
     * @param Collection<int, array{email: string, name: string}> $recipients
     */
    public function queue(Broadcast $broadcast, Collection $recipients): BroadcastSchedule
    {
        $plan = BroadcastSchedule::plan($recipients->count(), $this->quota);

        $jobs = $recipients
            ->values()
            ->map(static fn(array $recipient, int $index): SendEventReminders => new SendEventReminders(
                $broadcast->id,
                $recipient['email'],
                $recipient['name'],
            )
                ->onQueue('broadcasts')
                ->delay($plan->sendAt($index)))
            ->all();

        $batch = Bus::batch($jobs)->name("Broadcast: {$broadcast->subject}")->allowFailures()->dispatch();
        $broadcast->update(['batch_id' => $batch->id]);

        return $plan;
    }
}
