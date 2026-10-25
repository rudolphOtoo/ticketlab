<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_ledgers', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subdivision_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_name');
            $table->dateTime('opened_at');
            $table->dateTime('locked_at')->nullable()->index();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_online_momo', 12, 2)->default(0.00);
            $table->decimal('total_cash', 12, 2)->default(0.00);
            $table->unsignedInteger('total_attendees')->default(0);
            $table->string('snapshot_pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_ledgers');
    }
};
