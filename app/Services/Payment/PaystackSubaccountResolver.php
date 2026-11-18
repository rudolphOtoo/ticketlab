<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subdivision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides whether a checkout may route funds to a zone's Paystack subaccount.
 *
 * Paystack refuses the entire transaction ("Invalid Subaccount") when the code is unknown to the account behind
 * the active key, and test keys cannot see live subaccounts (or vice versa). Codes are therefore verified with
 * Paystack when they are saved (and re-checked daily by payouts:recheck-subaccounts), and the result is stored on
 * the zone. Checkout only ever reads that stored result: it never calls Paystack's subaccount API.
 */
class PaystackSubaccountResolver
{
    private const string CODE_PATTERN = '/^ACCT_[a-z0-9]+$/i';

    private const string SETTLEMENT_CURRENCY = 'GHS';

    private const string MALFORMED_CODE = 'This is not a Paystack subaccount code.';

    /**
     * The zone's subaccount code when its stored verification covers this exact code under the active key's mode,
     * else null. Reads the database only.
     */
    public function forSubdivision(?Subdivision $subdivision): ?string
    {
        $code = $subdivision?->paystack_subaccount_code;

        if (
            $subdivision === null
            || $code === null
            || trim($code) === ''
            || preg_match(self::CODE_PATTERN, $code) !== 1
        ) {
            return null;
        }

        return $subdivision->payout_verified_code === $code && $subdivision->payout_verified_mode === $this->mode()
            ? $code
            : null;
    }

    /**
     * Ask Paystack about the zone's current code now and store the outcome. A definitive rejection stops the zone
     * taking payments; an unreachable Paystack leaves the last stored verdict in place.
     *
     * @return array{recognised: bool|null, message: string|null, details: array<array-key, mixed>}
     */
    public function verify(Subdivision $zone): array
    {
        $code = trim((string) $zone->paystack_subaccount_code);
        $inspection = $code === ''
            ? ['recognised' => false, 'message' => 'No subaccount code is set.', 'details' => []]
            : $this->inspect($code);

        $this->recordInspection($zone, $inspection);

        return $inspection;
    }

    /**
     * Store an inspection that the caller has just made for the zone's current code (no second Paystack call).
     *
     * @param array{recognised: bool|null, message: string|null, details: array<array-key, mixed>} $inspection
     */
    public function recordInspection(Subdivision $zone, array $inspection): void
    {
        match ($inspection['recognised']) {
            true => $this->markVerified($zone),
            false => $zone->forceFill([
                'payout_verified_code' => null,
                'payout_verified_mode' => null,
                'payout_checked_at' => now(),
                'payout_check_error' => mb_substr((string) $this->rejectionReason($inspection), 0, 255),
            ])->save(),
            // Paystack unreachable: keep whatever was verified before, so a network error never blocks sales.
            null => $zone->forceFill([
                'payout_checked_at' => now(),
                'payout_check_error' => mb_substr($inspection['message'] ?? 'Paystack could not be reached.', 0, 255),
            ])->save(),
        };
    }

    /**
     * The zone's current code was just confirmed with Paystack under the active key.
     */
    public function markVerified(Subdivision $zone): void
    {
        $zone->forceFill([
            'payout_verified_code' => $zone->paystack_subaccount_code,
            'payout_verified_mode' => $this->mode(),
            'payout_verified_at' => now(),
            'payout_checked_at' => now(),
            'payout_check_error' => null,
        ])->save();
    }

    /**
     * Ask Paystack about a code right now, returning what it knows for admin onboarding.
     *
     * @return array{recognised: bool|null, message: string|null, details: array<array-key, mixed>}
     */
    public function inspect(string $code): array
    {
        $secretKey = $this->secretKey();

        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            return ['recognised' => false, 'message' => self::MALFORMED_CODE, 'details' => []];
        }

        if ($secretKey === '') {
            return ['recognised' => null, 'message' => 'No Paystack secret key is configured.', 'details' => []];
        }

        return $this->lookup($secretKey, $code, ['subaccount' => $code, 'paystack_mode' => $this->mode()]);
    }

    /**
     * Why an inspected code cannot be used, in words an organisation admin can act on; null when it can.
     *
     * @param array{recognised: bool|null, message: string|null, details: array<array-key, mixed>} $inspection
     */
    public function rejectionReason(array $inspection): ?string
    {
        return match (true) {
            $inspection['recognised'] === true => null,
            $inspection['message'] === self::MALFORMED_CODE => self::MALFORMED_CODE,
            // Known to Paystack but unusable (deactivated, wrong currency): the message already says why.
            $inspection['details'] !== [] => $inspection['message'],
            $inspection['recognised'] === false => 'Paystack does not recognise this subaccount for the '
                . $this->mode()
                . ' keys.',
            default => $inspection['message'] ?? 'Paystack could not be reached. Try again.',
        };
    }

    public function mode(): string
    {
        return match (true) {
            str_starts_with($this->secretKey(), 'sk_test_') => 'test',
            str_starts_with($this->secretKey(), 'sk_live_') => 'live',
            default => 'unknown',
        };
    }

    /**
     * Recognised is true when Paystack knows the subaccount, false when it does not, null when it could not be reached.
     *
     * @param array<string, mixed> $context
     *
     * @return array{recognised: bool|null, message: string|null, details: array<array-key, mixed>}
     */
    private function lookup(string $secretKey, string $code, array $context): array
    {
        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->timeout(5)
                ->get($baseUrl . '/subaccount/' . rawurlencode($code));
        } catch (Throwable $exception) {
            Log::error('Paystack subaccount omitted: verification request failed.', [
                ...$context,
                'error' => $exception->getMessage(),
            ]);

            return ['recognised' => null, 'message' => 'Paystack could not be reached.', 'details' => []];
        }

        $message = $response->json('message');
        $message = is_string($message) ? $message : null;

        if ($response->successful() && $response->json('status') === true) {
            $details = $response->json('data');
            $details = is_array($details) ? $details : [];
            $problem = $this->unusable($details);

            if ($problem !== null) {
                Log::error('Paystack subaccount omitted: ' . $problem, $context);

                return ['recognised' => false, 'message' => $problem, 'details' => $details];
            }

            return ['recognised' => true, 'message' => $message, 'details' => $details];
        }

        Log::error('Paystack subaccount omitted: Paystack does not recognise it for the active API key.', [
            ...$context,
            'http_status' => $response->status(),
            'paystack_message' => $message,
        ]);

        // Server errors are transient; only a definitive answer from Paystack counts as a rejection.
        return ['recognised' => $response->serverError() ? null : false, 'message' => $message, 'details' => []];
    }

    /**
     * Paystack knows the subaccount but cannot settle these orders into it.
     *
     * @param array<array-key, mixed> $details
     */
    private function unusable(array $details): ?string
    {
        $currency = $details['currency'] ?? null;

        return match (true) {
            ($details['active'] ?? true) === false => 'This subaccount is deactivated on Paystack.',
            is_string($currency) && strtoupper($currency) !== self::SETTLEMENT_CURRENCY
                => "This subaccount settles in {$currency}; ticket sales are in " . self::SETTLEMENT_CURRENCY . '.',
            default => null,
        };
    }

    private function secretKey(): string
    {
        return trim((string) config('services.paystack.secret_key', ''));
    }
}
