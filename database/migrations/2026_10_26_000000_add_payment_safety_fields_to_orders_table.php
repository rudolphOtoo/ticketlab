<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table
                ->foreignId('reservation_id')
                ->nullable()
                ->after('subdivision_id')
                ->constrained('reservations')
                ->nullOnDelete();
            $table->string('currency', 3)->default('GHS')->after('fee_amount');
        });

        DB::table('orders')
            ->select(['id', 'metadata'])
            ->whereNull('reservation_id')
            ->orderBy('id')
            ->chunkById(100, static function ($orders): void {
                foreach ($orders as $order) {
                    $metadata = is_string($order->metadata) ? json_decode($order->metadata, true) : null;

                    $reservationId = is_array($metadata) ? $metadata['reservation_id'] ?? null : null;

                    if (!is_numeric($reservationId)) {
                        continue;
                    }

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['reservation_id' => (int) $reservationId]);
                }
            });

        Schema::table('orders', static function (Blueprint $table) {
            $table->unique('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropUnique(['reservation_id']);
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn('currency');
        });
    }
};
