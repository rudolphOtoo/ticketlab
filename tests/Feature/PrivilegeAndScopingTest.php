<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Ops\ScannerTerminal;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Livewire\Livewire;

test('privilege fields are ignored by mass assignment', function () {
    $org = Organization::factory()->create();

    $user = User::query()->create([
        'name' => 'Escalation Attempt',
        'email' => 'escalate@example.com',
        'password' => 'password',
        'role' => 'superadmin',
        'organization_id' => $org->id,
        'subdivision_id' => null,
    ]);

    $user->refresh();

    expect($user->role)->toBe('attendee')->and($user->organization_id)->toBeNull();
});

test('registration cannot smuggle a role or organization through the request', function () {
    if (!Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration is disabled.');
    }

    $org = Organization::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'Sneaky Buyer',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'superadmin',
        'organization_id' => $org->id,
    ]);

    $user = User::query()->where('email', 'sneaky@example.com')->firstOrFail();

    expect($user->role)->toBe('admin')
        ->and($user->organization_id)->not->toBe($org->id)
        ->and($user->isSuperAdmin())->toBeFalse();
});

test('membership is granted only through the explicit model method', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    $user->assignMembership('usher', $org->id);

    expect($user->fresh())->role->toBe('usher')->organization_id->toBe($org->id)->subdivision_id->toBeNull();
});

test('legacy zones and devdojo schema is gone and new accounts default to attendee', function () {
    expect(Schema::hasTable('zones'))->toBeFalse();
    expect(Schema::hasTable('social_provider_user'))->toBeFalse();
    expect(Schema::hasTable('email_verification_codes'))->toBeFalse();
    expect(Schema::hasColumn('users', 'zone_id'))->toBeFalse();

    DB::table('users')->insert([
        'name' => 'Raw Insert',
        'email' => 'raw@example.com',
        'password' => 'hashed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('users')->where('email', 'raw@example.com')->value('role'))->toBe('attendee');
});

test('roster toggle checks a pass in and out against the locked row', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id, 'role' => 'admin']);
    $attendee = Attendee::factory()->create(['organization_id' => $org->id]);

    $component = Livewire::actingAs($admin)->test(AttendeeRoster::class)->call('toggleCheckIn', $attendee->id);

    expect($attendee->fresh())->checked_in_at->not->toBeNull()->checked_in_by->toBe($admin->id);

    $component->call('toggleCheckIn', $attendee->id);

    expect($attendee->fresh())->checked_in_at->toBeNull()->checked_in_by->toBeNull();
});

test('scanner turnout counts only passes for the active event', function () {
    $org = Organization::factory()->create();
    $usher = User::factory()->create(['organization_id' => $org->id, 'role' => 'usher']);

    $current = Event::factory()->create(['organization_id' => $org->id]);
    $other = Event::factory()->create(['organization_id' => $org->id]);
    $currentTier = TicketType::factory()->create(['event_id' => $current->id]);
    $otherTier = TicketType::factory()->create(['event_id' => $other->id]);

    Attendee::factory()->create([
        'organization_id' => $org->id,
        'ticket_type_id' => $currentTier->id,
        'checked_in_at' => now(),
    ]);
    Attendee::factory()->create(['organization_id' => $org->id, 'ticket_type_id' => $currentTier->id]);
    Attendee::factory()
        ->count(3)
        ->create(['organization_id' => $org->id, 'ticket_type_id' => $otherTier->id, 'checked_in_at' => now()]);

    Livewire::actingAs($usher)
        ->test(ScannerTerminal::class, ['eventId' => $current->id])
        ->assertViewHas('totalCheckedIn', 1)
        ->assertViewHas('totalExpected', 2)
        ->assertViewHas('turnoutPercent', 50);
});
