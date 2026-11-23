<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A super admin can suspend an organisation (public pages unavailable, no sales, its users can't sign in, the scheduler
 * skips it, every record kept), archive it (the same, and hidden from the platform lists) and reactivate it.
 *
 * Those actions are recorded in platform_audit_logs, not audit_logs: an organisation's own trail is deleted with it,
 * while this one keeps who did what to which organisation (by id, name and web address) even after it is deleted.
 * Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', static function (Blueprint $table): void {
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('status_changed_at')->nullable();
        });

        Schema::create('platform_audit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // No foreign key: the row outlives the organisation it describes.
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('organization_name');
            $table->string('organization_slug');
            $table->string('action', 64);
            $table->text('reason');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');

        Schema::table('organizations', static function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_changed_at']);
        });
    }
};
