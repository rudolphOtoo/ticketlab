<?php

namespace App\Services;

class PaystackFeeCalculator
{
    /**
     * Calculate gross amount to charge to ensure net revenue retention (zero net loss).
     *
     * Formula: Gross Amount = Net Amount / (1 - Fee Rate)
     *
     * @param  float  $netAmount  The amount the merchant wants to receive
     * @param  float  $feeRate  The transaction fee rate (default 0.0195 for 1.95%)
     * @param  float  $flatFee  Optional flat fee in addition to percentage
     * @return float The gross amount to charge the customer
     */
    public function calculateGrossAmount(float $netAmount, float $feeRate = 0.0195, float $flatFee = 0.0): float
    {
        if ($feeRate >= 1) {
            throw new \InvalidArgumentException('Fee rate must be less than 1');
        }

        if ($netAmount <= 0) {
            throw new \InvalidArgumentException('Net amount must be greater than 0');
        }

        // Gross = (Net + Flat Fee) / (1 - Percentage Fee)
        $grossAmount = ($netAmount + $flatFee) / (1 - $feeRate);

        return round($grossAmount, 2);
    }

    /**
     * Calculate the fee amount for a given gross amount.
     *
     * @param  float  $grossAmount  The amount charged to the customer
     * @param  float  $feeRate  The transaction fee rate (default 0.0195 for 1.95%)
     * @param  float  $flatFee  Optional flat fee in addition to percentage
     * @return float The fee amount
     */
    public function calculateFeeAmount(float $grossAmount, float $feeRate = 0.0195, float $flatFee = 0.0): float
    {
        $percentageFee = $grossAmount * $feeRate;
        $totalFee = $percentageFee + $flatFee;

        return round($totalFee, 2);
    }

    /**
     * Calculate the net amount that will be received for a given gross amount.
     *
     * @param  float  $grossAmount  The amount charged to the customer
     * @param  float  $feeRate  The transaction fee rate (default 0.0195 for 1.95%)
     * @param  float  $flatFee  Optional flat fee in addition to percentage
     * @return float The net amount received
     */
    public function calculateNetAmount(float $grossAmount, float $feeRate = 0.0195, float $flatFee = 0.0): float
    {
        $feeAmount = $this->calculateFeeAmount($grossAmount, $feeRate, $flatFee);
        $netAmount = $grossAmount - $feeAmount;

        return round($netAmount, 2);
    }

    /**
     * Get a detailed breakdown of the transaction.
     *
     * @param  float  $netAmount  The amount the merchant wants to receive
     * @param  float  $feeRate  The transaction fee rate (default 0.0195 for 1.95%)
     * @param  float  $flatFee  Optional flat fee in addition to percentage
     * @return array Detailed breakdown
     */
    public function getBreakdown(float $netAmount, float $feeRate = 0.0195, float $flatFee = 0.0): array
    {
        $grossAmount = $this->calculateGrossAmount($netAmount, $feeRate, $flatFee);
        $percentageFee = round($grossAmount * $feeRate, 2);
        $totalFee = round($percentageFee + $flatFee, 2);
        $netReceived = round($grossAmount - $totalFee, 2);

        return [
            'net_amount' => round($netAmount, 2),
            'gross_amount' => $grossAmount,
            'fee_rate' => $feeRate,
            'percentage_fee' => $percentageFee,
            'flat_fee' => $flatFee,
            'total_fee' => $totalFee,
            'net_received' => $netReceived,
            'zero_net_loss_verified' => abs($netReceived - $netAmount) < 0.01,
        ];
    }
}
