/**
 * The buyer-pays Paystack fee, in whole pesewas, exactly as App\Services\PaystackFeeCalculator works it out:
 * gross = ceil((net + platformFee) * scale / basis). The server sends `basis` and `scale` with the page so both sides
 * use the same numbers, and tests/fixtures/paystack-fees.json is checked by both the PHP and the JS test suites.
 *
 * Display only: checkout prices the order again on the server and refuses if the totals differ.
 */
export const DEFAULT_FEE = Object.freeze({ basis: 9805, scale: 10000, platformFee: 0 });

/**
 * @param {number} netPesewas
 * @param {{basis: number, scale: number, platformFee: number}} fee
 * @returns {number}
 */
export function grossPesewas(netPesewas, fee = DEFAULT_FEE) {
    const chargeable = netPesewas + fee.platformFee;

    if (chargeable <= 0) {
        return 0;
    }

    // Integer ceiling division, as in PHP's intdiv(a + b - 1, b). Every operand is a whole number far below 2^53,
    // so the division is exact enough for Math.floor to give the same integer PHP does.
    return Math.floor((chargeable * fee.scale + (fee.basis - 1)) / fee.basis);
}

/**
 * @param {number} netPesewas
 * @param {{basis: number, scale: number, platformFee: number}} fee
 * @returns {{net: number, platformFee: number, processingFee: number, gross: number}} all in pesewas
 */
export function breakdown(netPesewas, fee = DEFAULT_FEE) {
    const gross = grossPesewas(netPesewas, fee);

    return {
        net: netPesewas,
        platformFee: fee.platformFee,
        processingFee: gross - netPesewas - fee.platformFee,
        gross,
    };
}

/**
 * "1,234.50", matching PHP's number_format($amount, 2).
 *
 * @param {number} pesewas
 * @returns {string}
 */
export function formatCedis(pesewas) {
    const whole = Math.floor(pesewas / 100);
    const cents = String(pesewas % 100).padStart(2, '0');

    return `${String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${cents}`;
}
