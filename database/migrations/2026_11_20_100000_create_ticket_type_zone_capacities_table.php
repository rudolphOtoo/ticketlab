<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every zone sells the same ticket types at the same prices, but each zone has its own seats per ticket type, so
 * a zone sells out on its own. The ticket type's total_capacity remains the overall ceiling. A zone with no row
 * here is limited only by that ceiling.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_type_zone_capacities', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subdivision_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('capacity');
            $table->timestamps();

            $table->unique(['ticket_type_id', 'subdivision_id']);
        });

        // Zone-scoped stock checks count issued passes and live holds per tier and zone.
        Schema::table('attendees', static function (Blueprint $table): void {
            $table->index(['ticket_type_id', 'subdivision_id'], 'attendees_tier_zone_index');
        });

        Schema::table('reservations', static function (Blueprint $table): void {
            $table->index(['ticket_type_id', 'subdivision_id', 'status'], 'reservations_tier_zone_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', static function (Blueprint $table): void {
            $table->dropIndex('reservations_tier_zone_status_index');
        });

        Schema::table('attendees', static function (Blueprint $table): void {
            $table->dropIndex('attendees_tier_zone_index');
        });

        Schema::dropIfExists('ticket_type_zone_capacities');
    }
};
