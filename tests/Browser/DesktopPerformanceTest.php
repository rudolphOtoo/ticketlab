<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;

/*
 * Item 5: the event page felt slower on desktop than on a phone. Measured causes, each pinned here:
 * - every tap animated the card colour (transition-colors) and, on desktop, repainted the whole page because the
 *   sticky order summary changed in the same frames (309 M px painted per tap against 0.9 M on a phone);
 * - fonts were downloaded twice (a woff and a woff2 @font-face per weight; the woff won, the woff2 preloads were
 *   wasted);
 * - Paystack's script was parser-blocking, so Livewire and Alpine (and every tap) waited for Paystack's CDN.
 */

beforeEach(function (): void {
    $org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU', 'subdivision_label' => 'Zone']);
    $this->zone = payable_zone($org, 'Zone A');
    $event = Event::factory()->create(['organization_id' => $org->id, 'slug' => 'convention', 'status' => 'published']);
    $this->standard = TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'Standard',
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
    $this->vip = TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 100,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
});

test('on desktop a tier or zone tap is drawn in the next frame, with no request', function (): void {
    $page = visit('/gmsu/convention')->resize(1440, 900)->assertNoJavaScriptErrors();
    $requestsBefore = $page->script(LIVEWIRE_REQUESTS);
    $vip = "[data-ticket=\"{$this->vip->id}\"]";
    $unselected = $page->script("getComputedStyle(document.querySelector('{$vip}')).backgroundColor");

    expect($page->script(
        "[...document.querySelectorAll('[data-ticket], [data-zone]')].map(e => getComputedStyle(e).transitionDuration)",
    ))
        ->each
        ->toBe('0s');

    $page->click('VIP');

    // Read at once, without waiting: the selected colour is already there.
    expect($page->script("getComputedStyle(document.querySelector('{$vip}')).backgroundColor"))->not->toBe($unselected);

    $page->click('Zone A')->assertSeeIn('[data-zone-name]', 'Zone A');

    expect($page->script(LIVEWIRE_REQUESTS))->toBe($requestsBefore);
});

test('the sticky order summary keeps its repaints to itself on desktop', function (): void {
    $page = visit('/gmsu/convention')->resize(1440, 900);

    expect($page->script("getComputedStyle(document.querySelector('[data-order-summary]').parentElement).contain"))
        ->toContain('paint')
        ->and($page->script("getComputedStyle(document.querySelector('[data-order-summary]').parentElement).position"))
        ->toBe('sticky');
});

test('fonts are fetched once, as woff2, and Paystack never blocks the page', function (): void {
    $page = visit('/gmsu/convention')->resize(1440, 900)->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready.then(() => true)');

    $fonts = $page->script(
        "performance.getEntriesByType('resource').map(e => e.name).filter(n => /\\.(woff2?|ttf|otf)(\\?|$)/.test(n))",
    );

    expect($fonts)
        ->not->toBeEmpty()
        ->each->toEndWith('.woff2')->and(count($fonts))->toBe(count(array_unique($fonts)))
        // No parser-blocking Paystack <script src>: it is loaded later, async, by window.loadPaystack().
        ->and($page->script("document.querySelectorAll('script[src*=\"js.paystack.co\"]:not([async])').length"))->toBe(
            0,
        )->and($page->script('typeof window.loadPaystack'))->toBe('function');
});
