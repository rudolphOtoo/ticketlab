<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DedicatedSite;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $site = DedicatedSite::resolve();

        if ($site === null) {
            // Multi-tenant platform discovery hub
            return view('welcome');
        }

        // Developer custom tenant template override
        $override = "tenants.{$site->organization->slug}.home";

        return view(view()->exists($override) ? $override : 'tenants.default-bespoke', $site->viewData());
    }
}
