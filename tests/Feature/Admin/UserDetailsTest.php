<?php

declare(strict_types=1);

use App\Livewire\Admin\UserDetails;
use App\Livewire\Admin\UserManagement;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 |--------------------------------------------------------------------------
 | Admin user details inspection: /admin/users/{user}
 |--------------------------------------------------------------------------
 | One organisation with two zones, plus a second organisation. Admins may
 | only reach accounts inside their organisation, and inside their zone when
 | zone-bound; everything else is a 403.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->zoneA = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Zone Alpha']);
    $this->zoneB = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Zone Bravo']);

    $this->orgAdmin = User::factory()->create(['name' => 'Olivia Owner'])->assignMembership('admin', $this->org->id);
    $this->zoneAdmin = User::factory()
        ->create(['name' => 'Zara Zone'])
        ->assignMembership('admin', $this->org->id, $this->zoneA->id);
    $this->staffA = User::factory()
        ->create(['name' => 'Sam Alpha', 'email' => 'sam.alpha@example.com'])
        ->assignMembership('staff', $this->org->id, $this->zoneA->id);
    $this->staffB = User::factory()
        ->create(['name' => 'Ben Bravo'])
        ->assignMembership('staff', $this->org->id, $this->zoneB->id);

    $this->foreignOrg = Organization::factory()->create();
    $this->foreignUser = User::factory()->create()->assignMembership('admin', $this->foreignOrg->id);
});

/*
 |--------------------------------------------------------------------------
 | Who can inspect whom
 |--------------------------------------------------------------------------
 */

test('organization admins can inspect any member of their organization', function (): void {
    foreach ([$this->staffA, $this->staffB, $this->zoneAdmin] as $member) {
        $this
            ->actingAs($this->orgAdmin)
            ->get(route('admin.users.show', $member))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($member->email);
    }
});

test('the details page shows identity, access and verification details', function (): void {
    $this
        ->actingAs($this->orgAdmin)
        ->get(route('admin.users.show', $this->staffA))
        ->assertOk()
        ->assertSee('sam.alpha@example.com')
        ->assertSee('Verified')
        ->assertSee('Zone Alpha')
        ->assertSee($this->org->name)
        ->assertSee((string) $this->staffA->created_at?->format('M j, Y'));
});

test('zone-bound admins can inspect members of their own zone only', function (): void {
    $this->actingAs($this->zoneAdmin)->get(route('admin.users.show', $this->staffA))->assertOk();

    $this->actingAs($this->zoneAdmin)->get(route('admin.users.show', $this->staffB))->assertForbidden();
    $this->actingAs($this->zoneAdmin)->get(route('admin.users.show', $this->orgAdmin))->assertForbidden();
});

test('cross-tenant inspection is forbidden', function (): void {
    $this->actingAs($this->orgAdmin)->get(route('admin.users.show', $this->foreignUser))->assertForbidden();
    $this->actingAs($this->foreignUser)->get(route('admin.users.show', $this->staffA))->assertForbidden();
});

test('tenant admins cannot inspect platform administrators or attendees outside the organization', function (): void {
    $platformAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $attendee = User::factory()->create()->assignMembership('attendee', null);

    $this->actingAs($this->orgAdmin)->get(route('admin.users.show', $platformAdmin))->assertForbidden();
    $this->actingAs($this->orgAdmin)->get(route('admin.users.show', $attendee))->assertForbidden();
});

test('staff, ushers and guests cannot open the details page', function (): void {
    $usher = User::factory()->create()->assignMembership('usher', $this->org->id, $this->zoneA->id);

    $this->actingAs($this->staffA)->get(route('admin.users.show', $this->staffA))->assertForbidden();
    $this->actingAs($usher)->get(route('admin.users.show', $this->staffA))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.users.show', $this->staffA))->assertRedirect(route('login'));
});

test('platform administrators can inspect any account but not manage other platform accounts', function (): void {
    $platformAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $otherPlatformAdmin = User::factory()->create()->assignMembership('super_admin', null);

    $this->actingAs($platformAdmin)->get(route('admin.users.show', $this->foreignUser))->assertOk();

    Livewire::actingAs($platformAdmin)
        ->test(UserDetails::class, ['user' => $otherPlatformAdmin])
        ->call('removeMember')
        ->assertHasErrors('action');

    expect(User::query()->whereKey($otherPlatformAdmin->id)->exists())->toBeTrue();
});

test('the team list links each member to their details page', function (): void {
    Livewire::actingAs($this->orgAdmin)
        ->test(UserManagement::class)
        ->assertSee(route('admin.users.show', $this->staffA), false);
});

/*
 |--------------------------------------------------------------------------
 | Managing access
 |--------------------------------------------------------------------------
 */

test('organization admins can change a member\'s role and zone', function (): void {
    Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->set('role', 'admin')
        ->set('subdivisionId', (string) $this->zoneB->id)
        ->call('updateAccess')
        ->assertHasNoErrors();

    $this->staffA->refresh();

    expect($this->staffA->role)->toBe('admin')->and($this->staffA->subdivision_id)->toBe($this->zoneB->id);
});

