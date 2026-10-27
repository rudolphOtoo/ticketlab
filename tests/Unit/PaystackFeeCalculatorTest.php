<?php

use App\Services\PaystackFeeCalculator;

beforeEach(function () {
    $this->calculator = new PaystackFeeCalculator();
});

it('calculates gross amount for zero net loss with 1.95% fee rate', function () {
    $netAmount = 100.00;
    $grossAmount = $this->calculator->calculateGrossAmount($netAmount, 0.0195);

    // Expected: 100 / (1 - 0.0195) = 100 / 0.9805 = 101.999...
    expect($grossAmount)->toBe(101.99);
});

it('ensures zero net loss for Ghana MoMo transactions', function () {
    $netAmount = 50.00;
    $breakdown = $this->calculator->getBreakdown($netAmount, 0.0195);

    expect($breakdown['net_received'])->toBe($breakdown['net_amount']);
    expect($breakdown['zero_net_loss_verified'])->toBeTrue();
});

it('calculates fee amount correctly', function () {
    $grossAmount = 101.99;
    $feeAmount = $this->calculator->calculateFeeAmount($grossAmount, 0.0195);

    expect($feeAmount)->toBe(1.99);
});

it('calculates net amount from gross amount', function () {
    $grossAmount = 101.99;
    $netAmount = $this->calculator->calculateNetAmount($grossAmount, 0.0195);

    expect($netAmount)->toBe(100.00);
});

it('handles breakdown correctly', function () {
    $netAmount = 100.00;
    $breakdown = $this->calculator->getBreakdown($netAmount, 0.0195);

    expect($breakdown)->toHaveKeys([
        'net_amount',
        'gross_amount',
        'fee_rate',
        'percentage_fee',
        'flat_fee',
        'total_fee',
        'net_received',
        'zero_net_loss_verified',
    ]);

    expect($breakdown['fee_rate'])->toBe(0.0195);
    expect($breakdown['flat_fee'])->toBe(0.0);
});

it('throws exception for fee rate >= 1', function () {
    $this->calculator->calculateGrossAmount(100, 1.5);
})->throws(InvalidArgumentException::class);

it('throws exception for net amount <= 0', function () {
    $this->calculator->calculateGrossAmount(0, 0.0195);
})->throws(InvalidArgumentException::class);

it('handles flat fees correctly', function () {
    $netAmount = 100.00;
    $flatFee = 1.00;
    $grossAmount = $this->calculator->calculateGrossAmount($netAmount, 0.0195, $flatFee);

    // Expected: (100 + 1) / (1 - 0.0195) = 101 / 0.9805 = ~103.01
    expect($grossAmount)->toBe(103.01);
});

it('verifies zero net loss with various amounts', function (float $netAmount) {
    $breakdown = $this->calculator->getBreakdown($netAmount, 0.0195);
    expect($breakdown['net_received'])->toBe($netAmount);
})->with([10.00, 25.50, 50.00, 100.00, 250.00, 500.00]);
