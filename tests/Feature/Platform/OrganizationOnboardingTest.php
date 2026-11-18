<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Platform\OnboardOrganization;
use App\Livewire\Platform\Organizations;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\OrganizationAdminInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.paystack.secret_key' => 'sk_test_onboarding',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        'api.paystack.co/subaccount/ACCT_kumasi*' => Http::response([
            'status' => true,
            'data' => ['active' => true, 'currency' => 'GHS'],
        ]),
        'api.paystack.co/subaccount/ACCT_accra*' => Http::response([
            'status' => true,
            'data' => ['active' => true, 'currency' => 'GHS'],
        ]),
        'api.paystack.co/subaccount/*' => Http::response(['status' => false, 'message' => 'Subaccount not found'], 404),
    ]);

    $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
});

/**
 * @return \Livewire\Features\SupportTesting\Testable
 */
function onboarding_form(User $actor)
{
    return Livewire::actingAs($actor)
        ->test(OnboardOrganization::class)
        ->set('name', 'Ghana Methodist Student Union')
        ->set('contactEmail', 'info@ghamsu.example.test')
        ->set('contactPhone', '+233 20 000 0000')
        ->set('zones', [
            ['name' => 'Accra', 'subaccount' => 'ACCT_accra1'],
            ['name' => 'Kumasi', 'subaccount' => 'ACCT_kumasi1'],
            ['name' => 'Cape Coast', 'subaccount' => ''],
        ])
        ->set('adminName', 'Connexional Admin')
        ->set('adminEmail', 'admin@ghamsu.example.test');
}

test('a super admin onboards an organisation with zones, verified subaccounts and an invited admin', function () {
    Notification::fake();

    onboarding_form($this->superAdmin)
        ->assertSet('slug', 'ghana-methodist-student-union')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('platform.organizations'));

    $org = Organization::query()->where('slug', 'ghana-methodist-student-union')->sole();
    expect($org->subdivision_label)
        ->toBe('Zone')
        ->and($org->settings)
        ->toMatchArray([
            'currency' => 'GHS',
            'timezone' => 'Africa/Accra',
            'contact_email' => 'info@ghamsu.example.test',
        ])
        ->and($org->subdivisions()->orderBy('name')->pluck('paystack_subaccount_code', 'name')->all())
        ->toBe(['Accra' => 'ACCT_accra1', 'Cape Coast' => null, 'Kumasi' => 'ACCT_kumasi1']);

    $admin = User::query()->where('email', 'admin@ghamsu.example.test')->sole();
    expect($admin->role)
        ->toBe('admin')
        ->and($admin->organization_id)
        ->toBe($org->id)
        ->and($admin->subdivision_id)
        ->toBeNull()
        ->and($admin->password)
        ->toBeNull();

    Notification::assertSentTo($admin, OrganizationAdminInvitation::class);
    expect(AuditLog::query()->where('action', 'organization.onboarded')->sole()->user_id)->toBe($this->superAdmin->id);
});

test('a subaccount Paystack does not recognise blocks onboarding and nothing is created', function () {
    Notification::fake();

    onboarding_form($this->superAdmin)
        ->set('zones', [
            ['name' => 'Accra', 'subaccount' => 'ACCT_accra1'],
            ['name' => 'Kumasi', 'subaccount' => 'ACCT_typo99'],
        ])
        ->call('save')
        ->assertHasErrors('zones.1.subaccount');

    expect(Organization::query()->count())
        ->toBe(0)
        ->and(User::query()->where('email', 'admin@ghamsu.example.test')->exists())
        ->toBeFalse();
    Notification::assertNothingSent();
});

test('onboarding rejects reserved or taken web addresses, a taken admin email and an empty zone list', function () {
    Organization::factory()->create(['slug' => 'taken-church']);
    User::factory()->create(['email' => 'admin@ghamsu.example.test']);

    onboarding_form($this->superAdmin)->set('slug', 'admin')->call('save')->assertHasErrors(['slug', 'adminEmail']);
    onboarding_form($this->superAdmin)->set('slug', 'taken-church')->call('save')->assertHasErrors('slug');
    onboarding_form($this->superAdmin)
        ->set('zones', [['name' => '', 'subaccount' => '']])
        ->call('save')
        ->assertHasErrors('zones');

    expect(Organization::query()->count())->toBe(1);
});

test('only the platform super admin can open the organisation screens', function () {
    $org = Organization::factory()->create();
    $orgAdmin = User::factory()->create()->assignMembership('admin', $org->id);

    foreach (['platform.organizations', 'platform.organizations.create'] as $route) {
        $this->actingAs($orgAdmin)->get(route($route))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route($route))->assertOk();
    }
});

test('the invited admin sets a password once through the signed link and lands in their admin dashboard', function () {
    $org = Organization::factory()->create(['name' => 'Ghana Methodist Student Union']);
    $admin = User::factory()
        ->create(['password' => null, 'email_verified_at' => null])
        ->assignMembership('admin', $org->id);
    $url = OrganizationAdminInvitation::acceptUrl($admin);

    $this->get($url)->assertOk()->assertSee('Ghana Methodist Student Union')->assertSee($admin->email);
    $this->get(route('invitations.accept', ['user' => $admin->id]))->assertForbidden();

    Livewire::test(AcceptInvitation::class, ['user' => $admin])
        ->set('password', 'a-strong-Passw0rd!')
        ->set('password_confirmation', 'a-strong-Passw0rd!')
        ->call('accept')
        ->assertRedirect(route('admin.index'));

    expect(Auth::id())->toBe($admin->id)->and($admin->fresh()->hasVerifiedEmail())->toBeTrue();

    // The link is single-use: once a password exists it no longer opens.
    Auth::logout();
    $this->get($url)->assertStatus(410);
});

test('an expired invitation link is refused', function () {
    $admin = User::factory()
        ->create(['password' => null])
        ->assignMembership('admin', Organization::factory()->create()->id);
    $url = OrganizationAdminInvitation::acceptUrl($admin);

    $this->travel(OrganizationAdminInvitation::INVITATION_DAYS + 1)->days();

    $this->get($url)->assertForbidden();
});

test('the organisation list shows event counts, zones missing a payout account and paid totals', function () {
    Notification::fake();
    $org = Organization::factory()->create([
        'name' => 'Ghana Methodist Student Union',
        'slug' => 'gmsu',
        'subdivision_label' => 'Zone',
    ]);
    Subdivision::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Accra',
        'paystack_subaccount_code' => 'ACCT_accra1',
    ]);
    Subdivision::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Cape Coast',
        'paystack_subaccount_code' => null,
    ]);
    Event::factory()->count(2)->create(['organization_id' => $org->id]);
    Order::factory()->create([
        'organization_id' => $org->id,
        'payment_status' => OrderStatus::Paid,
        'gross_amount' => 150,
    ]);
    Order::factory()->create([
        'organization_id' => $org->id,
        'payment_status' => OrderStatus::Pending,
        'gross_amount' => 999,
    ]);
    $pending = User::factory()->create(['password' => null])->assignMembership('admin', $org->id);

    Livewire::actingAs($this->superAdmin)
        ->test(Organizations::class)
        ->assertSeeInOrder([
            'Ghana Methodist Student Union',
            '2',
            '1 of 2: Cape Coast',
            'GHS 150.00',
            $pending->email,
            'Invitation pending',
        ])
        ->call('resendInvitation', $pending->id)
        ->assertSee('Invitation re-sent');

    Notification::assertSentTo($pending, OrganizationAdminInvitation::class);
});
