<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Payment\TicketPassPdf;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['name' => 'Grace Chapel', 'subdivision_label' => 'Zone']);
    $this->zone = Subdivision::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zone A']);
    $event = Event::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Kingdom Summit']);
    $this->ticketType = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'VIP Gold Pass']);

    $this->order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->organization->id,
            'subdivision_id' => $this->zone->id,
            'reference' => 'TKT-PDF-001',
            'paid_at' => now(),
            'metadata' => [],
        ]);

    $this->attendees = collect(['first', 'second'])->map(fn(string $seed) => Attendee::factory()->create([
        'order_id' => $this->order->id,
        'organization_id' => $this->organization->id,
        'subdivision_id' => $this->zone->id,
        'ticket_type_id' => $this->ticketType->id,
        'qr_code_hash' => hash('sha256', $seed),
    ]));
});

test('the pass pdf downloads as a valid pdf document', function () {
    $response = $this->get(route('public.tickets.pdf', ['order' => 'TKT-PDF-001']));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=tickets-TKT-PDF-001.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $pdf = (string) $response->getContent();

    expect($pdf)->toStartWith('%PDF-')->and(rtrim($pdf))->toEndWith('%%EOF');
});

test('the pass pdf has one page and one embedded qr image per attendee', function () {
    $pdf = (string) $this->get(route('public.tickets.pdf', ['order' => 'TKT-PDF-001']))->getContent();

    expect(preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf))
        ->toBe(2)
        ->and(preg_match_all('#/Subtype\s*/Image#', $pdf))
        ->toBe(2);
});

test('the embedded qr codes decode back to each pass id', function () {
    $passes = app(TicketPassPdf::class);

    $this->attendees->each(function (Attendee $attendee) use ($passes): void {
        $png = base64_decode(explode(',', $passes->qrDataUri($attendee), 2)[1], true);

        expect((string) new QRCode()->readFromBlob((string) $png))->toBe($attendee->qr_code_hash);
    });
});

test('there is no pass pdf for unpaid, empty or unknown orders', function () {
    $pending = Order::factory()->create([
        'organization_id' => $this->organization->id,
        'reference' => 'TKT-PDF-PENDING',
        'payment_status' => OrderStatus::Pending,
        'metadata' => [],
    ]);
    $empty = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->organization->id,
            'reference' => 'TKT-PDF-EMPTY',
            'metadata' => [],
        ]);

    $this->get(route('public.tickets.pdf', ['order' => $pending->reference]))->assertNotFound();
    $this->get(route('public.tickets.pdf', ['order' => $empty->reference]))->assertNotFound();
    $this->get('/tickets/TKT-DOES-NOT-EXIST/pdf')->assertNotFound();
});

test('the web pass links save as pdf to the download and ships a clean print stylesheet', function () {
    $this
        ->get(route('public.tickets.show', ['order' => 'TKT-PDF-001']))
        ->assertOk()
        ->assertSee(route('public.tickets.pdf', ['order' => 'TKT-PDF-001']), false)
        ->assertSee('print-color-adjust: exact', false)
        ->assertSee('@page { size: A4 portrait; margin: 0; }', false);
});

test('an unsafe event accent never reaches the pdf stylesheet', function () {
    $this->ticketType->event->update(['customization' => ['accent_hex' => 'red; } body { display: none']]);

    $accent = null;
    View::composer('pdf.ticket-pass', function (Illuminate\View\View $view) use (&$accent): void {
        $accent = $view->getData()['accent'];
    });

    app(TicketPassPdf::class)->render($this->order);

    expect($accent)->toBe('#10b981');
});
