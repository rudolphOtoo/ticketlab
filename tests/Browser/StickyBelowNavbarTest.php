<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;

/*
 * Sticky panels must never slide under a fixed navbar or header. The event page's order summary used to stick at
 * 24px, under the public navbar (which ends at 62px, 74px when its links wrap at 1024px).
 */

/**
 * Every visible sticky element that overlaps a fixed or sticky top bar (a nav or header within 150px of the top),
 * after the page has been scrolled. Elements inside the bar itself don't count.
 */
const STICKY_OVERLAPS = <<<'JS'
    (() => {
        const box = (e) => e.getBoundingClientRect();
        const shown = (e) => {
            const r = box(e), s = getComputedStyle(e);
            return r.width > 0 && r.height > 0 && s.display !== 'none' && s.visibility !== 'hidden';
        };
        const pinned = (e) => {
            for (let n = e; n && n !== document.body; n = n.parentElement) {
                if (['fixed', 'sticky'].includes(getComputedStyle(n).position)) return true;
            }
            return false;
        };
        const bars = [...document.querySelectorAll('nav, header')].filter((e) => shown(e) && pinned(e) && box(e).top < 150);
        const stickies = [...document.querySelectorAll('body *')]
            .filter((e) => getComputedStyle(e).position === 'sticky' && shown(e));
        const overlaps = [];
        for (const s of stickies) {
            for (const b of bars) {
                if (b.contains(s) || s.contains(b)) continue;
                const a = box(s), c = box(b);
                if (a.left < c.right && c.left < a.right && a.top < c.bottom && c.top < a.bottom) {
                    overlaps.push(`${s.tagName}.${s.className} under ${b.tagName} (${Math.round(a.top)} < ${Math.round(c.bottom)})`);
                }
            }
        }
        return { stickies: stickies.length, overlaps };
    })()
    JS;

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU', 'subdivision_label' => 'Zone']);
    $this->zone = payable_zone($this->org, 'Zone A');
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'status' => 'published',
        'description' => str_repeat("A long programme description so the page scrolls well past the summary.\n", 40),
    ]);
    TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Standard',
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
});

test('on desktop the order summary sticks below the navbar, expanded or collapsed', function (int $width): void {
    $page = visit('/gmsu/convention')->resize($width, 900)->assertNoJavaScriptErrors();
    $page->script('window.scrollTo(0, 1200)');
    $page->wait(0.2);

    $stuck = $page->script("Math.round(document.querySelector('[data-order-summary]').getBoundingClientRect().top)");
    $result = $page->script(STICKY_OVERLAPS);

    expect($result['stickies'])->toBe(1)->and($result['overlaps'])->toBe([])->and($stuck)->toBe(96);

    $page->click('[aria-label="Minimize Navigation"]');
    $page->wait(0.4);

    expect($page->script(STICKY_OVERLAPS)['overlaps'])->toBe([]);
})->with([1024, 1280, 1440, 1920]);

test('on a short desktop window the stuck summary still shows its Proceed button', function (): void {
    $page = visit('/gmsu/convention')->resize(1280, 720);
    $page->click('Zone A');
    $page->script('window.scrollTo(0, 1200)');
    $page->wait(0.2);

    expect($page->script(
        "document.querySelector('[data-proceed]').getBoundingClientRect().bottom <= window.innerHeight",
    ))
        ->toBeTrue()
        ->and($page->script(STICKY_OVERLAPS)['overlaps'])
        ->toBe([]);
});

test('sticky panels on admin and ops screens never sit under a header', function (string $route): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->actingAs($admin);
    session([ActiveEvent::SESSION_KEY => $this->event->id]);

    $page = visit(route($route))->resize(1440, 900)->assertNoJavaScriptErrors();
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.2);

    // Their headers scroll away with the page, so a sticky panel at top-4 has nothing to slide under.
    expect($page->script(STICKY_OVERLAPS)['overlaps'])->toBe([]);
})->with(['admin.studio', 'ops.cash-desk']);
