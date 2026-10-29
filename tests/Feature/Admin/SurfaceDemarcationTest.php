<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;

function staff_member(string $role, ?Organization $org = null, ?Subdivision $zone = null): User
{
    $org ??= Organization::factory()->create();

    return User::factory()->create()->assignMembership($role, $org->id, $zone?->id);
}

test('guests see public navigation and sign in, never internal tools', function (): void {
    $this
        ->get(route('public.events.index'))
        ->assertOk()
        ->assertSee('Sign In')
        ->assertDontSee('Ops Hub')
        ->assertDontSee('ops/scanner')
        ->assertDontSee('Manage Event');
});

test('staff get a single discrete manage event pill instead of ops links', function (): void {
    $this
        ->actingAs(staff_member('admin'))
        ->get(route('public.events.index'))
        ->assertOk()
        ->assertSee('Manage Event')
        ->assertSee(route('admin.index'), false)
        ->assertDontSee('Ops Hub')
        ->assertDontSee('>Scanner<', false);
});

test('ushers are pointed at the gate terminal from the public site', function (): void {
    $this
        ->actingAs(staff_member('usher'))
        ->get(route('public.events.index'))
        ->assertSee('Manage Event')
        ->assertSee(route('ops.scanner'), false);
});

test('dashboard routes every role to its own surface', function (string $role, string $expectedRoute): void {
    $user = $role === 'super_admin'
        ? User::factory()->create()->assignMembership('super_admin', null)
        : staff_member($role);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($expectedRoute));
})->with([
    'admin' => ['admin', 'admin.index'],
    'staff' => ['staff', 'admin.index'],
    'usher' => ['usher', 'ops.scanner'],
    'super admin' => ['super_admin', 'platform.dashboard'],
]);

test('ops terminal shell contains only the scanner and cash desk', function (): void {
    $this
        ->actingAs(staff_member('usher'))
        ->get(route('ops.scanner'))
        ->assertOk()
        ->assertSee('Door Scanner')
        ->assertSee('Cash Desk')
        ->assertDontSee('Financial Ledger')
        ->assertDontSee('Attendee Roster')
        ->assertDontSee(route('admin.sessions'), false);
});

test('admin shell exposes the full administrative sidebar and active event switcher', function (): void {
    $this
        ->actingAs(staff_member('admin'))
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Admin Command')
        ->assertSee('Events')
        ->assertSee('Broadcast Hub')
        ->assertSee('Queue Monitor')
        ->assertSee('Staff &amp; Ushers', false)
        ->assertSee('Create your first event');
});

test('staff role does not see team management in the sidebar', function (): void {
    $this
        ->actingAs(staff_member('staff'))
        ->get(route('admin.index'))
        ->assertOk()
        ->assertDontSee('Staff &amp; Ushers', false);
});

test('ushers remain locked out of the admin surface', function (): void {
    $this->actingAs(staff_member('usher'))->get(route('admin.index'))->assertForbidden();
});
