import { describe, expect, it } from 'vitest';
import fixture from '../fixtures/paystack-fees.json';
import { breakdown, formatCedis, grossPesewas } from '../../resources/js/checkout/fees.js';

// The same cases tests/Unit/PaystackFeeCalculatorTest.php checks against App\Services\PaystackFeeCalculator.
const fee = (platformFee) => ({ basis: fixture.basis, scale: fixture.scale, platformFee });

describe('browser fee formula', () => {
    it.each(fixture.cases)('net $net_pesewas + platform $platform_fee_pesewas pesewas', (row) => {
        const totals = breakdown(row.net_pesewas, fee(row.platform_fee_pesewas));

        expect(totals.gross).toBe(row.gross_pesewas);
        expect(totals.processingFee).toBe(row.processing_fee_pesewas);
        expect(grossPesewas(row.net_pesewas, fee(row.platform_fee_pesewas))).toBe(row.gross_pesewas);
    });

    it('formats amounts like PHP number_format', () => {
        expect(formatCedis(0)).toBe('0.00');
        expect(formatCedis(5100)).toBe('51.00');
        expect(formatCedis(2039710)).toBe('20,397.10');
        expect(formatCedis(123456789)).toBe('1,234,567.89');
    });
});
