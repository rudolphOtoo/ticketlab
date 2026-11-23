<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The ticket email is sent right after payment is confirmed; these columns record whether it went out, so the cron
 * only retries what is left (and finds that out with one indexed query). mail_quota_days counts what was sent each
 * day against Brevo's daily cap. Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->timestamp('ticket_emailed_at')->nullable();
            // Set while an email is owed: when the next attempt may start. Null once sent or given up.
            $table->timestamp('ticket_email_due_at')->nullable()->index();
            $table->unsignedSmallInteger('ticket_email_attempts')->default(0);
            $table->string('ticket_email_error', 500)->nullable();
        });

        Schema::create('mail_quota_days', static function (Blueprint $table): void {
            $table->date('day')->primary();
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('ticket')->default(0);
            $table->unsignedInteger('transactional')->default(0);
            $table->unsignedInteger('zone_choice')->default(0);
            $table->unsignedInteger('broadcast')->default(0);
            $table->unsignedInteger('other')->default(0);
            // Emails that found no room and were moved to a later day.
            $table->unsignedInteger('deferred')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_quota_days');

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropIndex(['ticket_email_due_at']);
            $table->dropColumn([
                'ticket_emailed_at',
                'ticket_email_due_at',
                'ticket_email_attempts',
                'ticket_email_error',
            ]);
        });
    }
};
