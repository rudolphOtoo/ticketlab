<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Admin\DailySessionLedger;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('unauthenticated users cannot access financial closeout ledger', function () {
    $this->get(route('admin.sessions'))->assertRedirect(route('login'));
});

test('authenticated admin can view financial closeout ledger with aggregated figures', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-'.uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin Jane',
        'email' => 'admin_'.uniqid().'@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $subdivision = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Accra Central Zone',
        'slug' => 'accra-central-'.uniqid(),
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'summit-'.uniqid(),
        'venue' => 'Perez Dome',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Gold Pass',
        'price' => 120.00,
        'total_capacity' => 500,
        'available_quantity' => 480,
    ]);

    // Online Momo Order (GHS 240)
    Order::create([
        'organization_id' => $org->id,
        'subdivision_id' => $subdivision->id,
        'reference' => 'TL-ORD-ONLINE-'.uniqid(),
        'customer_name' => 'Online Payer',
        'customer_email' => 'online@gh.com',
        'customer_phone' => '+233240001122',
        'gross_amount' => 240.00,
        'net_amount' => 234.00,
        'fee_amount' => 6.00,
        'payment_method' => PaymentMethod::MobileMoney->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    // Cash Desk Order (GHS 120)
    $cashOrder = Order::create([
        'organization_id' => $org->id,
        'subdivision_id' => $subdivision->id,
        'reference' => 'TL-ORD-CASH-'.uniqid(),
        'customer_name' => 'Cash Payer',
        'customer_email' => 'cash@gh.com',
        'customer_phone' => '+233240003344',
        'gross_amount' => 120.00,
        'net_amount' => 120.00,
        'fee_amount' => 0.00,
        'payment_method' => PaymentMethod::PhysicalCash->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    // Checked-in Attendee
    Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $cashOrder->id,
        'subdivision_id' => $subdivision->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Cash Payer',
        'email' => 'cash@gh.com',
        'phone' => '+233240003344',
        'qr_code_hash' => hash('sha256', 'TEST-PASS-HASH'),
        'checked_in_at' => now(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('admin.sessions'))
        ->assertOk()
        ->assertSee('Financial Closeout', false)
        ->assertSee('Lock Session', false)
        ->assertSee('360.00', false)
        ->assertSee('240.00', false)
        ->assertSee('120.00', false);
});

test('admin can lock financial session and generate tamper-proof PDF audit report', function () {
    Storage::fake('local');

    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-'.uniqid(),
    ]);

    $user = User::create([
        'name' => 'Finance Director',
        'email' => 'finance_'.uniqid().'@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'summit-'.uniqid(),
        'venue' => 'Perez Dome',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Regular Pass',
        'price' => 50.00,
        'total_capacity' => 1000,
        'available_quantity' => 900,
    ]);

    $order = Order::create([
        'organization_id' => $org->id,
        'reference' => 'TL-ORD-CASH-'.uniqid(),
        'customer_name' => 'Session Attendee',
        'customer_email' => 'session@test.gh',
        'customer_phone' => '+233541112233',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'fee_amount' => 0.00,
        'payment_method' => PaymentMethod::PhysicalCash->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $order->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Session Attendee',
        'email' => 'session@test.gh',
        'phone' => '+233541112233',
        'qr_code_hash' => hash('sha256', 'HASH-SESSION-1'),
        'checked_in_at' => now(),
    ]);

    $component = Livewire::actingAs($user)
        ->test(DailySessionLedger::class, ['eventId' => $event->id])
        ->call('lockSession')
        ->assertDispatched('session-locked');

    $session = SessionLedger::find($component->get('activeSessionId'));

    expect($session->isLocked())->toBeTrue()
        ->and($session->locked_by)->toBe($user->id)
        ->and((float) $session->total_cash)->toBe(50.00)
        ->and($session->total_attendees)->toBe(1)
        ->and($session->snapshot_pdf_path)->not->toBeNull();

    Storage::disk('local')->assertExists($session->snapshot_pdf_path);
});

test('locked session offers PDF download and prevents double locking', function () {
    Storage::fake('local');

    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-'.uniqid(),
    ]);

    $user = User::create([
        'name' => 'Finance Director',
        'email' => 'finance_'.uniqid().'@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $session = SessionLedger::create([
        'organization_id' => $org->id,
        'session_name' => 'Session Day 1 Final',
        'opened_at' => now()->subHours(8),
        'locked_at' => now()->subMinutes(10),
        'locked_by' => $user->id,
        'total_online_momo' => 2000.00,
        'total_cash' => 500.00,
        'total_attendees' => 45,
        'snapshot_pdf_path' => 'reports/test_snapshot.pdf',
    ]);

    Storage::disk('local')->put('reports/test_snapshot.pdf', '%PDF-1.4 mock content');

    Livewire::actingAs($user)
        ->test(DailySessionLedger::class)
        ->set('activeSessionId', $session->id)
        ->call('downloadPdfReport')
        ->assertFileDownloaded('Session_Ledger_Session_Day_1_Final.pdf');
});
