<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an organisation stands on the platform. Suspended and archived organisations keep every record; neither sells,
 * shows its public pages or lets its users sign in. Archived ones are also hidden from the platform lists by default.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
        };
    }

    /** Shown on the sign-in page to the organisation's users. */
    public function signInRefusal(): string
    {
        return match ($this) {
            self::Active => '',
            self::Suspended
                => "Your organisation's TicketLab account is suspended, so you can't sign in. Please contact the TicketLab team.",
            self::Archived
                => "Your organisation's TicketLab account has been archived, so you can't sign in. Please contact the TicketLab team.",
        };
    }
}
