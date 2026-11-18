<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passes sold before zones were required have no zone. They are assigned one afterwards (by an organisation admin
 * or by the buyer choosing once), and every such change is recorded in the organisation's audit trail.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('attendees', static function (Blueprint $table): void {
            $table->timestamp('zone_assigned_at')->nullable()->after('subdivision_id');
            $table
                ->foreignId('zone_assigned_by')
                ->nullable()
                ->after('zone_assigned_at')
                ->constrained('users')
                ->nullOnDelete();
            // "admin" (organisation admin on the roster) or "buyer" (self-service choice on the ticket page).
            $table->string('zone_assigned_via', 16)->nullable()->after('zone_assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('zone_assigned_by');
            $table->dropColumn(['zone_assigned_at', 'zone_assigned_via']);
        });

        Schema::dropIfExists('audit_logs');
    }
};
