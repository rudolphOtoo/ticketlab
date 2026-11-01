<?php

declare(strict_types=1);

use App\Services\PaystackFeeCalculator;

/**
 * What Paystack keeps from a gross charge at 1.95%, rounded to the pesewa as Paystack settles it.
 */
function paystack_fee_pesewas(int $grossPesewas): int
{
    return (int) round($grossPesewas * PaystackFeeCalculator::RATE);
}

it('grosses up a ticket so the buyer covers the 1.95% processing fee', function () {
    // 100.00 / (1 - 0.0195) = 101.989..., rounded up to the pesewa.
    expect(PaystackFeeCalculator::grossPesewas(100))
        ->toBe(10_199)
        ->and(PaystackFeeCalculator::gross(100))
        ->toBe(101.99);
});

it('adds the platform fee before grossing up', function () {
    // (50.00 + 2.00) / 0.9805 = 53.034..., rounded up.
    expect(PaystackFeeCalculator::grossPesewas(50, 2))->toBe(5_304);
});

it('charges nothing for free tickets', function () {
    expect(PaystackFeeCalculator::grossPesewas(0))
        ->toBe(0)
        ->and(PaystackFeeCalculator::breakdown(0)['gross_pesewas'])
        ->toBe(0);
});

it('never leaves the organiser short after Paystack takes its fee', function (float $net, float $platformFee) {
    $breakdown = PaystackFeeCalculator::breakdown($net, $platformFee);
    $gross = $breakdown['gross_pesewas'];
    $kept = $breakdown['net_pesewas'] + $breakdown['platform_fee_pesewas'];

    // Zero net loss, overcharging the buyer by at most a pesewa (Paystack rounds its fee to the nearest one).
    expect($gross - paystack_fee_pesewas($gross))
        ->toBeGreaterThanOrEqual($kept)
        ->and($gross - paystack_fee_pesewas($gross) - $kept)
        ->toBeLessThanOrEqual(1);
})->with([
    'GHS 10' => [10.00, 0],
    'GHS 25.50' => [25.50, 0],
    'GHS 50 + fee' => [50.00, 2.00],
    'GHS 100' => [100.00, 0],
    'GHS 250 + fee' => [250.00, 5.00],
    'GHS 500' => [500.00, 0],
]);

it('reconciles every component of the breakdown to the gross charge', function () {
    $breakdown = PaystackFeeCalculator::breakdown('199.99', '3.50');

    expect($breakdown['net_pesewas'] + $breakdown['platform_fee_pesewas'] + $breakdown['processing_fee_pesewas'])
        ->toBe($breakdown['gross_pesewas'])
        ->and($breakdown['net'])
        ->toBe(199.99)
        ->and($breakdown['platform_fee'])
        ->toBe(3.5)
        ->and($breakdown['rate'])
        ->toBe(0.0195);
});

it('converts amounts to pesewas without floating point drift', function () {
    expect(PaystackFeeCalculator::toPesewas('0.29'))
        ->toBe(29)
        ->and(PaystackFeeCalculator::toPesewas(19.99))
        ->toBe(1_999);
});
