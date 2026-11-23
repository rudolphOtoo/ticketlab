<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A cancelled event stops selling at once, its public page says so with the organiser's message, and its passes are
 * refused at the gate. Its orders, passes and payments are kept: refunds are made by hand from the refund list.
 * Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', static function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', static function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'cancellation_message']);
        });
    }
};
