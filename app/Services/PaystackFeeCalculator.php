<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Paystack Ghana surcharge math engine.
 *
 * Buyers absorb the processor fee so the organization receives 100% of the
 * ticket net value: Gross = (Net + PlatformFee) / (1 - 0.0195).
 */
class PaystackFeeCalculator
{
    public const RATE = 0.0195;

    private const DENOMINATOR_BASIS = 9805;

    private const SCALE = 10000;

    public static function grossPesewas(float|int|string $netAmount, float|int|string $platformFee = 0): int
    {
        $netPesewas = self::toPesewas($netAmount);
        $platformFeePesewas = self::toPesewas($platformFee);
        $chargeablePesewas = $netPesewas + $platformFeePesewas;

        if ($chargeablePesewas <= 0) {
            return 0;
        }

        // Integer arithmetic keeps the ceiling exact to the pesewa and avoids
        // binary floating point drift on values such as 0.9805.
        return intdiv(($chargeablePesewas * self::SCALE) + (self::DENOMINATOR_BASIS - 1), self::DENOMINATOR_BASIS);
    }

    public static function gross(float|int|string $netAmount, float|int|string $platformFee = 0): float
    {
        return (float) (self::grossPesewas($netAmount, $platformFee) / 100);
    }

    public static function calculateGross(float|int|string $netAmount, float|int|string $platformFee = 0): float
    {
        return self::gross($netAmount, $platformFee);
    }

    public static function processingFeePesewas(float|int|string $netAmount, float|int|string $platformFee = 0): int
    {
        return (
            self::grossPesewas($netAmount, $platformFee) - self::toPesewas($netAmount) - self::toPesewas($platformFee)
        );
    }

    public static function processingFee(float|int|string $netAmount, float|int|string $platformFee = 0): float
    {
        return (float) (self::processingFeePesewas($netAmount, $platformFee) / 100);
    }

    /**
     * @return array{
     *     net: float,
     *     platform_fee: float,
     *     processing_fee: float,
     *     gross: float,
     *     net_pesewas: int,
     *     platform_fee_pesewas: int,
     *     processing_fee_pesewas: int,
     *     gross_pesewas: int,
     *     rate: float
     * }
     */
    public static function breakdown(float|int|string $netAmount, float|int|string $platformFee = 0): array
    {
        $netPesewas = self::toPesewas($netAmount);
        $platformFeePesewas = self::toPesewas($platformFee);
        $grossPesewas = self::grossPesewas($netAmount, $platformFee);

        return [
            'net' => (float) ($netPesewas / 100),
            'platform_fee' => (float) ($platformFeePesewas / 100),
            'processing_fee' => (float) (($grossPesewas - $netPesewas - $platformFeePesewas) / 100),
            'gross' => (float) ($grossPesewas / 100),
            'net_pesewas' => $netPesewas,
            'platform_fee_pesewas' => $platformFeePesewas,
            'processing_fee_pesewas' => $grossPesewas - $netPesewas - $platformFeePesewas,
            'gross_pesewas' => $grossPesewas,
            'rate' => self::RATE,
        ];
    }

    /**
     * @return array{
     *     net: float,
     *     platform_fee: float,
     *     processing_fee: float,
     *     gross: float,
     *     net_pesewas: int,
     *     platform_fee_pesewas: int,
     *     processing_fee_pesewas: int,
     *     gross_pesewas: int,
     *     rate: float
     * }
     */
    public static function calculate(float|int|string $netAmount, float|int|string $platformFee = 0): array
    {
        return self::breakdown($netAmount, $platformFee);
    }

    public static function toPesewas(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
