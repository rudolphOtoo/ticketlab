<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\MailCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Today's count of sent emails against the provider's daily cap (Brevo free plan: 300, shared by every kind of email).
 *
 * reserve() takes one slot with a single conditional UPDATE, so serverless instances sending at the same moment can
 * never overshoot: the row only changes while today's total is under the kind's ceiling. A send that then fails gives
 * its slot back (release()).
 */
final class MailQuota
{
    /** True while sending an email whose slot was already taken (so the MessageSent listener skips it). */
    private static bool $insideCountedSend = false;

    /**
     * Runs $send for an email that already took its slot.
     *
     * @template T
     *
     * @param \Closure(): T $send
     *
     * @return T
     */
    public static function counted(\Closure $send): mixed
    {
        $previous = self::$insideCountedSend;
        self::$insideCountedSend = true;

        try {
            return $send();
        } finally {
            self::$insideCountedSend = $previous;
        }
    }

    /** Whether the email being sent right now already took its slot. */
    public static function isInsideCountedSend(): bool
    {
        return self::$insideCountedSend;
    }

    public function limit(): int
    {
        return max(0, (int) config('mail.quota.daily_limit', 300));
    }

    /** The quota day now, in the provider's timezone. */
    public function today(): string
    {
        return $this->now()->toDateString();
    }

    /** When the count starts again from zero (the provider's midnight), as app time. */
    public function nextReset(): CarbonImmutable
    {
        return $this
            ->now()
            ->addDay()
            ->startOfDay()
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }

    /** Seconds until the next reset, plus a small spread so deferred emails don't all start in the same second. */
    public function secondsUntilReset(int $spread = 0): int
    {
        $seconds = (int) ceil(CarbonImmutable::now()->diffInSeconds($this->nextReset(), true));

        return max(60, $seconds + ($spread > 0 ? random_int(0, $spread) : 0));
    }

    /**
     * Takes one slot for $category if today's total is still under its ceiling.
     */
    public function reserve(MailCategory $category): bool
    {
        $day = $this->ensureToday();
        $column = $category->value;

        return DB::table('mail_quota_days')
            ->where('day', $day)
            ->where('sent', '<', $category->ceiling($this->limit()))
            ->update([
                'sent' => DB::raw('sent + 1'),
                $column => DB::raw("{$column} + 1"),
                'updated_at' => now(),
            ]) === 1;
    }

    /** Counts an email that is never held back (sent by Laravel itself). */
    public function count(MailCategory $category): void
    {
        $day = $this->ensureToday();
        $column = $category->value;

        DB::table('mail_quota_days')
            ->where('day', $day)
            ->update([
                'sent' => DB::raw('sent + 1'),
                $column => DB::raw("{$column} + 1"),
                'updated_at' => now(),
            ]);
    }

    /** Gives back the slot of an email that was not sent after all. */
    public function release(MailCategory $category): void
    {
        $column = $category->value;

        DB::table('mail_quota_days')
            ->where('day', $this->today())
            ->where('sent', '>', 0)
            ->where($column, '>', 0)
            ->update([
                'sent' => DB::raw('sent - 1'),
                $column => DB::raw("{$column} - 1"),
                'updated_at' => now(),
            ]);
    }

    /** Records an email moved to a later day for lack of room (shown on the platform dashboard). */
    public function recordDeferral(): void
    {
        $day = $this->ensureToday();

        DB::table('mail_quota_days')
            ->where('day', $day)
            ->update([
                'deferred' => DB::raw('deferred + 1'),
                'updated_at' => now(),
            ]);
    }

    /** How many more emails of $category fit today. */
    public function remaining(MailCategory $category): int
    {
        return max(0, $category->ceiling($this->limit()) - $this->sentToday());
    }

    public function sentToday(): int
    {
        return (int) DB::table('mail_quota_days')->where('day', $this->today())->value('sent');
    }

    /**
     * Today's counts, for the platform dashboard.
     *
     * @return array{day: string, limit: int, sent: int, deferred: int, by_category: array<string, int>}
     */
    public function snapshot(): array
    {
        $row = DB::table('mail_quota_days')->where('day', $this->today())->first();
        $byCategory = [];

        foreach (MailCategory::cases() as $category) {
            $byCategory[$category->value] = (int) ($row->{$category->value} ?? 0);
        }

        return [
            'day' => $this->today(),
            'limit' => $this->limit(),
            'sent' => (int) ($row->sent ?? 0),
            'deferred' => (int) ($row->deferred ?? 0),
            'by_category' => $byCategory,
        ];
    }

    private function ensureToday(): string
    {
        $day = $this->today();

        DB::table('mail_quota_days')->insertOrIgnore(['day' => $day, 'created_at' => now(), 'updated_at' => now()]);

        return $day;
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('mail.quota.timezone', 'UTC'));
    }
}
