<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\DedicatedSite;
use Illuminate\Contracts\View\View;

/**
 * Marketing sub-pages for an organization in multi-tenant bespoke mode.
 */
class TenantPageController extends Controller
{
    public function __invoke(string $org_slug, string $page): View
    {
        $organization = Organization::query()->where('slug', $org_slug)->firstOrFail();
        $site = DedicatedSite::forOrganization($organization);

        $view = match ($page) {
            'about', 'about-us' => 'tenants.pages.about',
            'speakers' => 'tenants.pages.speakers',
            'conference-packages', 'packages' => 'tenants.pages.packages',
            'highlights' => 'tenants.pages.highlights',
            'gallery' => 'tenants.pages.gallery',
            'partnership' => 'tenants.pages.partnership',
            'faq', 'faqs' => 'tenants.pages.faq',
            'contact' => 'tenants.pages.contact',
            default => abort(404),
        };

        return view($view, $site->viewData());
    }
}

