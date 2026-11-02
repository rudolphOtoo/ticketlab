<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant ownership is explicit: every org-scoped query must constrain organization_id itself.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToOrganization
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
