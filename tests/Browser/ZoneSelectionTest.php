<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventZone;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\TicketTypeZoneCapacity;

/*
 * The buyer's choices (tier, quantity, zone) live in the browser: every tap must update the page at once, with no
 * Livewire request, and only Pay talks to the server.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU', 'subdivision_label' => 'Zone']);
    $this->zoneA = payable_zone($this->org, 'Zone A');
    $this->zoneB = payable_zone($this->org, 'Zone B');
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'status' => 'published',
        'venue' => 'National Theatre, Accra',
        'start_at' => now()->addDays(10)->setTime(18, 0),
        'end_at' => now()->addDays(10)->setTime(21, 0),
    ]);
    EventZone::query()->create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'subdivision_id' => $this->zoneB->id,
        'venue' => 'UCC Main Auditorium',
        'address' => 'Cape Coast',
        'start_at' => '2027-03-06 10:00:00',
    ]);
    $this->standard = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Standard',
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
    $this->vip = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'VIP',
        'price' => 100,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
    // Zone B has only 7 Standard seats, so its "seats left" differs from Zone A's.
    TicketTypeZoneCapacity::query()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->standard->id,
        'subdivision_id' => $this->zoneB->id,
        'capacity' => 7,
    ]);
});

/** Livewire update requests the page has sent so far. */
const LIVEWIRE_REQUESTS = "performance.getEntriesByType('resource').filter(e => /livewire[^/]*\\/update/.test(e.name)).length";

/** Whether the zone card is drawn as selected (its border uses the focused colour, not the hairline). */
function zone_card_border(object $page, int $zoneId): string
{
    // Let the card's colour transition finish before reading it.
    $page->wait(0.4);

    return (string) $page->script(
        "getComputedStyle(document.querySelector('[data-zone=\"{$zoneId}\"]')).borderTopColor",
    );
}

test('tapping Zone A then Zone B moves the highlight to B only, and the venue, date and seats left follow', function (): void {
    $page = visit('/gmsu/convention')->assertNoJavaScriptErrors();
    $unselectedBorder = zone_card_border($page, $this->zoneA->id);
    $requestsBefore = $page->script(LIVEWIRE_REQUESTS);

    $page->click('Zone A');
    $page
        ->assertChecked("#zone-{$this->zoneA->id}")
        ->assertNotChecked("#zone-{$this->zoneB->id}")
        ->assertSeeIn('[data-zone-name]', 'Zone A')
        ->assertSeeIn('[data-ticket="' . $this->standard->id . '"] [data-seats-left]', '100');
    expect(zone_card_border($page, $this->zoneA->id))->not->toBe($unselectedBorder);

    $page->click('Zone B');
    $page
        ->assertChecked("#zone-{$this->zoneB->id}")
        ->assertNotChecked("#zone-{$this->zoneA->id}")
        ->assertSeeIn('[data-zone-name]', 'Zone B')
        ->assertSeeIn('[data-zone-when]', 'Sat, 6 Mar 2027 · 10:00 AM')
        ->assertSeeIn('[data-zone-place]', 'UCC Main Auditorium')
        ->assertSeeIn('[data-ticket="' . $this->standard->id . '"] [data-seats-left]', '7');
    expect(zone_card_border($page, $this->zoneB->id))
        ->not
        ->toBe($unselectedBorder)
        ->and(zone_card_border($page, $this->zoneA->id))
        ->toBe($unselectedBorder)
        // The whole exchange happened in the browser.
        ->and($page->script(LIVEWIRE_REQUESTS))
        ->toBe($requestsBefore);
});

test('the zone list is a radio group the keyboard can drive', function (): void {
    $page = visit('/gmsu/convention');

    $page->click('Zone A')->keys("#zone-{$this->zoneA->id}", 'ArrowDown');

    $page
        ->assertChecked("#zone-{$this->zoneB->id}")
        ->assertNotChecked("#zone-{$this->zoneA->id}")
        ->assertSeeIn('[data-zone-name]', 'Zone B');
    expect($page->script("document.querySelectorAll('[data-zone-venues] input[type=radio][name=zone]').length"))
        ->toBe(2);
});

test('switching GHS 50 to GHS 100 and changing quantity updates the total at once, without a request', function (): void {
    $page = visit('/gmsu/convention');
    $requestsBefore = $page->script(LIVEWIRE_REQUESTS);

    // 50.00 grossed up for the 1.95% Paystack fee.
    $page->assertSeeIn('[data-total]', 'GHS 51.00');

    $page->click('VIP')->assertSeeIn('[data-total]', 'GHS 101.99');
    $page
        ->click('[aria-label="More tickets"]')
        ->assertSeeIn('[data-quantity]', '2')
        ->assertSeeIn('[data-total]', 'GHS 203.98');
    $page->click('Standard')->assertSeeIn('[data-total]', 'GHS 101.99');

    expect($page->script(LIVEWIRE_REQUESTS))->toBe($requestsBefore);
});

