<?php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

/**
 * Paystack refused or could not be asked to open a transaction. The message is safe to show the buyer.
 */
class PaystackCheckoutException extends RuntimeException
{
    public static function duplicateReference(): self
    {
        return new self('Paystack has already seen this payment reference.', 409);
    }

    public function isDuplicateReference(): bool
    {
        return $this->getCode() === 409;
    }
}