test('zone-bound admins can only place members inside their own zone', function (): void {
    Livewire::actingAs($this->zoneAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->set('role', 'usher')
        ->set('subdivisionId', (string) $this->zoneB->id)
        ->call('updateAccess')
        ->assertHasNoErrors();

    $this->staffA->refresh();

    expect($this->staffA->role)->toBe('usher')->and($this->staffA->subdivision_id)->toBe($this->zoneA->id);
});

test('access changes reject platform roles and zones from another organization', function (): void {
    $foreignZone = Subdivision::factory()->create(['organization_id' => $this->foreignOrg->id]);

    Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->set('role', 'super_admin')
        ->call('updateAccess')
        ->assertHasErrors('role')
        ->set('role', 'staff')
        ->set('subdivisionId', (string) $foreignZone->id)
        ->call('updateAccess')
        ->assertHasErrors('subdivisionId');

    expect($this->staffA->fresh()?->role)->toBe('staff');
});

test('the member id cannot be tampered with to reach another zone', function (): void {
    Livewire::actingAs($this->zoneAdmin)->test(UserDetails::class, ['user' => $this->staffB])->assertForbidden();

    expect(fn() => Livewire::actingAs($this->zoneAdmin)->test(UserDetails::class, ['user' => $this->staffA])->set(
        'userId',
        $this->staffB->id,
    ))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('an admin who loses access mid-session is refused on the next action', function (): void {
    $page = Livewire::actingAs($this->zoneAdmin)->test(UserDetails::class, ['user' => $this->staffA]);

    $this->staffA->assignMembership('staff', $this->org->id, $this->zoneB->id);

    $page->call('sendPasswordReset')->assertForbidden();
});

/*
 |--------------------------------------------------------------------------
 | Account actions and self-modification guards
 |--------------------------------------------------------------------------
 */

test('admins can email a member a password reset link', function (): void {
    Notification::fake();

    Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->call('sendPasswordReset')
        ->assertSet('flashMessage', 'A password reset link was emailed to sam.alpha@example.com.');

    Notification::assertSentTo($this->staffA, ResetPassword::class);
});

test('admins can sign a member out of every device', function (): void {
    foreach (['session-one', 'session-two'] as $id) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $this->staffA->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
    $rememberToken = $this->staffA->getRememberToken();

    Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->call('signOutEverywhere')
        ->assertSet('flashMessage', 'Sam Alpha was signed out of 2 sessions.');

    expect(DB::table('sessions')->where('user_id', $this->staffA->id)->count())
        ->toBe(0)
        ->and($this->staffA->fresh()?->getRememberToken())
        ->not->toBe($rememberToken);
});

test('admins can remove a member and return to the team list', function (): void {
    Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->call('removeMember')
        ->assertRedirect(route('admin.users'));

    expect(User::query()->whereKey($this->staffA->id)->exists())->toBeFalse();

    Livewire::actingAs($this->orgAdmin)
        ->test(UserManagement::class)
        ->assertSee('Sam Alpha was removed from your team.');
});

test('admins cannot demote, sign out, reset or remove their own account', function (): void {
    Notification::fake();
    DB::table('sessions')->insert([
        'id' => 'own-session',
        'user_id' => $this->orgAdmin->id,
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    $page = Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->orgAdmin])
        ->assertSee('You cannot change your own role or zone')
        ->assertDontSee('Remove from team');

    foreach (['updateAccess', 'sendPasswordReset', 'signOutEverywhere', 'removeMember'] as $action) {
        $page->set('role', 'staff')->call($action)->assertHasErrors('action');
    }

    $admin = $this->orgAdmin->fresh();

    expect($admin)
        ->not
        ->toBeNull()
        ->and($admin?->role)
        ->toBe('admin')
        ->and(DB::table('sessions')->where('user_id', $this->orgAdmin->id)->count())
        ->toBe(1);

    Notification::assertNothingSent();
});

/*
 |--------------------------------------------------------------------------
 | Activity stays inside the inspecting admin's scope
 |--------------------------------------------------------------------------
 */

test('check-in and purchase activity only counts records inside the admin\'s zone', function (): void {
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    $tier = TicketType::factory()->create(['event_id' => $event->id]);

    // Sam works the shared gate, so they admit passes from both zones.
    foreach ([$this->zoneA, $this->zoneA, $this->zoneB] as $zone) {
        $order = Order::factory()
            ->paid()
            ->create([
                'organization_id' => $this->org->id,
                'subdivision_id' => $zone->id,
            ]);
        Attendee::factory()
            ->checkedIn()
            ->create([
                'order_id' => $order->id,
                'organization_id' => $this->org->id,
                'subdivision_id' => $zone->id,
                'ticket_type_id' => $tier->id,
                'checked_in_by' => $this->staffA->id,
                'name' => 'Guest of ' . $zone->name,
            ]);
    }

    Livewire::actingAs($this->zoneAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->assertViewHas('checkInCount', 2)
        ->assertSee('Guest of Zone Alpha')
        ->assertDontSee('Guest of Zone Bravo');

    Livewire::actingAs($this->orgAdmin)
        ->test(UserDetails::class, ['user' => $this->staffA])
        ->assertViewHas('checkInCount', 3)
        ->assertSee('Guest of Zone Bravo');
});
