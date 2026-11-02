<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('email')->constrained('zones')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('gate_usher')->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mirror the guarded up(): later migrations may already have removed these columns.
        Schema::table('users', static function (Blueprint $table) {
            if (Schema::hasColumn('users', 'zone_id')) {
                $table->dropConstrainedForeignId('zone_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
