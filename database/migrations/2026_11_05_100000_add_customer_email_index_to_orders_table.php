<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The attendee dashboard claims guest checkouts by matching orders.customer_email, which scanned the
 * orders table on every visit by a verified attendee.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex(['customer_email']);
        });
    }
};
