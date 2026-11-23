<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrganizationStatus;
use App\Models\Organization;

/**
 * Public pages of a suspended or archived organisation show "unavailable" instead (503 while suspended, 410 once
 * archived), without saying why. Ticket pages and their PDFs are not public pages of the organisation: buyers keep them.
 */
final class OrganizationAvailability
{
    public static function ensure(Organization $organization): void
    {
        if ($organization->isActive()) {
            return;
        }

        abort(response()->view(
            'errors.organization-unavailable',
            ['organizationName' => $organization->name],
            $organization->status === OrganizationStatus::Archived ? 410 : 503,
        ));
    }
}
