<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'subdivision_id')) {
                $table
                    ->foreignId('subdivision_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained()
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('attendee')->after('email')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
            if (Schema::hasColumn('users', 'subdivision_id')) {
                $table->dropForeign(['subdivision_id']);
                $table->dropColumn('subdivision_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
