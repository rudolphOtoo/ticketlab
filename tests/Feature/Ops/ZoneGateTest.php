<?php

declare(strict_types=1);

use App\Livewire\Ops\ScannerTerminal;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create([
        'name' => 'Ghana Methodist Student Union',
        'subdivision_label' => 'Zone',
    ]);
    $this->kumasi = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kumasi']);
    $this->capeCoast = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Cape Coast']);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id]);
    $tier = TicketType::factory()->create(['event_id' => $this->event->id]);

    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->org->id,
            'subdivision_id' => $this->capeCoast->id,
        ]);
    $this->capeCoastPass = Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'order_id' => $order->id,
        'subdivision_id' => $this->capeCoast->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Esi Cape Delegate',
    ]);

    $this->staffIn = fn(?Subdivision $zone, string $role = 'usher'): User => User::factory()->create([
        'organization_id' => $this->org->id,
        'subdivision_id' => $zone?->id,
        'role' => $role,
    ]);
});

test('a Kumasi usher scanning a Cape Coast pass gets wrong zone and nothing is checked in', function () {
    Log::spy();

    Livewire::actingAs(($this->staffIn)($this->kumasi))
        ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
        ->call('validatePass', $this->capeCoastPass->qr_code_hash)
        ->assertSet('scanState', 'wrong_zone')
        ->assertSet('scanResult.zone', 'Cape Coast')
        ->assertSee('WRONG ZONE: this pass is for Cape Coast')
        ->assertDontSee('Subdivision:')
        ->assertDispatched('play-error-sound');

    expect($this->capeCoastPass->fresh()->checked_in_at)
        ->toBeNull()
        ->and($this->capeCoastPass->fresh()->checked_in_by)
        ->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(
            static fn(string $message, array $context): bool => (
                $message === 'Wrong-zone pass refused at the gate.'
                && $context['gate_subdivision_id'] === test()->kumasi->id
                && $context['pass_subdivision_id'] === test()->capeCoast->id
                && $context['organization_id'] === test()->org->id
                && is_int($context['user_id'])
            ),
        )
        ->once();
});

test('scanning twice at the wrong gate never turns into a duplicate or a check-in', function () {
    $scanner = Livewire::actingAs(($this->staffIn)($this->kumasi, 'admin'))->test(ScannerTerminal::class, [
        'eventId' => $this->event->id,
    ]);

    $scanner->call('validatePass', $this->capeCoastPass->qr_code_hash)->assertSet('scanState', 'wrong_zone');
    $scanner->call('validatePass', $this->capeCoastPass->qr_code_hash)->assertSet('scanState', 'wrong_zone');

    expect($this->capeCoastPass->fresh()->checked_in_at)->toBeNull();
});

test('a crafted Livewire update calling the check-in action directly is refused the same way', function () {
    $usher = ($this->staffIn)($this->kumasi);
    $component = Livewire::actingAs($usher)->test(ScannerTerminal::class);

    // Bypass the camera UI entirely: hit the action with a raw payload, as a hand-built request would.
    $component->call('validatePass', '  ' . $this->capeCoastPass->qr_code_hash . "\n")->assertSet(
        'scanState',
        'wrong_zone',
    );

    expect($this->capeCoastPass->fresh()->checked_in_at)->toBeNull();
});

test('the Kumasi gate counter shows only Kumasi passes', function () {
    Livewire::actingAs(($this->staffIn)($this->kumasi))
        ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
        ->assertViewHas('totalCheckedIn', 0)
        ->assertViewHas('totalExpected', 1)
        ->assertSee('Zone: Kumasi')
        ->assertDontSee('Gate 04');
});

test('a Cape Coast usher admits the Cape Coast pass', function () {
    $usher = ($this->staffIn)($this->capeCoast);

    Livewire::actingAs($usher)
        ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
        ->call('validatePass', $this->capeCoastPass->qr_code_hash)
        ->assertSet('scanState', 'success')
        ->assertSee('Zone: Cape Coast');

    expect($this->capeCoastPass->fresh()->checked_in_by)->toBe($usher->id);
});

test('an organisation admin without a zone admits any zone and is told which zone the pass belongs to', function () {
    Livewire::actingAs(($this->staffIn)(null, 'admin'))
        ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
        ->assertSee('All Zones')
        ->call('validatePass', $this->capeCoastPass->qr_code_hash)
        ->assertSet('scanState', 'success')
        ->assertSet('scanResult.zone', 'Cape Coast')
        ->assertSee('This pass belongs to Cape Coast');

    expect($this->capeCoastPass->fresh()->checked_in_at)->not->toBeNull();
});

test('a zone gate refuses a pass that has no zone', function () {
    $this->capeCoastPass->update(['subdivision_id' => null]);

    Livewire::actingAs(($this->staffIn)($this->kumasi))
        ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
        ->call('validatePass', $this->capeCoastPass->qr_code_hash)
        ->assertSet('scanState', 'wrong_zone')
        ->assertSee('not assigned to a zone');

    expect($this->capeCoastPass->fresh()->checked_in_at)->toBeNull();
});
