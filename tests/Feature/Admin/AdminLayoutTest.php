<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;

/*
 |--------------------------------------------------------------------------
 | Admin shell: tenant branding and the account / sign-out menu
 |--------------------------------------------------------------------------
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['name' => 'Perez Dome & Summit Global']);
    $this->admin = User::factory()->create(['name' => 'Ama Owusu'])->assignMembership('admin', $this->org->id);
});

test('the sidebar is branded with the signed-in admin\'s own organisation', function (): void {
    $other = Organization::factory()->create(['name' => 'Grace Chapel International']);
    $otherAdmin = User::factory()->create()->assignMembership('admin', $other->id);

    $this
        ->actingAs($this->admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSeeInOrder(['data-tenant-initials', 'PD', 'data-tenant-name', 'Perez Dome &amp; Summit Global'], false)
        ->assertDontSee('Grace Chapel International');

    $this
        ->actingAs($otherAdmin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSeeInOrder(['data-tenant-initials', 'GC', 'data-tenant-name', 'Grace Chapel International'], false)
        ->assertDontSee('Perez Dome');
});

test('an organisation logo from a safe address replaces the initials badge', function (string $url): void {
    $this->org->update(['logo_url' => $url]);

    $this
        ->actingAs($this->admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee('data-tenant-logo', false)
        ->assertSee($url, false)
        ->assertDontSee('data-tenant-initials', false);
})->with(['https url' => 'https://cdn.example.com/perez.png', 'same-site path' => '/storage/logos/perez.png']);

test('an unsafe logo address is ignored in favour of the initials badge', function (string $url): void {
    $this->org->update(['logo_url' => $url]);

    $this
        ->actingAs($this->admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertDontSee('data-tenant-logo', false)
        ->assertSee('data-tenant-initials', false);
})->with(['javascript url' => 'javascript:alert(1)', 'protocol-relative url' => '//evil.example/logo.png']);

test('zone-bound admins see their zone under the organisation name', function (): void {
    $zone = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kumasi South']);
    $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $zone->id);

    $this->actingAs($zoneAdmin)->get(route('admin.index'))->assertOk()->assertSee('Kumasi South · Zone admin');
    $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->assertSee('Admin Command');
});

test('the account menu shows who is signed in and signs out with a csrf-protected post', function (): void {
    $html = (string) $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('id="admin-account-menu"')
        ->toContain('Ama Owusu')
        ->toContain($this->admin->email)
        ->toContain('href="' . route('profile.edit') . '"');

    preg_match('#<form method="POST" action="([^"]+)"[^>]*data-sign-out>(.*?)</form>#s', $html, $form);

    expect($form)->not->toBeEmpty();
    expect($form[1])->toBe(route('logout'));
    expect($form[2])->toContain('name="_token"')->toContain('Sign out');
    expect($html)->not->toContain('href="' . route('logout') . '"');
});

test('signing out only works as a post request', function (): void {
    // A plain link or prefetch can never sign an admin out.
    $this->actingAs($this->admin)->get('/logout')->assertClientError();
    $this->assertAuthenticatedAs($this->admin);

    $this->actingAs($this->admin)->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});
