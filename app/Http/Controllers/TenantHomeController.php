<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\DedicatedSite;
use Illuminate\Contracts\View\View;

class TenantHomeController extends Controller
{
    /**
     * Render the bespoke showcase landing page for an organization in multi-tenant mode.
     */
    public function __invoke(string $org_slug): View
    {
        $organization = Organization::query()->where('slug', $org_slug)->firstOrFail();
        $site = DedicatedSite::forOrganization($organization);

        // Developer custom tenant template override
        $override = "tenants.{$organization->slug}.home";

        return view(view()->exists($override) ? $override : 'tenants.default-bespoke', $site->viewData());
    }
}

