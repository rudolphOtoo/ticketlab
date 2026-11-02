<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subdivision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides whether a checkout may route funds to a zone's Paystack subaccount.
 *
 * Paystack refuses the entire transaction ("Invalid Subaccount") when the code is unknown to the account
 * behind the active key, and test keys cannot see live subaccounts (or vice versa). Every code is therefore
 * verified against the active key (cached per key) before a transaction is initialised with it.
 */
class PaystackSubaccountResolver
{
    private const string CODE_PATTERN = '/^ACCT_[a-z0-9]+$/i';

    private const int VERIFIED_TTL_SECONDS = 21_600;

    private const int REJECTED_TTL_SECONDS = 600;

    private const string SETTLEMENT_CURRENCY = 'GHS';

    public function forSubdivision(?Subdivision $subdivision): ?string
    {
        $code = $subdivision?->paystack_subaccount_code;

        if ($subdivision === null || $code === null || trim($code) === '' || !$this->splitsEnabledFor($subdivision)) {
            return null;
        }

        $context = [
            'organization_id' => $subdivision->organization_id,
            'subdivision_id' => $subdivision->id,
            'subaccount' => $code,
            'paystack_mode' => $this->mode(),
        ];

        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            Log::warning('Paystack subaccount omitted: the code is not a valid Paystack subaccount code.', $context);

            return null;
        }

        return $this->existsForActiveKey($code, $context) ? $code : null;
    }

    /**
     * True when sales for this zone are expected to settle into its own subaccount.
     */
    public function routingRequired(?Subdivision $subdivision): bool
    {
        return $subdivision !== null && $this->splitsEnabledFor($subdivision);
    }

    /**
     * Cached verdict for a code under the active key, without calling Paystack: true, false, or null when unchecked.
     */
    public function cachedVerdict(string $code): ?bool
    {
        $secretKey = $this->secretKey();
        $cached = $secretKey === '' ? null : Cache::get($this->cacheKey($secretKey, $code));

        return is_bool($cached) ? $cached : null;
    }

    /**
     * Ask Paystack about a code right now (refreshing the cache), returning what it knows for admin onboarding.
     *
     * @return array{recognised: bool|null, message: string|null, details: array<array-key, mixed>}
     */
    public function inspect(string $code): array
    {
        $secretKey = $this->secretKey();

        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            return ['recognised' => false, 'message' => 'This is not a Paystack subaccount code.', 'details' => []];
        }

        if ($secretKey === '') {
            return ['recognised' => null, 'message' => 'No Paystack secret key is configured.', 'details' => []];
        }

        $result = $this->lookup($secretKey, $code, ['subaccount' => $code, 'paystack_mode' => $this->mode()]);
        $this->remember($secretKey, $code, $result['recognised']);

        return $result;
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
     * Platform switch first, then the organisation's own opt-out in its settings.
     */
    private function splitsEnabledFor(Subdivision $subdivision): bool
    {
        if (!config('services.paystack.split_payments')) {
            return false;
        }

        $settings = $subdivision->organization->settings ?? [];

        return ($settings['paystack_split_payments'] ?? true) !== false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function existsForActiveKey(string $code, array $context): bool
    {
        $secretKey = $this->secretKey();

        if ($secretKey === '') {
            Log::warning('Paystack subaccount omitted: no secret key is configured to verify it.', $context);

            return false;
        }

        $cached = $this->cachedVerdict($code);

        if ($cached !== null) {
            return $cached;
        }

        $recognised = $this->lookup($secretKey, $code, $context)['recognised'];
        $this->remember($secretKey, $code, $recognised);

        return $recognised === true;
    }

    /**
     * Only a definitive answer from Paystack is cached; unreachable lookups are retried on the next checkout.
     */
    private function remember(string $secretKey, string $code, ?bool $recognised): void
    {
        if ($recognised !== null) {
            Cache::put(
                $this->cacheKey($secretKey, $code),
                $recognised,
                $recognised ? self::VERIFIED_TTL_SECONDS : self::REJECTED_TTL_SECONDS,
            );
        }
    }

    /**
     * Keyed by the secret key fingerprint, so switching between test and live keys re-verifies every code.
     */
    private function cacheKey(string $secretKey, string $code): string
    {
        return 'paystack:subaccount:' . sha1($secretKey) . ':' . $code;
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
