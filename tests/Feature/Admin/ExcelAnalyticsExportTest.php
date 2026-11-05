<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Exports\EventAnalyticsExport;
use App\Livewire\Admin\DataExportCentral;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
 |--------------------------------------------------------------------------
 | Excel analytics report: styled roster + dashboard with native charts
 |--------------------------------------------------------------------------
 | Two zones. Accra sells two passes for GHS 300 (one checked in), Kumasi one
 | pass for GHS 80. The zone-bound Accra admin must never see Kumasi figures.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'slug' => 'kingdom-summit']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id, 'name' => 'Delegate', 'price' => 150]);
    $this->accra = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Accra']);
    $this->kumasi = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kumasi']);
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->accraAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->accra->id);

    EventFormField::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'label' => 'Dietary Needs',
        'field_key' => 'dietary',
        'input_type' => 'text',
    ]);

    $accraOrder = analytics_order($this, $this->accra, 300);
    analytics_pass($this, $accraOrder, [
        'name' => 'Ama Accra',
        'checked_in_at' => now(),
        'custom_fields' => ['dietary' => [
            'label' => 'Dietary Needs',
            'value' => 'Vegan',
        ]],
    ]);
    analytics_pass($this, $accraOrder, ['name' => 'Kojo Accra']);
    analytics_pass($this, analytics_order($this, $this->kumasi, 80), ['name' => 'Yaw Kumasi']);

    // Unpaid orders never count towards revenue.
    analytics_order($this, $this->accra, 999, OrderStatus::Pending);
});

function analytics_order(object $test, Subdivision $zone, float $amount, OrderStatus $status = OrderStatus::Paid): Order
{
    return Order::factory()->create([
        'organization_id' => $test->org->id,
        'subdivision_id' => $zone->id,
        'gross_amount' => $amount,
        'net_amount' => $amount,
        'payment_status' => $status,
    ]);
}

function analytics_pass(object $test, Order $order, array $attributes): Attendee
{
    return Attendee::factory()->create([
        'order_id' => $order->id,
        'organization_id' => $test->org->id,
        'subdivision_id' => $order->subdivision_id,
        'ticket_type_id' => $test->tier->id,
        'checked_in_at' => null,
        ...$attributes,
    ]);
}

function analytics_report(User $user, ?int $eventId): BinaryFileResponse
{
    return Livewire::actingAs($user)
        ->test(DataExportCentral::class)
        ->set('selectedEventId', $eventId)
        ->instance()
        ->exportAnalyticsXlsx();
}

function analytics_workbook(BinaryFileResponse $response): Spreadsheet
{
    $reader = IOFactory::createReader('Xlsx');
    $reader->setIncludeCharts(true);

    return $reader->load($response->getFile()->getPathname());
}

/**
 * @return array<string, string>
 */
function analytics_zip_parts(BinaryFileResponse $response): array
{
    $zip = new ZipArchive();
    $zip->open($response->getFile()->getPathname());
    $parts = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $parts[$name] = (string) $zip->getFromIndex($i);
    }

    $zip->close();

    return $parts;
}

/**
 * @return array<string, mixed>
 */
function dashboard_metrics(Spreadsheet $book): array
{
    $sheet = $book->getSheetByNameOrThrow('Dashboard');
    $metrics = [];

    foreach (range(5, 9) as $row) {
        $metrics[(string) $sheet->getCell("A{$row}")->getValue()] = $sheet->getCell("B{$row}")->getValue();
    }

    return $metrics;
}

test('the analytics report downloads as an xlsx workbook', function (): void {
    $response = analytics_report($this->admin, $this->event->id);

    expect($response->getStatusCode())
        ->toBe(200)
        ->and($response->headers->get('content-type'))
        ->toBe(EventAnalyticsExport::CONTENT_TYPE)
        ->and($response->headers->get('content-disposition'))
        ->toStartWith('attachment; filename=event_analytics_kingdom-summit_')
        ->toEndWith('.xlsx')
        ->and(file_get_contents($response->getFile()->getPathname(), length: 2))
        ->toBe('PK');

    expect(analytics_workbook($response)->getSheetNames())->toBe(['Attendee Roster', 'Dashboard']);
});

test('the roster sheet is a styled, filterable table with every attendee and answer', function (): void {
    $sheet = analytics_workbook(analytics_report($this->admin, $this->event->id))
        ->getSheetByNameOrThrow('Attendee Roster');
    $rows = $sheet->toArray(null, false, false);
    $header = $rows[0];
    $byName = collect(array_slice($rows, 1))
        ->map(static fn(array $row): array => array_combine($header, $row))
        ->keyBy('Name');

    expect($header)
        ->toContain('Name', 'Ticket Tier', 'Zone', 'Price Paid (GHS)', 'Status', 'Custom Field: Dietary Needs')
        ->and($byName)
        ->toHaveCount(3)
        ->and($byName['Ama Accra']['Zone'])
        ->toBe('Accra')
        ->and($byName['Ama Accra']['Status'])
        ->toBe('Checked-In')
        ->and($byName['Ama Accra']['Custom Field: Dietary Needs'])
        ->toBe('Vegan')
        ->and($byName['Ama Accra']['Price Paid (GHS)'])
        ->toEqual(150)
        ->and($sheet->getFreezePane())
        ->toBe('A2')
        ->and($sheet->getAutoFilter()->getRange())
        ->toStartWith('A1:')
        ->and($sheet->getStyle('A1')->getFont()->getBold())
        ->toBeTrue()
        ->and($sheet->getStyle('A1')->getFill()->getStartColor()->getRGB())
        ->toBe('18181B')
        ->and($sheet->getStyle('G2')->getNumberFormat()->getFormatCode())
        ->toBe('#,##0.00')
        ->and($sheet->getConditionalStylesCollection())
        ->not->toBeEmpty();
});

