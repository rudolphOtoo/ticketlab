<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case MobileMoney = 'momo';
    case Card = 'card';
    case PhysicalCash = 'cash';
}
