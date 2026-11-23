<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An archived event leaves the public pages and stops selling; its passes, ticket pages and reports stay. Separate
 * from status (draft/published) so unarchiving brings the event back exactly as it was. Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', static function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('events', static function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
