<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Enums\MailCategory;
use App\Services\Mail\MailQuota;
use Closure;
use Throwable;

/**
 * Queue middleware for every queued email: takes a slot of today's cap for its kind before sending, or puts the job
 * back until the provider's next midnight when there is no room (it is never dropped). A send that throws gives its
 * slot back, so failures don't use up the day.
 *
 * Jobs using it set retryUntil() and maxExceptions: waiting for tomorrow then never counts as a failed attempt, while
 * real send errors still fail the job after a few tries.
 */
final readonly class MailQuotaGate
{
    /** Deferred emails start within this many seconds after midnight, not all at once. */
    private const int SPREAD_SECONDS = 1800;

    public function __construct(
        private MailCategory $category,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        $quota = app(MailQuota::class);

        if (!$quota->reserve($this->category)) {
            $quota->recordDeferral();

            if (method_exists($job, 'release')) {
                $job->release($quota->secondsUntilReset(self::SPREAD_SECONDS));
            }

            return null;
        }

        try {
            return MailQuota::counted(static fn(): mixed => $next($job));
        } catch (Throwable $exception) {
            $quota->release($this->category);

            throw $exception;
        }
    }
}
