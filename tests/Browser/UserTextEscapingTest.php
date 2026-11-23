<?php

declare(strict_types=1);

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\EventZone;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;

/*
 * Everything organisers type is shown as text: never run as script, never able to break the page's own scripts.
 *
 * NAME closes a <script> block and opens a new one; BREAKOUT tries to escape a JS string inside an Alpine or Livewire
 * attribute (the old `@click="activeFilter = '{{ $tag }}'"` pattern) and would set window.__xss if it ever ran.
 */
const NAME = 'St. Mary\'s "Youth" </script><script>alert(1)</script>';

const BREAKOUT = "x'); window.__xss = 1; ('";

/** True when something injected ran: the marker was set, or a script element holds the injected code. */
const INJECTION_RAN = "window.__xss !== undefined || [...document.scripts].some(s => s.textContent.trim() === 'alert(1)')";

beforeEach(function (): void {
    $this->org = Organization::factory()->create([
        'slug' => 'stmarys',
        'name' => NAME,
        'subdivision_label' => NAME,
        'settings' => [
            'bespoke' => [
                'gallery' => [
                    'images' => [
                        ['url' => 'https://example.test/a.jpg', 'title' => NAME, 'caption' => NAME, 'tag' => NAME],
                        [
                            'url' => 'https://example.test/b.jpg',
                            'title' => BREAKOUT,
                            'caption' => '',
                            'tag' => BREAKOUT,
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $this->zone = payable_zone($this->org, NAME);
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'youth-camp',
        'title' => NAME,
        'description' => NAME,
        'venue' => NAME,
        'status' => 'published',
        'start_at' => now()->addDays(5),
        'end_at' => now()->addDays(5)->addHours(3),
        'customization' => ['headline' => NAME, 'tagline' => NAME],
    ]);
    EventZone::query()->create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'subdivision_id' => $this->zone->id,
        'venue' => NAME,
        'address' => BREAKOUT,
    ]);
    $this->tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => NAME,
        'description' => BREAKOUT,
        'price' => 50,
    ]);
    EventSpeaker::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'name' => NAME,
        'title_role' => BREAKOUT,
        'bio' => NAME,
        'sort_order' => 1,
    ]);
    EventSchedule::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'title' => NAME,
        'day_label' => BREAKOUT,
        'start_time' => '18:00',
        'sort_order' => 1,
    ]);
    EventFaq::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'question' => NAME,
        'answer' => BREAKOUT,
        'sort_order' => 1,
    ]);

    $this->order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->org->id,
            'subdivision_id' => $this->zone->id,
            'customer_name' => NAME,
        ]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'order_id' => $this->order->id,
        'subdivision_id' => $this->zone->id,
        'ticket_type_id' => $this->tier->id,
        'name' => NAME,
    ]);
});

function assert_text_is_inert(object $page): void
{
    $page->assertNoJavaScriptErrors();

    expect($page->script(INJECTION_RAN))->toBeFalse();
}

test('the event page and its navbar show organiser text as text', function (): void {
    $page = visit('/stmarys/youth-camp')->resize(1280, 900);

    assert_text_is_inert($page);
    $page->assertSee(NAME)->assertSee(BREAKOUT);

    // The page's own scripts still work: choosing the zone updates the summary without a request.
    $page->click('[data-zone="' . $this->zone->id . '"]')->assertSeeIn('[data-zone-name]', NAME);
    assert_text_is_inert($page);
});

test('the ticket page shows organiser and buyer text as text', function (): void {
    $page = visit('/tickets/' . $this->order->reference);

    assert_text_is_inert($page);
    $page->assertSee(NAME);
});

test('the dedicated gallery filters by an organiser tag without running it', function (): void {
    $page = visit('/stmarys/page/gallery');

    assert_text_is_inert($page);
    $page->assertSee(NAME);

    // Click the filter chip whose tag is the breakout string: the click runs the chip's Alpine expression.
    $page->script(
        '[...document.querySelectorAll("button")].find(b => b.textContent.trim() === '
        . json_encode(BREAKOUT)
        . ').click()',
    );
    $page->wait(0.3);
    assert_text_is_inert($page);
});

test('admin screens show organiser text as text', function (string $route): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->actingAs($admin);
    session([ActiveEvent::SESSION_KEY => $this->event->id]);

    $page = visit(route($route));

    assert_text_is_inert($page);
})->with([
    'admin.index',
    'admin.attendees',
    'admin.events',
    'admin.subdivisions',
    'admin.tickets',
    'admin.zone-venues',
    'admin.payouts',
    'admin.studio',
    'admin.studio.bespoke',
]);
