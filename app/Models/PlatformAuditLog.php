<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What platform super admins did to organisations (suspend, archive, reactivate, delete), with the reason they gave.
 * Rows are only ever appended, and outlive the organisation: it is identified by id, name and web address.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $organization_id
 * @property string $organization_name
 * @property string $organization_slug
 * @property string $action
 * @property string $reason
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
class PlatformAuditLog extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'organization_id',
        'organization_name',
        'organization_slug',
        'action',
        'reason',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
