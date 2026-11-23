<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Jobs\SendEventReminders;
use App\Livewire\Admin\DataExportCentral;
use App\Livewire\Admin\EventManager;
use App\Livewire\Admin\EventRefunds;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Ops\ScannerTerminal;
use App\Livewire\Public\EventDirectory;
use App\Livewire\Public\EventShow;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Events\EventLifecycle;
use App\Services\Mail\MailQuota;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Events with money attached are never hard-deleted: an organisation admin archives them (hidden, not on sale, every
 * record kept) and can bring them back, or cancels one that won't take place (sales stop, the page says so, the gate
 * refuses its passes, buyers may be emailed, and refunds are made by hand from the refund list). Only an event that
 * never took an order, a seat hold or a pass can be deleted, and only after typing its name.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'gmsu']);
    $this->zone = payable_zone($this->org, 'Zone A');
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'title' => 'Convention 2027',
        'slug' => 'convention',
        'status' => 'published',
        'is_public' => true,
    ]);
    $this->tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Standard',
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
});

/**
 * @param array<string, mixed> $order
 */
function sold_pass(object $test, array $order = []): Attendee
{
    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $test->org->id,
            'subdivision_id' => $test->zone->id,
            ...$order,
        ]);

    return Attendee::factory()->create([
        'organization_id' => $test->org->id,
        'order_id' => $order->id,
        'subdivision_id' => $order->subdivision_id,
        'ticket_type_id' => $test->tier->id,
    ]);
}

describe('archiving', function (): void {
    it('hides the event from the public site and the directory, and stops online sales', function (): void {
        $pass = sold_pass($this);
        $page = Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention']);

        Livewire::actingAs($this->admin)->test(EventManager::class)->call('archiveEvent', $this->event->id);

        expect($this->event->fresh()->isArchived())->toBeTrue();

        $this->get('/gmsu/convention')->assertNotFound();
        $this->get('/gmsu')->assertOk()->assertDontSee('Convention 2027');
        Livewire::test(EventDirectory::class)->assertDontSee('Convention 2027');

        // A buyer who opened the page before it was archived can't pay any more.
        $page
            ->set('ticketTypeId', $this->tier->id)
            ->set('quantity', 1)
            ->set('customerName', 'Ama Owusu')
            ->set('customerEmail', 'ama@example.com')
            ->set('customerPhone', '0244123456')
            ->set('subdivisionId', $this->zone->id)
            ->checkoutAsShown()
            ->assertHasErrors(['payment' => 'This event is archived, so sales are closed.']);

        expect(Reservation::query()->count())->toBe(0);

        // Existing passes keep working.
        $this->get(route('public.tickets.show', ['order' => $pass->order->reference]))->assertOk();
        $this->get(route('public.tickets.pdf', ['order' => $pass->order->reference]))->assertOk();
    });

    it('stops cash desk sales', function (): void {
        $this->event->forceFill(['archived_at' => now()])->save();

        Livewire::actingAs($this->admin)
            ->test(CashDeskTerminal::class, ['eventId' => $this->event->id])
            ->assertSeeHtml('data-sales-closed')
            ->call('selectTier', $this->tier->id)
            ->set('quantity', 1)
            ->set('cashTendered', '50.00')
            ->set('saleZoneId', $this->zone->id)
            ->call('processCashSale')
            ->assertHasErrors(['tier' => 'This event is archived, so sales are closed.']);

        expect(Attendee::query()->count())->toBe(0);
    });

    it('keeps the event in admin reports and exports', function (): void {
        sold_pass($this)->update(['name' => 'Kofi Mensah']);
        $this->event->forceFill(['archived_at' => now()])->save();

        $response = Livewire::actingAs($this->admin)
            ->test(DataExportCentral::class, ['eventId' => $this->event->id])
            ->instance()
            ->exportAttendeeCsv();

        ob_start();
        $response->sendContent();

        expect(ob_get_clean())->toContain('Kofi Mensah');

        $this
            ->actingAs($this->admin)
            ->get(route('admin.events'))
            ->assertOk()
            ->assertSee('Convention 2027')
            ->assertSee('Archived')
            ->assertSee('Unarchive');
    });

    it('can be undone, bringing the event back as it was, and is audited', function (): void {
        Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->call('archiveEvent', $this->event->id)
            ->call('unarchiveEvent', $this->event->id)
            ->assertSet('flashMessage', "'Convention 2027' is back as published.");

        expect($this->event->fresh()->isArchived())
            ->toBeFalse()
            ->and(AuditLog::query()->where('subject_type', 'event')->pluck('action')->all())
            ->toBe(['event.archived', 'event.unarchived'])
            ->and(AuditLog::query()->first()->user_id)
            ->toBe($this->admin->id);

        $this->get('/gmsu/convention')->assertOk();
    });
});

