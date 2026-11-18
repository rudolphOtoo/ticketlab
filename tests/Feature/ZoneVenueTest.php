<?php

declare(strict_types=1);

use App\Livewire\Admin\ZoneVenues;
use App\Mail\TicketIssuedMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventZone;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Payment\TicketPassPdf;
use App\Support\ActiveEvent;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create(['slug' => 'gmsu', 'subdivision_label' => 'Zone']);
    $this->kumasi = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kumasi']);
    $this->capeCoast = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Cape Coast']);
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'venue' => 'KNUST Great Hall, Kumasi',
        'status' => 'published',
    ]);
    EventZone::query()->create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'subdivision_id' => $this->capeCoast->id,
        'venue' => 'UCC Main Auditorium',
        'address' => 'University of Cape Coast',
        'map_url' => 'https://maps.example.test/ucc',
        'start_at' => '2027-03-06 10:00:00',
    ]);
    $tier = TicketType::factory()->create(['event_id' => $this->event->id]);

    $this->order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->org->id,
            'subdivision_id' => $this->capeCoast->id,
        ]);
    $this->pass = Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'order_id' => $this->order->id,
        'subdivision_id' => $this->capeCoast->id,
        'ticket_type_id' => $tier->id,
    ]);
});

test('the PDF for a Cape Coast pass prints the Cape Coast venue, not the event or Kumasi hall', function () {
    $captured = null;
    View::composer('pdf.ticket-pass', static function ($view) use (&$captured): void {
        $captured = $view->getData();
    });

    app(TicketPassPdf::class)->render($this->order->fresh())->output();

    $html = view('pdf.ticket-pass', $captured)->render();

    expect($html)
        ->toContain('UCC Main Auditorium, University of Cape Coast')
        ->toContain('Sat, 6 Mar 2027 · 10:00 AM')
        ->toContain('Cape Coast')
        ->not->toContain('KNUST Great Hall');
});

test('the ticket email for a Cape Coast pass shows the Cape Coast zone and venue', function () {
    $mail = new TicketIssuedMail($this->order->fresh()->load('attendees.ticketType.event'), 'pdf');

    $mail
        ->assertSeeInHtml('Cape Coast')
        ->assertSeeInHtml('UCC Main Auditorium, University of Cape Coast')
        ->assertSeeInHtml('https://maps.example.test/ucc', false)
        ->assertDontSeeInHtml('KNUST Great Hall');
});

test('the online ticket page shows the buyer zone venue', function () {
    $this
        ->get(route('public.tickets.show', ['order' => $this->order->reference]))
        ->assertOk()
        ->assertSee('UCC Main Auditorium')
        ->assertSee('Zone: Cape Coast')
        ->assertDontSee('KNUST Great Hall');
});

test('the event page lists every zone with its own venue, and zones without one fall back to the event venue', function () {
    $this
        ->get('/gmsu/convention')
        ->assertOk()
        ->assertSee('UCC Main Auditorium, University of Cape Coast')
        ->assertSee('Open map')
        ->assertSeeInOrder(['Cape Coast', 'UCC Main Auditorium', 'Kumasi', 'KNUST Great Hall'])
        ->assertDontSee('Campus / Branch');
});

test('an organisation admin edits every zone venue', function () {
    $admin = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin']);
    $this->actingAs($admin);
    ActiveEvent::set($this->event);

    Livewire::test(ZoneVenues::class)
        ->assertSee('Kumasi')
        ->assertSee('Cape Coast')
        ->set("forms.{$this->kumasi->id}.venue", 'KNUST Great Hall')
        ->set("forms.{$this->kumasi->id}.map_url", 'javascript:alert(1)')
        ->call('save', $this->kumasi->id)
        ->assertHasErrors("forms.{$this->kumasi->id}.map_url")
        ->set("forms.{$this->kumasi->id}.map_url", 'https://maps.example.test/knust')
        ->call('save', $this->kumasi->id)
        ->assertHasNoErrors();

    expect(EventZone::query()->where('subdivision_id', $this->kumasi->id)->value('venue'))->toBe('KNUST Great Hall');
});

test('a zone admin edits only their own zone venue and is refused for another zone', function () {
    $kumasiAdmin = User::factory()->create([
        'organization_id' => $this->org->id,
        'subdivision_id' => $this->kumasi->id,
        'role' => 'admin',
    ]);
    $this->actingAs($kumasiAdmin);
    ActiveEvent::set($this->event);

    $component = Livewire::test(ZoneVenues::class)->assertSee('Save Kumasi')->assertDontSee('Save Cape Coast');

    $component
        ->set("forms.{$this->kumasi->id}.venue", 'Kumasi Hall')
        ->call('save', $this->kumasi->id)
        ->assertHasNoErrors();

    // A crafted call for Cape Coast is refused on the server and changes nothing.
    $component
        ->set("forms.{$this->capeCoast->id}.venue", 'Hijacked Hall')
        ->call('save', $this->capeCoast->id)
        ->assertForbidden();

    expect(EventZone::query()->where('subdivision_id', $this->capeCoast->id)->value('venue'))
        ->toBe('UCC Main Auditorium')
        ->and(EventZone::query()->where('subdivision_id', $this->kumasi->id)->value('venue'))
        ->toBe('Kumasi Hall');
});

test('ushers cannot open the zone venue editor', function () {
    $usher = User::factory()->create([
        'organization_id' => $this->org->id,
        'subdivision_id' => $this->kumasi->id,
        'role' => 'usher',
    ]);

    $this->actingAs($usher)->get(route('admin.zone-venues'))->assertForbidden();
});
