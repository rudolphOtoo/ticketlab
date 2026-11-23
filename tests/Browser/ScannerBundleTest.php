<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;

/*
 * The door scanner's QR library (html5-qrcode, ~109 KB gzipped) is its own Vite entry, loaded only by the scanner
 * view. Every other page, the event page above all, never downloads it.
 */

/** Scripts the page has loaded that belong to the scanner entry (public/build/assets/scanner-*.js). */
const SCANNER_SCRIPTS = "performance.getEntriesByType('resource').map(e => e.name).filter(n => /\\/scanner-[^/]*\\.js/.test(n))";

beforeEach(function (): void {
    // What ships: public/build, even when a Vite dev server runs locally (its reloads would also disturb the test).
    Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));

    $this->org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU', 'subdivision_label' => 'Zone']);
    payable_zone($this->org, 'Zone A');
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'status' => 'published',
    ]);
    TicketType::factory()->create(['event_id' => $this->event->id, 'name' => 'Standard', 'price' => 50]);
});

test('the event page never loads the scanner library', function (): void {
    $page = visit('/gmsu/convention')->assertNoJavaScriptErrors();
    $page->click('Standard')->assertSeeIn('[data-total]', 'GHS');

    expect($page->script(SCANNER_SCRIPTS))
        ->toBe([])
        ->and($page->script('typeof window.Html5Qrcode'))
        ->toBe('undefined');
});

test('the scanner loads its library and starts the camera', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->actingAs($admin);
    session([ActiveEvent::SESSION_KEY => $this->event->id]);

    $page = visit(route('ops.scanner'))->assertNoJavaScriptErrors();

    // The scanner created its Html5Qrcode reader and tried the camera: it is scanning (the test browser's fake
    // camera) or reports the camera offline, but it is no longer waiting for the library.
    $page->wait(1.5);
    $scanner = "Alpine.\$data(document.getElementById('qr-reader'))";

    expect($page->script('typeof window.Html5Qrcode'))
        ->toBe('function')
        ->and($page->script(SCANNER_SCRIPTS))
        ->not->toBe([])->and($page->script("{$scanner}.html5QrCode !== null"))->toBeTrue()->and($page->script(
            "{$scanner}.isScanning || {$scanner}.cameraError !== null",
        ))->toBeTrue()->and($page->script("{$scanner}.cameraStatusText"))
        ->not->toBe('Camera engine loading...');
});
