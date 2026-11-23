<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\MailCategory;
use Carbon\CarbonImmutable;

/**
 * When each recipient of a broadcast is emailed. Broadcasts may only use their share of the daily cap (150 of 300 on
 * Brevo's free plan), so a larger audience is spread over the following days: what fits today goes now, then up to a
 * day's share each day, starting a while after midnight so the day's deferred ticket emails go first.
 *
 * MailQuotaGate still checks every send, so if ticket emails take the room on the day, the rest simply move on a day.
 */
final readonly class BroadcastSchedule
{
    /** Recipients per pacing step, and seconds between steps (keeps the provider's per-second rate low). */
    private const int STEP_SIZE = 25;

    private const int STEP_SECONDS = 5;

    /** Later days start this long after the quota resets, after deferred ticket emails (spread over 30 minutes). */
    private const int LATER_DAYS_OFFSET_MINUTES = 45;

    private function __construct(
        public int $recipients,
        public int $today,
        public int $perDay,
        private CarbonImmutable $now,
        private CarbonImmutable $nextReset,
    ) {}

    public static function plan(int $recipients, MailQuota $quota): self
    {
        $perDay = max(1, MailCategory::Broadcast->ceiling($quota->limit()));

        return new self(
            max(0, $recipients),
            min(max(0, $recipients), $quota->remaining(MailCategory::Broadcast)),
            $perDay,
            CarbonImmutable::now(),
            $quota->nextReset(),
        );
    }

    /** Days after today the last recipient is emailed (0: all today). */
    public function extraDays(): int
    {
        $later = $this->recipients - $this->today;

        return $later <= 0 ? 0 : (int) ceil($later / $this->perDay);
    }

    public function sendAt(int $index): CarbonImmutable
    {
        if ($index < $this->today) {
            return $this->now->addSeconds(intdiv($index, self::STEP_SIZE) * self::STEP_SECONDS);
        }

        $later = $index - $this->today;
        $day = intdiv($later, $this->perDay);
        $position = $later % $this->perDay;

        return $this->nextReset
            ->addDays($day)
            ->addMinutes(self::LATER_DAYS_OFFSET_MINUTES)
            ->addSeconds(intdiv($position, self::STEP_SIZE) * self::STEP_SECONDS);
    }

    /** What the admin is told after sending. */
    public function summary(): string
    {
        if ($this->extraDays() === 0) {
            return 'All are emailed now.';
        }

        $days = $this->extraDays();

        return sprintf(
            '%d are emailed today, then up to %d a day over the next %d %s (daily email limit).',
            $this->today,
            $this->perDay,
            $days,
            $days === 1 ? 'day' : 'days',
        );
    }
}
