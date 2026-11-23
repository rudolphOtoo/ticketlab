<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MailCategory;
use App\Jobs\Concerns\WaitsForMailQuota;
use App\Jobs\Middleware\MailQuotaGate;
use App\Mail\EventReminderMail;
use App\Models\Broadcast;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * One recipient of a Broadcast Hub batch; failures stay inside the batch so they can be retried per broadcast.
 */
class SendEventReminders implements ShouldQueue
{
    use Batchable;
    use Queueable;
    use WaitsForMailQuota;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public int $broadcastId,
        public string $email,
        public string $recipientName,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new MailQuotaGate(MailCategory::Broadcast)];
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $broadcast = Broadcast::query()->with(['event', 'organization'])->find($this->broadcastId);

        if ($broadcast === null) {
            return;
        }

        Mail::to($this->email, $this->recipientName)->send(new EventReminderMail($broadcast, $this->recipientName));
    }
}
