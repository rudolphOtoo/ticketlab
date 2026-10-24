<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Expired = 'expired';
    case Released = 'released';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
