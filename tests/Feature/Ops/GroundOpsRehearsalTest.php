<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Ops\ScannerTerminal;
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

test('t2-w5: full ground operations gate rehearsal and session closeout lifecycle', function () {
    Storage::fake('local');

    // 1. Establish Organization and Regional Subdivisions
    $org = Organization::create([
        'name' => 'Perez Dome Assemblies',
        'slug' => 'perez-dome-' . uniqid(),
    ]);

    $accra = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Accra Central Zone',
        'slug' => 'accra-central-' . uniqid(),
    ]);

    $kumasi = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Kumasi South Zone',
        'slug' => 'kumasi-south-' . uniqid(),
    ]);

    $capeCoast = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Cape Coast North Zone',
        'slug' => 'cape-coast-' . uniqid(),
    ]);

    // 2. Establish Role Users
    $superadmin = User::create([
        'name' => 'General Overseer Admin',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
        'subdivision_id' => null,
    ]);

    $kumasiAdmin = User::create([
        'name' => 'Kumasi Lead Admin',
        'email' => 'kumasi_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
        'subdivision_id' => $kumasi->id,
    ]);

    $usher = User::create([
        'name' => 'Door Usher Kwame',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'usher',
        'subdivision_id' => $accra->id,
    ]);

    // 3. Establish Published Event and Ticket Tiers
    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Mega Summit 2026',
        'slug' => 'kingdom-summit-' . uniqid(),
        'venue' => 'Perez Dome Auditorium',
        'start_at' => now(),
        'end_at' => now()->addDays(3),
        'status' => 'published',
    ]);

    $vipTier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Executive Pass',
        'price' => 200.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    $regularTier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Standard Gate Pass',
        'price' => 50.00,
        'total_capacity' => 500,
        'available_quantity' => 500,
    ]);

    // 4. Pre-populate Online Paystack Orders & Attendees (3 attendees across Accra and Kumasi)
    $onlineOrder = Order::create([
        'organization_id' => $org->id,
        'reference' => 'TL-ORD-ONLINE-' . uniqid(),
        'customer_name' => 'Kofi Online Buyer',
        'customer_email' => 'kofi@online.test',
        'customer_phone' => '+233240009988',
        'gross_amount' => 450.00,
        'net_amount' => 430.00,
        'fee_amount' => 20.00,
        'payment_method' => PaymentMethod::MobileMoney->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    $onlineAttendee1 = Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $onlineOrder->id,
        'ticket_type_id' => $vipTier->id,
        'subdivision_id' => $accra->id,
        'name' => 'Kofi Mensah',
        'email' => 'kofi.m@test.com',
        'phone' => '0240001111',
        'qr_code_hash' => hash('sha256', 'PAYSTACK-TICKET-001'),
    ]);

    $onlineAttendee2 = Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $onlineOrder->id,
        'ticket_type_id' => $regularTier->id,
        'subdivision_id' => $kumasi->id,
        'name' => 'Ama Serwaa',
        'email' => 'ama.s@test.com',
        'phone' => '0240002222',
        'qr_code_hash' => hash('sha256', 'PAYSTACK-TICKET-002'),
    ]);

    // 5. Walk-In Cash Desk Transaction (Usher issues 2 Regular tickets for GHS 100.00)
    Livewire::actingAs($usher)
        ->test(CashDeskTerminal::class, ['eventId' => $event->id])
        ->call('selectTier', $regularTier->id)
        ->set('quantity', 2)
        ->set('cashTendered', '150.00')
        ->set('attendeeName', 'Kwesi Walkin')
        ->set('attendeePhone', '+233240001122')
        ->call('processCashSale')
        ->assertHasNoErrors()
        ->assertDispatched('pass-issued');

    // Mathematical Invariant: Capacity decrement & Order insertion
    $regularTier->refresh();
    expect($regularTier->available_quantity)->toBe(498);

    $cashOrder = Order::where('payment_method', PaymentMethod::PhysicalCash->value)->first();
    expect($cashOrder)
        ->not
        ->toBeNull()
        ->and((float) $cashOrder->gross_amount)
        ->toBe(100.00);

    $cashAttendees = Attendee::where('order_id', $cashOrder->id)->get();
    expect($cashAttendees)->toHaveCount(2);
    $walkinAttendee = $cashAttendees->first();

    // 6. Door QR Scanner Execution
    $scannerComponent = Livewire::actingAs($usher)->test(ScannerTerminal::class, ['eventId' => $event->id]);

    // 6a. Valid Scan
    $scannerComponent
        ->call('validatePass', $walkinAttendee->qr_code_hash)
        ->assertSet('scanState', 'success')
        ->assertSet('scanResult.status', 'VERIFIED')
        ->assertDispatched('play-success-sound');

    $walkinAttendee->refresh();
    expect($walkinAttendee->isCheckedIn())
        ->toBeTrue()
        ->and($walkinAttendee->checked_in_by)
        ->toBe($usher->id)
        ->and($walkinAttendee->checked_in_at)
        ->not->toBeNull();

    // 6b. Duplicate Scan Rejection
    $scannerComponent
        ->call('validatePass', $walkinAttendee->qr_code_hash)
        ->assertSet('scanState', 'duplicate')
        ->assertSet('scanResult.status', 'DUPLICATE')
        ->assertDispatched('play-warning-sound');

    // 6c. Invalid Hash Rejection
    $scannerComponent
        ->call('validatePass', 'tampered-qr-code-hash-999')
        ->assertSet('scanState', 'invalid')
        ->assertSet('scanResult.status', 'INVALID')
        ->assertDispatched('play-error-sound');

    // 7. Multi-Tier RBAC Guardrail Enforcement
    $this->actingAs($usher)->get(route('admin.sessions'))->assertForbidden();
    $this->actingAs($usher)->get(route('admin.attendees'))->assertForbidden();

    // Kumasi Admin can access, but only sees Kumasi scoped records
    $this->actingAs($kumasiAdmin)->get(route('admin.attendees'))->assertOk();

    Livewire::actingAs($kumasiAdmin)
        ->test(AttendeeRoster::class)
        ->assertSee('Ama Serwaa')
        ->assertDontSee('Kofi Mensah');

    // Superadmin sees all
    Livewire::actingAs($superadmin)
        ->test(AttendeeRoster::class)
        ->assertSee('Ama Serwaa')
        ->assertSee('Kofi Mensah')
        ->assertSee('Kwesi Walkin');

    // 8. 24-Hour Financial Closeout & DomPDF Generation
    $ledgerComponent = Livewire::actingAs($superadmin)
        ->test(DailySessionLedger::class, ['eventId' => $event->id])
        ->assertSee('550.00', false)
        ->assertSee('100.00', false)
        ->assertSee('450.00', false)
        ->call('lockSession')
        ->assertDispatched('session-locked');

    $savedLedger = SessionLedger::where('organization_id', $org->id)->latest()->first();
    expect($savedLedger)
        ->not->toBeNull()->and(
            $savedLedger->isLocked(),
        )->toBeTrue()->and($savedLedger->locked_by)->toBe($superadmin->id)->and((float) $savedLedger->total_cash)->toBe(
            100.00,
        )->and((float) $savedLedger->total_online_momo)->toBe(450.00)->and($savedLedger->total_attendees)->toBe(
            1,
        )->and($savedLedger->snapshot_pdf_path)
        ->not->toBeNull();

    Storage::disk('local')->assertExists($savedLedger->snapshot_pdf_path);

    // 9. Streaming Roster CSV Export
    $exportResponse = Livewire::actingAs($superadmin)->test(AttendeeRoster::class)->call('exportCsv');

    $exportResponse->assertFileDownloaded();
});
