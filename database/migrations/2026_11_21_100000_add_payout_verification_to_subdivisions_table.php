<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A zone's payout subaccount is verified with Paystack when it is saved (and re-checked daily), and the result is
 * stored here. Checkout reads only this stored result, so it never calls Paystack's subaccount API.
 *
 * A zone can take payments only while payout_verified_code equals its current code and payout_verified_mode
 * equals the mode of the active key ("test" or "live").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subdivisions', static function (Blueprint $table): void {
            $table->string('payout_verified_code', 64)->nullable()->after('paystack_subaccount_code');
            $table->string('payout_verified_mode', 8)->nullable()->after('payout_verified_code');
            $table->timestamp('payout_verified_at')->nullable()->after('payout_verified_mode');
            $table->timestamp('payout_checked_at')->nullable()->after('payout_verified_at');
            $table->string('payout_check_error', 255)->nullable()->after('payout_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('subdivisions', static function (Blueprint $table): void {
            $table->dropColumn([
                'payout_verified_code',
                'payout_verified_mode',
                'payout_verified_at',
                'payout_checked_at',
                'payout_check_error',
            ]);
        });
    }
};
