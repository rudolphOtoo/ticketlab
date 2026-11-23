<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The web address of a deleted organisation, kept so no other organisation can take it.
 *
 * @property int $id
 * @property string $slug
 * @property string $organization_name
 * @property CarbonImmutable $retired_at
 */
class RetiredOrganizationSlug extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug',
        'organization_name',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }
}
