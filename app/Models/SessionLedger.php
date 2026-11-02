<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $subdivision_id
 * @property string $session_name
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $locked_at
 * @property int|null $locked_by
 * @property string $total_online_momo
 * @property string $total_cash
 * @property int $total_attendees
 * @property string|null $snapshot_pdf_path
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Subdivision|null $subdivision
 * @property-read User|null $lockedByUser
 */
class SessionLedger extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'subdivision_id',
        'session_name',
        'opened_at',
        'locked_at',
        'locked_by',
        'total_online_momo',
        'total_cash',
        'total_attendees',
        'snapshot_pdf_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'locked_at' => 'datetime',
            'total_online_momo' => 'decimal:2',
            'total_cash' => 'decimal:2',
            'total_attendees' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Subdivision, $this>
     */
    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
