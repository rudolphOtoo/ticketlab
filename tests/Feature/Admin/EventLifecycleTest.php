<?php

declare(strict_types=1);

use App\Livewire\Admin\DataExportCentral;
use App\Livewire\Admin\EventManager;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Public\EventDirectory;
use App\Livewire\Public\EventShow;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Events\EventLifecycle;
use Livewire\Livewire;

/*
 * Events with money attached are never hard-deleted: an organisation admin archives them (hidden, not on sale, every
 * record kept) and can bring them back. Only an event that never took an order, a seat hold or a pass can be
 * deleted, and only after typing its name.
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

function sold_pass(object $test): Attendee
{
    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $test->org->id,
            'subdivision_id' => $test->zone->id,
        ]);

    return Attendee::factory()->create([
        'organization_id' => $test->org->id,
        'order_id' => $order->id,
        'subdivision_id' => $test->zone->id,
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

it('lets only organisation admins archive or delete, never zone admins', function (): void {
    $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zone->id);

    Livewire::actingAs($zoneAdmin)->test(EventManager::class)->assertForbidden();

    $this->actingAs($zoneAdmin)->get(route('admin.events'))->assertForbidden();

    expect($this->event->fresh()->isArchived())->toBeFalse();
});
