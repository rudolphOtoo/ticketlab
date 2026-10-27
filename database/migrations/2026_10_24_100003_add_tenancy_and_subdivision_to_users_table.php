<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('subdivision_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('role')->default('attendee')->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['subdivision_id']);
            $table->dropColumn(['organization_id', 'subdivision_id', 'role']);
        });
    }
};
