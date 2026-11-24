<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per scheduler call (/api/run-scheduler), shown on the platform health panel and pruned after 14 days by the
 * scheduler's cleanup task. Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduler_runs', static function (Blueprint $table): void {
            $table->id();
            // idle, locked, ran, or running (a run that never finished: killed by the platform).
            $table->string('status', 16);
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('ran')->nullable();
            $table->json('skipped')->nullable();
            $table->json('counts')->nullable();
            $table->unsignedSmallInteger('errors')->default(0);
            // Short messages with their task; never tokens or URLs.
            $table->json('error_messages')->nullable();

            // "Last successful run": status in (idle, ran), newest first.
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_runs');
    }
};
