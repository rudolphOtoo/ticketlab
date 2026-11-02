<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Roster filters, gate turnout counters and closeout tallies.
        Schema::table('attendees', static function (Blueprint $table) {
            $table->index(['organization_id', 'checked_in_at']);
            $table->index(['organization_id', 'subdivision_id']);
        });

        // Ledger revenue sums split by payment method.
        Schema::table('orders', static function (Blueprint $table) {
            $table->index(['organization_id', 'payment_status', 'payment_method']);
        });

        // Checkout hold reuse lookup and the release-expired sweep.
        Schema::table('reservations', static function (Blueprint $table) {
            $table->index(['session_id', 'ticket_type_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('session_ledgers', static function (Blueprint $table) {
            $table->index(['organization_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendees', static function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'checked_in_at']);
            $table->dropIndex(['organization_id', 'subdivision_id']);
        });

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'payment_status', 'payment_method']);
        });

        Schema::table('reservations', static function (Blueprint $table) {
            $table->dropIndex(['session_id', 'ticket_type_id', 'status']);
            $table->dropIndex(['status', 'expires_at']);
        });

        Schema::table('session_ledgers', static function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'opened_at']);
        });
    }
};
