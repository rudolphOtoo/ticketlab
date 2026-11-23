<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;

/*
 * Livewire (with Alpine and the same plugins) is bundled into app.js from resources/js/vendor/livewire.esm.js, so it
 * ships from public/build with a hashed name and immutable caching instead of being served by PHP on every new visit.
 */

beforeEach(function (): void {
    // What ships: public/build, even when a Vite dev server runs locally.
    Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));

    $org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU', 'subdivision_label' => 'Zone']);
    payable_zone($org, 'Zone A');
    $event = Event::factory()->create(['organization_id' => $org->id, 'slug' => 'convention', 'status' => 'published']);
    TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Standard', 'price' => 50]);
});

test('Livewire runs from the built bundle, not from a script served by PHP', function (): void {
    $page = visit('/gmsu/convention')->assertNoJavaScriptErrors();

    expect($page->script(
        "performance.getEntriesByType('resource').map(e => e.name).filter(n => /\\/livewire[^/]*\\/livewire(\\.min)?\\.js/.test(n))",
    ))
        ->toBe([])
        // The page's component is live, Alpine is running, and the plugins Livewire registers are there.
        ->and($page->script('window.Livewire.all().length'))
        ->toBeGreaterThan(0)
        ->and($page->script("typeof window.Alpine.\$data(document.querySelector('[x-data]'))"))
        ->toBe('object')
        // A plugin Livewire.start() registers (focus, which also provides x-trap for the speaker modal).
        ->and($page->script("typeof window.Alpine.evaluate(document.querySelector('[x-data]'), '\$focus')"))
        ->toBe('object');
});
