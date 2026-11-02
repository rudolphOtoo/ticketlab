<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Bus;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int|null $user_id
 * @property string $subject
 * @property string $body
 * @property string $audience
 * @property int $recipients_count
 * @property string|null $batch_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Event $event
 * @property-read User|null $sender
 */
class Broadcast extends Model
{
    use BelongsToOrganization;

    public const array AUDIENCES = ['all', 'checked_in', 'pending'];

    protected $fillable = [
        'organization_id',
        'event_id',
        'user_id',
        'subject',
        'body',
        'audience',
        'recipients_count',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'recipients_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function batch(): ?Batch
    {
        return $this->batch_id === null ? null : Bus::findBatch($this->batch_id);
    }
}