describe('cancelling', function (): void {
    it('stops sales at once and shows the organiser\'s message instead of the checkout', function (): void {
        $page = Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention']);

        Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->call('confirmCancel', $this->event->id)
            ->assertSeeHtml('data-cancel-event')
            ->set('cancellationMessage', 'The venue flooded. Every buyer will be refunded within 14 days.')
            ->set('emailBuyers', false)
            ->call('cancelEvent', $this->event->id)
            ->assertHasNoErrors()
            ->assertSet('cancellingEventId', null)
            ->assertSee('Refund list');

        $this
            ->get('/gmsu/convention')
            ->assertOk()
            ->assertSee('This event has been cancelled')
            ->assertSee('The venue flooded. Every buyer will be refunded within 14 days.')
            ->assertDontSee('Choose your ticket')
            ->assertDontSee('data-order-summary', false);
        $this->get('/gmsu')->assertOk()->assertDontSee('Convention 2027');
        Livewire::test(EventDirectory::class)->assertDontSee('Convention 2027');

        $page
            ->set('ticketTypeId', $this->tier->id)
            ->set('quantity', 1)
            ->set('customerName', 'Ama Owusu')
            ->set('customerEmail', 'ama@example.com')
            ->set('customerPhone', '0244123456')
            ->set('subdivisionId', $this->zone->id)
            ->checkoutAsShown()
            ->assertHasErrors(['payment' => 'This event has been cancelled, so sales are closed.']);

        Livewire::actingAs($this->admin)
            ->test(CashDeskTerminal::class, ['eventId' => $this->event->id])
            ->call('selectTier', $this->tier->id)
            ->set('cashTendered', '50.00')
            ->set('saleZoneId', $this->zone->id)
            ->call('processCashSale')
            ->assertHasErrors(['tier' => 'This event has been cancelled, so sales are closed.']);

        expect(Reservation::query()->count())->toBe(0)->and(Attendee::query()->count())->toBe(0);
    });

    it('needs a message, and is audited with it', function (): void {
        Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->call('confirmCancel', $this->event->id)
            ->call('cancelEvent', $this->event->id)
            ->assertHasErrors(['cancellationMessage' => 'required']);

        expect($this->event->fresh()->isCancelled())->toBeFalse();

        app(EventLifecycle::class)->cancel($this->event, $this->admin, 'Postponed indefinitely.');

        expect(AuditLog::query()->where('action', 'event.cancelled')->firstOrFail())
            ->user_id->toBe($this->admin->id)
            ->details->toMatchArray(['message' => 'Postponed indefinitely.'])->and(
                AuditLog::query()->where('action', 'event.cancellation_emailed')->exists(),
            )->toBeFalse();
    });

    it('refuses its passes at the gate, while the ticket page says why', function (): void {
        $pass = sold_pass($this);
        app(EventLifecycle::class)->cancel($this->event, $this->admin, 'The venue flooded.');
        $usher = User::factory()->create()->assignMembership('usher', $this->org->id);

        Livewire::actingAs($usher)
            ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
            ->call('validatePass', $pass->qr_code_hash)
            ->assertSet('scanState', 'invalid')
            ->assertSet('scanResult.status', 'CANCELLED')
            ->assertDispatched('play-error-sound');

        expect($pass->fresh()->checked_in_at)->toBeNull();

        $this
            ->get(route('public.tickets.show', ['order' => $pass->order->reference]))
            ->assertOk()
            ->assertSee('This event has been cancelled')
            ->assertSee('The venue flooded.');
    });

    it('emails every buyer once, within the daily email cap, spreading a large list over the next days', function (): void {
        Bus::fake();
        sold_pass($this, ['customer_email' => 'ama@example.com', 'customer_name' => 'Ama']);
        sold_pass($this, ['customer_email' => 'AMA@example.com', 'customer_name' => 'Ama again']);
        sold_pass($this, ['customer_email' => 'kofi@example.com', 'customer_name' => 'Kofi']);
        sold_pass($this, [
            'customer_email' => 'walkin_ab12@cashdesk.local',
            'payment_method' => PaymentMethod::PhysicalCash,
        ]);
        // Broadcasts may use half of the 300 a day; 149 are already used, so one goes today and one tomorrow.
        DB::table('mail_quota_days')->insert([
            'day' => app(MailQuota::class)->today(),
            'sent' => 149,
            'broadcast' => 149,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->call('confirmCancel', $this->event->id)
            ->assertSee('Email this message to 2 buyers.')
            ->set('cancellationMessage', 'The venue flooded.')
            ->call('cancelEvent', $this->event->id)
            ->assertSee('1 are emailed today, then up to 150 a day over the next 1 day');

        expect(AuditLog::query()->where('action', 'event.cancellation_emailed')->sole()->details)->toBe([
            'title' => 'Convention 2027',
            'buyers' => 2,
        ]);

        $broadcast = Broadcast::query()->sole();
        expect($broadcast)
            ->subject->toBe('Cancelled: Convention 2027')
            ->recipients_count->toBe(2)->and($broadcast->body)->toContain('has been cancelled.')->toContain(
                'The venue flooded.',
            );

        Bus::assertBatched(static function (PendingBatch $batch): bool {
            $jobs = $batch->jobs->values();

            return (
                $jobs->count() === 2
                && $jobs->every(
                    static fn($job): bool => $job instanceof SendEventReminders && $job->queue === 'broadcasts',
                )
                && $jobs->pluck('email')->all() === ['ama@example.com', 'kofi@example.com']
                && $jobs[1]->delay >= app(MailQuota::class)->nextReset()
            );
        });
    });

    it('lists paid orders and amounts per zone for manual refunds', function (): void {
        $zoneB = payable_zone($this->org, 'Zone B');
        sold_pass($this, ['reference' => 'TL-A1', 'gross_amount' => 50]);
        sold_pass($this, ['reference' => 'TL-A2', 'gross_amount' => 101.5]);
        sold_pass($this, ['reference' => 'TL-B1', 'gross_amount' => 50, 'subdivision_id' => $zoneB->id]);
        // Not refundable: unpaid, or for another event.
        Order::factory()->create(['organization_id' => $this->org->id, 'reference' => 'TL-PENDING']);
        $other = TicketType::factory()->create([
            'event_id' => Event::factory()->create(['organization_id' => $this->org->id])->id,
        ]);
        Attendee::factory()->create([
            'organization_id' => $this->org->id,
            'order_id' => Order::factory()
                ->paid()
                ->create(['organization_id' => $this->org->id, 'reference' => 'TL-OTHER'])
                ->id,
            'ticket_type_id' => $other->id,
        ]);
        app(EventLifecycle::class)->cancel($this->event, $this->admin, 'The venue flooded.');

        $refunds = Livewire::actingAs($this->admin)
            ->test(EventRefunds::class, ['event' => $this->event->id])
            ->assertSee('3 paid orders, GHS 201.50 in total.')
            ->assertSeeHtml('data-refund-row="TL-A1"')
            ->assertSeeHtml('data-refund-row="TL-B1"')
            ->assertDontSee('TL-PENDING')
            ->assertDontSee('TL-OTHER')
            ->assertSeeInOrder(['Zone A · 2 orders', 'GHS 151.50', 'Zone B · 1 order', 'GHS 50.00']);

        $response = $refunds->instance()->exportCsv(app(EventLifecycle::class));
        ob_start();
        $response->sendContent();

        expect(ob_get_clean())->toContain('TL-A2')->toContain('101.50')->toContain('Mobile money');
    });

    it('keeps the refund list to the organisation\'s own admins', function (): void {
        $foreignAdmin = User::factory()->create()->assignMembership('admin', Organization::factory()->create()->id);
        $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zone->id);
        $url = route('admin.events.refunds', ['event' => $this->event->id]);

        $this->actingAs($foreignAdmin)->get($url)->assertNotFound();
        $this->actingAs($zoneAdmin)->get($url)->assertForbidden();
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Refunds are not automatic.');
    });
});

describe('deleting', function (): void {
    it('refuses an event with sold passes, pointing to archive and cancel', function (): void {
        sold_pass($this);

        Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->call('confirmDelete', $this->event->id)
            ->assertSet('deletingEventId', null)
            ->assertSet(
                'flashMessage',
                "This event has sold passes, so it can't be deleted. Archive it to hide it, or cancel it if it won't take place.",
            )
            // Even a hand-built request with the right name is refused.
            ->set('deleteConfirmation', 'Convention 2027')
            ->call('deleteEvent', $this->event->id);

        expect($this->event->fresh())->not->toBeNull();
    });

    it('refuses an event with an order or a seat hold but no passes yet', function (): void {
        Reservation::factory()
            ->expired()
            ->create([
                'organization_id' => $this->org->id,
                'ticket_type_id' => $this->tier->id,
                'subdivision_id' => $this->zone->id,
            ]);

        expect(app(EventLifecycle::class)->deleteBlocker($this->event))->toBe(EventLifecycle::HAS_ORDERS);

        Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->set('deleteConfirmation', 'Convention 2027')
            ->call('deleteEvent', $this->event->id);

        expect($this->event->fresh())->not->toBeNull();
    });

    it('deletes an empty event only once its name is typed exactly', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test(EventManager::class)
            ->call('confirmDelete', $this->event->id)
            ->assertSet('deletingEventId', $this->event->id)
            ->assertSeeHtml('data-delete-event')
            ->set('deleteConfirmation', 'convention 2027')
            ->call('deleteEvent', $this->event->id)
            ->assertHasErrors(['deleteConfirmation']);

        expect($this->event->fresh())->not->toBeNull();

        $component
            ->set('deleteConfirmation', 'Convention 2027')
            ->call('deleteEvent', $this->event->id)
            ->assertHasNoErrors()
            ->assertSet('deletingEventId', null)
            ->assertSet('flashMessage', "'Convention 2027' was deleted.");

        expect($this->event->fresh())
            ->toBeNull()
            ->and(TicketType::query()->whereKey($this->tier->id)->exists())
            ->toBeFalse()
            ->and(AuditLog::query()->where('action', 'event.deleted')->value('subject_id'))
            ->toBe($this->event->id);
    });
});

it('lets only organisation admins archive, cancel or delete, never zone admins', function (): void {
    $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zone->id);

    Livewire::actingAs($zoneAdmin)->test(EventManager::class)->assertForbidden();

    $this->actingAs($zoneAdmin)->get(route('admin.events'))->assertForbidden();

    expect($this->event->fresh())->isArchived()->toBeFalse()->isCancelled()->toBeFalse();
});
