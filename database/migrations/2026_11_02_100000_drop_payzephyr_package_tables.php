<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PayZephyr package was removed: Paystack is called directly by the payment services,
 * so its transaction log and webhook ledger tables were never written by this application.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_transactions');
    }

    public function down(): void
    {
        Schema::create('payment_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('provider');
            $table->string('status');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('email');
            $table->string('channel')->nullable();
            $table->json('metadata')->nullable();
            $table->json('customer')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_key');
            $table->timestamps();
            $table->unique(['provider', 'event_key']);
        });
    }
};
