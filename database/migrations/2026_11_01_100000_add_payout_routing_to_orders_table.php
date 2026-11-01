<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            // The Paystack subaccount this order's ticket revenue was routed to; null means the main account.
            $table->string('payout_subaccount_code')->nullable()->after('currency');
            $table->index(['organization_id', 'payment_status', 'subdivision_id'], 'orders_zone_payout_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropIndex('orders_zone_payout_index');
            $table->dropColumn('payout_subaccount_code');
        });
    }
};
