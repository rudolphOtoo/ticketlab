<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case MobileMoney = 'momo';
    case Card = 'card';
    case PhysicalCash = 'cash';
    case Complimentary = 'comp';

    public function label(): string
    {
        return match ($this) {
            self::MobileMoney => 'Mobile money',
            self::Card => 'Card',
            self::PhysicalCash => 'Cash',
            self::Complimentary => 'Complimentary',
        };
    }
}
