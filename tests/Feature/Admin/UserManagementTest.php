<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManagement;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->zone = Subdivision::factory()->create(['organization_id' => $this->org->id]);
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
});

test('admins list only staff from their own organization', function (): void {
    User::factory()->create(['name' => 'Same Org Usher'])->assignMembership('usher', $this->org->id);
    User::factory()
        ->create(['name' => 'Foreign Usher'])
        ->assignMembership('usher', Organization::factory()->create()->id);

    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->assertSee('Same Org Usher')
        ->assertDontSee('Foreign Usher');
});

test('admin creates an usher through assignMembership with zone scoping', function (): void {
    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('openCreateModal')
        ->set('name', 'Ama Mensah')
        ->set('email', 'ama@example.com')
        ->set('password', 'Sup3r-Secret-Pass!')
        ->set('role', 'usher')
        ->set('subdivisionId', (string) $this->zone->id)
        ->call('save')
        ->assertHasNoErrors();

    $usher = User::query()->where('email', 'ama@example.com')->firstOrFail();

    expect($usher)
        ->role->toBe('usher')
        ->organization_id->toBe($this->org->id)
        ->subdivision_id->toBe($this->zone->id)->and($usher->hasVerifiedEmail())->toBeTrue();
});

test('roles outside the assignable set are rejected', function (): void {
    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('openCreateModal')
        ->set('name', 'Escalation')
        ->set('email', 'escalate@example.com')
        ->set('password', 'Sup3r-Secret-Pass!')
        ->set('role', 'super_admin')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::query()->where('email', 'escalate@example.com')->exists())->toBeFalse();
});

test('zones from another organization cannot be assigned', function (): void {
    $foreignZone = Subdivision::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('openCreateModal')
        ->set('name', 'Cross Zone')
        ->set('email', 'cross@example.com')
        ->set('password', 'Sup3r-Secret-Pass!')
        ->set('subdivisionId', (string) $foreignZone->id)
        ->call('save')
        ->assertHasErrors(['subdivisionId']);
});

test('editing updates details and role without touching the password when left blank', function (): void {
    $member = User::factory()->create(['password' => 'original-password'])->assignMembership('usher', $this->org->id);
    $hash = $member->password;

    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('editUser', $member->id)
        ->set('name', 'Promoted Member')
        ->set('role', 'staff')
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh())->name->toBe('Promoted Member')->role->toBe('staff')->password->toBe($hash);
});

test('admins cannot delete themselves', function (): void {
    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('deleteUser', $this->admin->id)
        ->assertSet('flashMessage', 'You cannot delete your own account.');

    expect($this->admin->fresh())->not->toBeNull();
});

test('admins cannot change their own role', function (): void {
    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('editUser', $this->admin->id)
        ->set('role', 'usher')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->admin->fresh()->role)->toBe('admin');
});

test('members of other organizations cannot be edited or deleted', function (): void {
    $foreign = User::factory()->create()->assignMembership('usher', Organization::factory()->create()->id);

    Livewire::actingAs($this->admin)
        ->test(UserManagement::class)
        ->call('editUser', $foreign->id)
        ->assertSet('isModalOpen', false)
        ->call('deleteUser', $foreign->id);

    expect($foreign->fresh())->not->toBeNull();
});

test('admins can remove team members', function (): void {
    $member = User::factory()->create()->assignMembership('usher', $this->org->id);

    Livewire::actingAs($this->admin)->test(UserManagement::class)->call('deleteUser', $member->id);

    expect($member->fresh())->toBeNull();
});

test('zone-bound admins can only provision staff inside their own zone', function (): void {
    $otherZone = Subdivision::factory()->create(['organization_id' => $this->org->id]);
    $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zone->id);
    User::factory()->create(['name' => 'Other Zone Usher'])->assignMembership('usher', $this->org->id, $otherZone->id);

    Livewire::actingAs($zoneAdmin)
        ->test(UserManagement::class)
        ->assertDontSee('Other Zone Usher')
        ->call('openCreateModal')
        ->set('name', 'Zone Usher')
        ->set('email', 'zone-usher@example.com')
        ->set('password', 'Sup3r-Secret-Pass!')
        ->set('subdivisionId', (string) $otherZone->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'zone-usher@example.com')->value('subdivision_id'))->toBe($this->zone->id);
});

test('staff and ushers cannot open team management', function (string $role): void {
    $user = User::factory()->create()->assignMembership($role, $this->org->id);

    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
})->with(['staff', 'usher']);
