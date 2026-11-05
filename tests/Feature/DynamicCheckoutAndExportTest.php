<?php

declare(strict_types=1);

use App\Livewire\Admin\DataExportCentral;
use App\Livewire\Public\EventShow;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Payment\OrderFulfillmentService;
use Livewire\Livewire;

/*
 |--------------------------------------------------------------------------
 | Registration questions: checkout modal → stored answers → export columns
 |--------------------------------------------------------------------------
 | Runs the real checkout and fulfilment path, so the export reads answers in
 | exactly the shape online buyers produce them.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'grace-hub']);
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'leaders-summit',
        'status' => 'published',
    ]);
    $this->ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    $field = fn(array $attributes): EventFormField => EventFormField::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'is_required' => false,
        ...$attributes,
    ]);

    $field([
        'field_key' => 't_shirt_size',
        'label' => 'T-Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Large'],
        'is_required' => true,
        'sort_order' => 1,
    ]);
    $field([
        'field_key' => 'bus_route',
        'label' => 'Bus Route',
        'field_type' => 'radio',
        'options' => ['Accra, Madina', 'Kumasi, Adum'],
        'sort_order' => 2,
    ]);
    $field(['field_key' => 'dietary', 'label' => 'Dietary Requirements', 'field_type' => 'text', 'sort_order' => 3]);
    $field(['field_key' => 'photo_consent', 'label' => 'Photo Consent', 'field_type' => 'checkbox', 'sort_order' => 4]);
    $field([
        'field_key' => 'retired_question',
        'label' => 'Retired Question',
        'field_type' => 'text',
        'is_required' => true,
        'is_active' => false,
        'sort_order' => 5,
    ]);
});

function checkout_with(object $test, array $answers): \Livewire\Features\SupportTesting\Testable
{
    $component = Livewire::test(EventShow::class, ['org_slug' => 'grace-hub', 'event_slug' => 'leaders-summit'])
        ->set('ticketTypeId', $test->ticket->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kofi Boateng')
        ->set('customerEmail', 'kofi@example.com')
        ->set('customerPhone', '0244998877');

    foreach ($answers as $key => $value) {
        $component->set("customFieldResponses.{$key}", $value);
    }

    return $component->call('startCheckout');
}

function paid_attendee(\Livewire\Features\SupportTesting\Testable $checkout): Attendee
{
    $order = Order::query()->where('reference', $checkout->get('orderReference'))->firstOrFail();

    return app(OrderFulfillmentService::class)
        ->fulfill($order, [
            'reference' => $order->reference,
            'status' => 'success',
            'amount' => $order->gross_pesewas,
            'currency' => 'GHS',
            'channel' => 'mobile_money',
        ])
        ->attendees->firstOrFail();
}

/**
 * @return list<array<string, string>>
 */
function exported_rows(object $test, ?int $eventId): array
{
    // A null event id is the "All events" choice in the export page's event picker.
    $response = Livewire::actingAs($test->admin)
        ->test(DataExportCentral::class)
        ->set('selectedEventId', $eventId)
        ->instance()
        ->exportAttendeeCsv();

    ob_start();
    $response->sendContent();
    $lines = array_map(static fn(string $line): array => str_getcsv(
        $line,
        ',',
        '"',
        '',
    ), array_filter(explode("\n", ltrim((string) ob_get_clean(), "\u{FEFF}"))));
    $header = array_shift($lines);

    return array_map(static fn(array $line): array => array_combine($header, $line), array_values($lines));
}

test('the checkout modal renders every active registration question with its options', function (): void {
    Livewire::test(EventShow::class, ['org_slug' => 'grace-hub', 'event_slug' => 'leaders-summit'])
        ->set('ticketTypeId', $this->ticket->id)
        ->call('openCheckout')
        ->assertSet('checkoutOpen', true)
        ->assertSeeHtml('wire:model="customFieldResponses.t_shirt_size"')
        ->assertSeeHtml('wire:model="customFieldResponses.bus_route"')
        ->assertSeeHtml('wire:model="customFieldResponses.dietary"')
        ->assertSeeHtml('wire:model="customFieldResponses.photo_consent"')
        ->assertSeeHtml('value="Accra, Madina"')
        ->assertSee('T-Shirt Size')
        ->assertDontSeeHtml('customFieldResponses.retired_question')
        ->assertDontSee('Retired Question');
});

