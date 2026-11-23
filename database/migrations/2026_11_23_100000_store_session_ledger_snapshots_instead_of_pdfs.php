<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger PDFs are no longer stored anywhere. Locking a session now saves what its PDF shows (event, expected
 * capacity and the per-zone admissions at that moment) in `snapshot`, and the PDF is drawn from it and streamed to
 * the authorised admin on every download, so the same figures come back each time.
 *
 * Ledgers locked before this change keep their frozen totals (online, cash, attendees); their zone breakdown lived
 * only inside the stored PDF and is not recovered.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('session_ledgers', static function (Blueprint $table): void {
            $table->json('snapshot')->nullable()->after('total_attendees');
        });

        Schema::table('session_ledgers', static function (Blueprint $table): void {
            $table->dropColumn('snapshot_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('session_ledgers', static function (Blueprint $table): void {
            $table->string('snapshot_pdf_path')->nullable()->after('total_attendees');
        });

        Schema::table('session_ledgers', static function (Blueprint $table): void {
            $table->dropColumn('snapshot');
        });
    }
};
