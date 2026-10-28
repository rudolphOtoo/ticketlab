<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes schema left behind by the retired devdojo/auth stack and the pre-organisation "zones" model,
 * and makes self-service accounts default to the least-privileged role.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('social_provider_user');
        Schema::dropIfExists('email_verification_codes');

        if (Schema::hasColumn('users', 'zone_id')) {
            Schema::table('users', static fn(Blueprint $table) => $table->dropConstrainedForeignId('zone_id'));
        }

        Schema::dropIfExists('zones');

        Schema::table('users', static fn(Blueprint $table) => $table->string('role')->default('attendee')->change());
    }

    public function down(): void
    {
        Schema::table('users', static fn(Blueprint $table) => $table->string('role')->default('gate_usher')->change());

        Schema::create('zones', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('paystack_subaccount_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', static function (Blueprint $table): void {
            $table->foreignId('zone_id')->nullable()->after('email')->constrained('zones')->nullOnDelete();
        });

        Schema::create('email_verification_codes', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('social_provider_user', static function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_slug');
            $table->string('provider_user_id');
            $table->string('nickname')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->text('provider_data')->nullable();
            $table->string('token', 400);
            $table->string('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'provider_slug']);
        });
    }
};
