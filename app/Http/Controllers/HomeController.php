<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        if (PlatformSetting::isDedicatedMode()) {
            $homeSlug = PlatformSetting::getHomeOrgSlug();

            if ($homeSlug !== null) {
                $organization = Organization::query()->where('slug', $homeSlug)->first();

                if ($organization !== null) {
                    $event = Event::query()
                        ->where('organization_id', $organization->id)
                        ->where('status', 'published')
                        ->with(['ticketTypes', 'speakers', 'schedules', 'faqs'])
                        ->latest('id')
                        ->first()
                        ?? Event::query()
                            ->where('organization_id', $organization->id)
                            ->with(['ticketTypes', 'speakers', 'schedules', 'faqs'])
                            ->latest('id')
                            ->first();

                    // Developer custom tenant template override
                    if (view()->exists("tenants.{$homeSlug}.home")) {
                        return view("tenants.{$homeSlug}.home", [
                            'organization' => $organization,
                            'event' => $event,
                        ]);
                    }

                    // Default universal bespoke client landing page
                    return view('tenants.default-bespoke', [
                        'organization' => $organization,
                        'event' => $event,
                    ]);
                }
            }
        }

        // Multi-tenant platform discovery hub
        return view('welcome');
    }
}
