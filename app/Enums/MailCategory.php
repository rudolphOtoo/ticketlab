<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of email, most important first. Each may only use its share of the daily cap (config mail.quota.shares), so
 * the kinds below it can never use up the room ticket emails need.
 */
enum MailCategory: string
{
    case Ticket = 'ticket';
    case Transactional = 'transactional';
    case ZoneChoice = 'zone_choice';
    case Broadcast = 'broadcast';

    /** Emails Laravel sends itself (password resets, verification): counted, never held back. */
    case Other = 'other';

    /** The most this kind may bring today's total to. */
    public function ceiling(int $dailyLimit): int
    {
        if ($this === self::Other) {
            return PHP_INT_MAX;
        }

        $share = (int) config("mail.quota.shares.{$this->value}", 100);

        return intdiv(max(0, $dailyLimit) * max(0, min(100, $share)), 100);
    }

    public function label(): string
    {
        return match ($this) {
            self::Ticket => 'Tickets',
            self::Transactional => 'Invitations & alerts',
            self::ZoneChoice => 'Zone choice',
            self::Broadcast => 'Broadcasts',
            self::Other => 'Account emails',
        };
    }
}