test('quantity is capped at the seats left in the chosen zone', function (): void {
    $page = visit('/gmsu/convention');

    $page->click('Zone B');
    foreach (range(1, 6) as $_) {
        $page->click('[aria-label="More tickets"]');
    }

    // Zone B has 7 Standard seats: the eighth is not offered.
    $page->assertSeeIn('[data-quantity]', '7');
    expect($page->script("document.querySelector('[aria-label=\"More tickets\"]').disabled"))->toBeTrue();
});

test('pay sends the choice to the server once, which holds the seats at the total shown', function (): void {
    $page = visit('/gmsu/convention');

    $page
        ->click('VIP')
        ->click('Zone B')
        ->click('[aria-label="More tickets"]')
        ->click('[data-proceed]')
        ->assertVisible('[data-checkout-modal]')
        ->assertSeeIn('[data-chosen-zone-venue]', 'Zone B')
        ->type('#customerName', 'Ama Mensah')
        ->type('#customerEmail', 'ama@example.com')
        ->type('#customerPhone', '0244000000')
        ->click('[data-pay]')
        ->wait(2);

    $order = Order::query()->sole();
    expect($order->subdivision_id)
        ->toBe($this->zoneB->id)
        ->and($order->metadata['ticket_type_id'])
        ->toBe($this->vip->id)
        ->and($order->metadata['quantity'])
        ->toBe(2)
        ->and((float) $order->gross_amount)
        ->toBe(203.98);
});

test('proceeding without a zone asks for one in the page instead of opening checkout', function (): void {
    visit('/gmsu/convention')
        ->click('[data-proceed]')
        ->assertSee('Choose your zone to continue.')
        ->assertMissing('[data-checkout-modal]');
});

test('a server validation error keeps the checkout open and the buyer\'s choices intact', function (): void {
    $page = visit('/gmsu/convention');

    $page
        ->click('VIP')
        ->click('Zone B')
        ->click('[data-proceed]')
        ->click('[data-pay]')
        ->assertSee('The customer name field is required.')
        ->assertVisible('[data-checkout-modal]')
        ->assertChecked("#zone-{$this->zoneB->id}")
        ->assertSeeIn('[data-total]', 'GHS 101.99')
        ->assertEnabled('[data-pay]');

    expect(Order::query()->count())->toBe(0);
});

/** Opens checkout for the given tier, zone and quantity, and fills in the buyer. */
function open_checkout(object $page, string $tier, string $zone, int $quantity = 1): object
{
    $page->click($tier)->click($zone);

    for ($added = 1; $added < $quantity; $added++) {
        $page->click('[aria-label="More tickets"]');
    }

    return $page
        ->click('[data-proceed]')
        ->type('#customerName', 'Ama Mensah')
        ->type('#customerEmail', 'ama@example.com')
        ->type('#customerPhone', '0244000000');
}

test('a price change before Pay shows a review message with the new total and keeps the choices', function (): void {
    $page = open_checkout(visit('/gmsu/convention'), 'VIP', 'Zone B');
    $page->assertSeeIn('[data-modal-total]', 'GHS 101.99');

    // The organiser raises the price while the buyer is filling in the form.
    $this->vip->update(['price' => 120]);

    $page
        ->click('[data-pay]')
        ->assertSee('Prices or seats changed. Please review.')
        ->assertVisible('[data-checkout-modal]')
        ->assertSeeIn('[data-modal-total]', 'GHS 122.39')
        ->assertSeeIn('[data-total]', 'GHS 122.39')
        ->assertChecked('input[name=ticket_type][value="' . $this->vip->id . '"]')
        ->assertChecked("#zone-{$this->zoneB->id}");
    expect(Order::query()->count())->toBe(0);

    // Paying again at the total now shown goes through.
    $page->click('[data-pay]')->wait(2);
    expect((float) Order::query()->sole()->gross_amount)->toBe(122.39);
});

test('seats running out before Pay trims the quantity to what is left and updates the total', function (): void {
    $page = open_checkout(visit('/gmsu/convention'), 'Standard', 'Zone B', quantity: 5);
    $page->assertSeeIn('[data-quantity]', '5');

    // Someone else holds 5 of Zone B's 7 Standard seats in the meantime.
    app(App\Services\Reservation\TicketReservationService::class)->holdOrReuse(
        $this->standard,
        5,
        'someone-else',
        $this->zoneB->id,
    );

    $page
        ->click('[data-pay]')
        ->assertSee('Prices or seats changed. Please review.')
        ->assertSeeIn('[data-quantity]', '2')
        ->assertSeeIn('[data-modal-total]', 'GHS 101.99')
        ->assertChecked("#zone-{$this->zoneB->id}");

    $page->click('[data-pay]')->wait(2);
    expect(Order::query()->sole()->metadata['quantity'])->toBe(2);
});
