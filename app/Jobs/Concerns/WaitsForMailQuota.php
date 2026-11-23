<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;

/**
 * For queued emails behind MailQuotaGate (jobs, mailables and notifications). Waiting for the next day's quota puts
 * the job back without counting as a failure: Laravel stops counting tries once retryUntil() is set, and
 * maxExceptions still fails the job after a few real send errors.
 *
 * Used with Laravel's Queueable, which provides $delay.
 *
 * @property DateTimeInterface|DateInterval|int|null $delay
 */
trait WaitsForMailQuota
{
    /** Real send errors (SMTP refused, timeout) before the job fails. */
    public int $maxExceptions = 3;

    /**
     * Keeps trying through three quota resets after the email was due (its delay, if any). Stamped at dispatch.
     */
    public function retryUntil(): DateTimeInterface
    {
        $delay = $this->delay ?? null;
        $due = match (true) {
            $delay instanceof DateTimeInterface => CarbonImmutable::instance($delay),
            $delay instanceof DateInterval => CarbonImmutable::now()->add($delay),
            is_int($delay) => CarbonImmutable::now()->addSeconds($delay),
            default => CarbonImmutable::now(),
        };

        return $due->addDays(3);
    }
}
