<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each zone of an event meets at its own venue, optionally on its own date and time. Blank fields fall back to
 * the event's own venue and schedule.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_zones', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subdivision_id')->constrained()->cascadeOnDelete();
            $table->string('venue', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'subdivision_id']);
            $table->index(['organization_id', 'subdivision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_zones');
    }
};
