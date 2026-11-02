<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', static function (Blueprint $table) {
            $table->json('customization')->nullable()->after('status');
        });

        Schema::table('ticket_types', static function (Blueprint $table) {
            $table->json('perks')->nullable()->after('available_quantity');
            $table->boolean('is_active')->default(true)->after('perks');
        });

        Schema::table('attendees', static function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('checked_in_by');
        });

        Schema::create('event_speakers', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title_role')->nullable();
            $table->string('topic')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_featured')->default(false);
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        Schema::create('event_schedules', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('day_label')->default('Day 1')->index();
            $table->string('session_title');
            $table->string('speaker_name')->nullable();
            $table->string('venue_hall')->nullable();
            $table->text('description')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('event_faqs', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->string('category')->default('general')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('event_form_fields', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('input_type')->default('text'); // text, select, radio, checkbox, textarea
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['event_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_form_fields');
        Schema::dropIfExists('event_faqs');
        Schema::dropIfExists('event_schedules');
        Schema::dropIfExists('event_speakers');

        Schema::table('attendees', static function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::table('ticket_types', static function (Blueprint $table) {
            $table->dropColumn(['perks', 'is_active']);
        });

        Schema::table('events', static function (Blueprint $table) {
            $table->dropColumn('customization');
        });
    }
};
