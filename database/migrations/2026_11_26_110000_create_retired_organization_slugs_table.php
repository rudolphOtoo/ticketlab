<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A deleted organisation's web address (slug) is never given to another organisation, so links printed or shared for
 * it can't later lead to a different church. Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('retired_organization_slugs', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('organization_name');
            $table->timestamp('retired_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_organization_slugs');
    }
};