test('answers are validated against the question type and options, commas included', function (): void {
    checkout_with($this, ['bus_route' => 'Accra, Madina'])->assertHasErrors(['customFieldResponses.t_shirt_size']);
    checkout_with($this, ['t_shirt_size' => 'Huge'])->assertHasErrors(['customFieldResponses.t_shirt_size']);
    checkout_with($this, ['t_shirt_size' => 'Large', 'bus_route' => 'Accra'])->assertHasErrors([
        'customFieldResponses.bus_route',
    ]);
    checkout_with($this, ['t_shirt_size' => 'Large', 'photo_consent' => 'maybe'])->assertHasErrors([
        'customFieldResponses.photo_consent',
    ]);
    checkout_with($this, ['t_shirt_size' => 'Large', 'bus_route' => 'Accra, Madina'])->assertHasNoErrors();
});

test('a switched-off required question never blocks checkout', function (): void {
    checkout_with($this, ['t_shirt_size' => 'Small'])
        ->assertHasNoErrors()
        ->assertSet('orderReference', fn($ref) => is_string($ref));
});

test('submitted answers are stored on the order and on the issued pass', function (): void {
    $checkout = checkout_with($this, [
        't_shirt_size' => 'Large',
        'bus_route' => 'Kumasi, Adum',
        'dietary' => '<b>Vegan</b>',
        'photo_consent' => true,
    ])->assertHasNoErrors();

    $order = Order::query()->where('reference', $checkout->get('orderReference'))->firstOrFail();

    expect($order->metadata['custom_fields']['t_shirt_size'])
        ->toBe(['label' => 'T-Shirt Size', 'value' => 'Large'])
        ->and($order->metadata['custom_fields'])
        ->not->toHaveKey('retired_question');

    $attendee = paid_attendee($checkout);

    expect($attendee->answerFor('t_shirt_size'))
        ->toBe('Large')
        ->and($attendee->answerFor('bus_route'))
        ->toBe('Kumasi, Adum')
        ->and($attendee->answerFor('dietary'))
        ->toBe('Vegan')
        ->and($attendee->answerFor('photo_consent'))
        ->toBeTrue();
});

test('the attendee csv unrolls each question into its own column with the plain answer', function (): void {
    paid_attendee(checkout_with($this, [
        't_shirt_size' => 'Large',
        'bus_route' => 'Accra, Madina',
        'photo_consent' => true,
    ]));

    [$row] = exported_rows($this, $this->event->id);

    expect($row)
        ->toHaveKeys([
            'Custom Field: T-Shirt Size',
            'Custom Field: Bus Route',
            'Custom Field: Dietary Requirements',
            'Custom Field: Photo Consent',
            'Custom Field: Retired Question',
        ])
        ->and($row['Custom Field: T-Shirt Size'])
        ->toBe('Large')
        ->and($row['Custom Field: Bus Route'])
        ->toBe('Accra, Madina')
        ->and($row['Custom Field: Dietary Requirements'])
        ->toBe('N/A')
        ->and($row['Custom Field: Photo Consent'])
        ->toBe('Yes')
        ->and($row['Name'])
        ->toBe('Kofi Boateng');
});

test('an all-events export unrolls questions from every event', function (): void {
    paid_attendee(checkout_with($this, ['t_shirt_size' => 'Small']));

    $other = Event::factory()->create(['organization_id' => $this->org->id]);
    EventFormField::create([
        'organization_id' => $this->org->id,
        'event_id' => $other->id,
        'field_key' => 'shirt',
        'label' => 'T-Shirt Size',
        'field_type' => 'text',
    ]);
    EventFormField::create([
        'organization_id' => $this->org->id,
        'event_id' => $other->id,
        'field_key' => 'church',
        'label' => 'Home Church',
        'field_type' => 'text',
    ]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => TicketType::factory()->create(['event_id' => $other->id])->id,
        'name' => 'Ama Mensah',
        'custom_fields' => ['shirt' => 'XL', 'church' => 'Grace Chapel'],
    ]);

    $rows = collect(exported_rows($this, null))->keyBy('Name');

    expect($rows['Kofi Boateng']['Custom Field: T-Shirt Size'])
        ->toBe('Small')
        ->and($rows['Kofi Boateng']['Custom Field: Home Church'])
        ->toBe('')
        ->and($rows['Ama Mensah']['Custom Field: T-Shirt Size'])
        ->toBe('XL')
        ->and($rows['Ama Mensah']['Custom Field: Home Church'])
        ->toBe('Grace Chapel');
});

test('a question labelled like a standard column never overwrites it', function (): void {
    EventFormField::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'field_key' => 'work_email',
        'label' => 'Email',
        'field_type' => 'text',
        'sort_order' => 6,
    ]);

    paid_attendee(checkout_with($this, ['t_shirt_size' => 'Large', 'work_email' => 'kofi@work.example']));

    [$row] = exported_rows($this, $this->event->id);

    expect($row['Email'])->toBe('kofi@example.com')->and($row['Custom Field: Email'])->toBe('kofi@work.example');
});
