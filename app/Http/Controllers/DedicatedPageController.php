<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DedicatedSite;
use Illuminate\Contracts\View\View;

/**
 * Marketing sub-pages of the dedicated client site; they do not exist while the platform runs multi-tenant.
 */
class DedicatedPageController extends Controller
{
    public function __invoke(string $page): View
    {
        $site = DedicatedSite::resolve();

        if ($site === null) {
            abort(404);
        }

        $view = match ($page) {
            'about' => 'tenants.pages.about',
            'speakers' => 'tenants.pages.speakers',
            'packages' => 'tenants.pages.packages',
            'highlights' => 'tenants.pages.highlights',
            'gallery' => 'tenants.pages.gallery',
            'partnership' => 'tenants.pages.partnership',
            'faq' => 'tenants.pages.faq',
            'contact' => 'tenants.pages.contact',
            default => abort(404),
        };

        return view($view, $site->viewData());
    }
}