test('the dashboard reports organisation-wide figures with a native bar and doughnut chart', function (): void {
    $response = analytics_report($this->admin, $this->event->id);
    $book = analytics_workbook($response);
    $sheet = $book->getSheetByNameOrThrow('Dashboard');

    expect(dashboard_metrics($book))->toEqual([
        'Total revenue (GHS)' => 380,
        'Passes issued' => 3,
        'Checked in' => 1,
        'Pending' => 2,
        'Check-in turnout' => 0.3333,
    ]);

    expect([$sheet->getCell('A12')->getValue(), $sheet->getCell('C12')->getValue()])->toEqual(['Accra', 300])->and([
        $sheet->getCell('A13')->getValue(),
        $sheet->getCell('C13')->getValue(),
    ])->toEqual(['Kumasi', 80]);

    $charts = $sheet->getChartCollection();

    expect($charts)
        ->toHaveCount(2)
        ->and($charts[0]->getPlotArea()?->getPlotGroupByIndex(0)->getPlotType())
        ->toBe(DataSeries::TYPE_BARCHART)
        ->and($charts[1]->getPlotArea()?->getPlotGroupByIndex(0)->getPlotType())
        ->toBe(DataSeries::TYPE_DOUGHNUTCHART);

    $chartXml = implode('', array_filter(
        analytics_zip_parts($response),
        static fn(string $name): bool => str_starts_with($name, 'xl/charts/chart'),
        ARRAY_FILTER_USE_KEY,
    ));

    expect($chartXml)
        ->toContain("'Dashboard'!\$A\$12:\$A\$13")
        ->toContain("'Dashboard'!\$C\$12:\$C\$13")
        ->toContain("'Dashboard'!\$B\$16:\$B\$17")
        ->toContain('Revenue by zone (GHS)')
        ->toContain('Check-in status');
});

test('a zone admin\'s report and charts only describe their own zone', function (): void {
    $response = analytics_report($this->accraAdmin, $this->event->id);
    $book = analytics_workbook($response);
    $roster = $book->getSheetByNameOrThrow('Attendee Roster')->toArray(null, false, false);
    $dashboard = $book->getSheetByNameOrThrow('Dashboard');

    expect(array_column(array_slice($roster, 1), 1))
        ->toEqualCanonicalizing(['Ama Accra', 'Kojo Accra'])
        ->and(dashboard_metrics($book))
        ->toMatchArray(['Total revenue (GHS)' => 300, 'Passes issued' => 2])
        ->and($dashboard->getCell('A12')->getValue())
        ->toBe('Accra')
        ->and($dashboard->getCell('A13')->getValue())
        ->toBeNull()
        ->and((string) $dashboard->getCell('A2')->getValue())
        ->toContain('Accra');

    expect(implode('', analytics_zip_parts($response)))->not->toContain('Kumasi')->not->toContain('Yaw Kumasi');
});

test('text typed by the public is stored as text in the workbook, never as a formula', function (): void {
    analytics_pass($this, analytics_order($this, $this->accra, 10), [
        'name' => '=HYPERLINK("http://evil.test","Click")',
        'custom_fields' => ['dietary' => '@SUM(A1:A9)'],
    ]);
    $this->kumasi->update(['name' => '=1+1']);

    $response = analytics_report($this->admin, $this->event->id);
    $parts = analytics_zip_parts($response);
    $sheets = implode('', array_filter(
        $parts,
        static fn(string $name): bool => str_starts_with($name, 'xl/worksheets/sheet'),
        ARRAY_FILTER_USE_KEY,
    ));

    expect($sheets)
        ->not
        ->toContain('<f>')
        // Zones are listed by name, and "=" sorts before letters.
        ->and(analytics_workbook($response)->getSheetByNameOrThrow('Dashboard')->getCell('A12')->getValue())
        ->toBe('=1+1');
});

test('an all-events report covers every event in the organisation', function (): void {
    $other = Event::factory()->create(['organization_id' => $this->org->id]);
    $otherTier = TicketType::factory()->create(['event_id' => $other->id]);
    $order = analytics_order($this, $this->kumasi, 50);
    Attendee::factory()->create([
        'order_id' => $order->id,
        'organization_id' => $this->org->id,
        'subdivision_id' => $this->kumasi->id,
        'ticket_type_id' => $otherTier->id,
        'name' => 'Esi Other Event',
    ]);

    $book = analytics_workbook(analytics_report($this->admin, null));

    expect(dashboard_metrics($book))
        ->toMatchArray(['Total revenue (GHS)' => 430, 'Passes issued' => 4])
        ->and((string) $book->getSheetByNameOrThrow('Dashboard')->getCell('A2')->getValue())
        ->toStartWith('All events');
});
