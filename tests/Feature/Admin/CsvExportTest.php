<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\DataExportCentral;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Support\CsvWriter;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'slug' => 'kingdom-summit']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id, 'name' => 'Delegate']);
    $this->zone = Subdivision::factory()->create(['organization_id' => $this->org->id]);
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);

    EventFormField::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'label' => 'Dietary Needs',
        'field_key' => 'dietary',
        'input_type' => 'text',
        'sort_order' => 1,
    ]);
});

function export_attendee(object $test, array $attributes = []): Attendee
{
    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $test->org->id,
            'subdivision_id' => $test->zone->id,
        ]);

    return Attendee::factory()->create([
        'order_id' => $order->id,
        'organization_id' => $test->org->id,
        'subdivision_id' => $test->zone->id,
        'ticket_type_id' => $test->tier->id,
        ...$attributes,
    ]);
}

/**
 * @return list<list<string>>
 */
function csv_rows(StreamedResponse $response): array
{
    ob_start();
    $response->sendContent();
    $body = (string) ob_get_clean();

    expect($body)->toStartWith("\u{FEFF}");

    $lines = explode("\n", str_replace("\r\n", "\n", trim(substr($body, 3))));

    return array_map(static fn(string $line): array => str_getcsv($line, ',', '"', ''), $lines);
}

test('the attendee export downloads as a utf-8 csv file with a csv filename', function (): void {
    export_attendee($this);

    $response = Livewire::actingAs($this->admin)
        ->test(DataExportCentral::class, ['eventId' => $this->event->id])
        ->instance()
        ->exportAttendeeCsv();

    expect($response->getStatusCode())
        ->toBe(200)
        ->and($response->headers->get('content-type'))
        ->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('content-disposition'))
        ->toStartWith('attachment; filename=attendees_kingdom-summit_')
        ->toEndWith('.csv');
});

test('the attendee export writes a header row, one row per attendee and questionnaire columns', function (): void {
    export_attendee($this, ['name' => 'Akosua Boateng, Jr.', 'custom_fields' => ['dietary' => 'Vegetarian']]);

    $rows = csv_rows(
        Livewire::actingAs($this->admin)
            ->test(DataExportCentral::class, ['eventId' => $this->event->id])
            ->instance()
            ->exportAttendeeCsv(),
    );

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toContain('Attendee ID', 'Name', 'Email', 'Custom Field: Dietary Needs');

    $row = array_combine($rows[0], $rows[1]);

    expect($row['Name'])->toBe('Akosua Boateng, Jr.')->and($row['Custom Field: Dietary Needs'])->toBe('Vegetarian');
});

test('the attendee export parses modern nested questionnaire custom fields without duplicating labels', function (): void {
    export_attendee($this, [
        'name' => 'Kofi Annan',
        'custom_fields' => [
            'dietary' => [
                'label' => 'Dietary Needs',
                'value' => 'Gluten-Free',
            ],
        ],
    ]);

    $rows = csv_rows(
        Livewire::actingAs($this->admin)
            ->test(DataExportCentral::class, ['eventId' => $this->event->id])
            ->instance()
            ->exportAttendeeCsv(),
    );

    expect($rows)->toHaveCount(2);
    $row = array_combine($rows[0], $rows[1]);

    expect($row['Name'])->toBe('Kofi Annan')->and($row['Custom Field: Dietary Needs'])->toBe('Gluten-Free');
});

test('csv exports neutralise spreadsheet formulas typed by the public', function (): void {
    export_attendee($this, [
        'name' => '=HYPERLINK("http://evil.test","Click")',
        'custom_fields' => ['dietary' => '@SUM(A1:A9)'],
    ]);

    $rows = csv_rows(
        Livewire::actingAs($this->admin)
            ->test(DataExportCentral::class, ['eventId' => $this->event->id])
            ->instance()
            ->exportAttendeeCsv(),
    );
    $row = array_combine($rows[0], $rows[1]);

    expect($row['Name'])
        ->toBe('\'=HYPERLINK("http://evil.test","Click")')
        ->and($row['Custom Field: Dietary Needs'])
        ->toBe("'@SUM(A1:A9)");

    $roster = csv_rows(Livewire::actingAs($this->admin)->test(AttendeeRoster::class)->instance()->exportCsv());

    expect(array_combine($roster[0], $roster[1])['Name'])->toStartWith("'=HYPERLINK");
});

test('csv exports stream every row across 500-row chunks', function (): void {
    $order = Order::factory()
        ->paid()
        ->create(['organization_id' => $this->org->id, 'subdivision_id' => $this->zone->id]);

    Attendee::factory()
        ->count(501)
        ->create([
            'order_id' => $order->id,
            'organization_id' => $this->org->id,
            'subdivision_id' => $this->zone->id,
            'ticket_type_id' => $this->tier->id,
        ]);

    $rows = csv_rows(
        Livewire::actingAs($this->admin)
            ->test(DataExportCentral::class, ['eventId' => $this->event->id])
            ->instance()
            ->exportAttendeeCsv(),
    );

    expect($rows)->toHaveCount(502);
});

test('the csv writer keeps numbers and plain text unchanged', function (): void {
    expect(CsvWriter::safe('Kofi Mensah'))
        ->toBe('Kofi Mensah')
        ->and(CsvWriter::safe(42))
        ->toBe('42')
        ->and(CsvWriter::safe(-3.5))
        ->toBe('-3.50')
        ->and(CsvWriter::safe(null))
        ->toBe('')
        ->and(CsvWriter::safe('+233244000000'))
        ->toBe("'+233244000000");
});
