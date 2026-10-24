<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

trait BelongsToOrganization
{
    use BelongsToTenant;

    public function getTenantIdColumn(): string
    {
        return 'organization_id';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
