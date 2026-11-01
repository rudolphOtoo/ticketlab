<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foreign keys are not indexed automatically on PostgreSQL or SQLite, so every "children of this parent"
 * lookup (an event's tiers and programme, an order's passes, an organisation's staff) scanned the table.
 * Programme indexes include sort_order so the ordered read is served straight from the index.
 */
return new class extends Migration {
    /** @var array<string, list<list<string>>> */
    private array $indexes = [
        'ticket_types' => [['event_id', 'is_active']],
        'attendees' => [['ticket_type_id'], ['order_id']],
        'event_speakers' => [['event_id', 'sort_order']],
        'event_schedules' => [['event_id', 'sort_order']],
        'event_faqs' => [['event_id', 'is_published', 'sort_order']],
        'events' => [['organization_id', 'status', 'start_at']],
        'users' => [['organization_id', 'role']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, static function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, static function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }
};
