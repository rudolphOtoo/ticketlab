<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_public_registration_creates_an_attendee_account_and_never_an_organisation(): void
    {
        $response = $this->post(route('register.store'), [
            'organization_name' => 'Kingdom Gatherings',
            'name' => 'Pastor Mensah',
            'email' => 'mensah@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        $user = \App\Models\User::query()->where('email', 'mensah@example.test')->firstOrFail();

        expect($user->role)
            ->toBe('attendee')
            ->and($user->organization_id)
            ->toBeNull()
            ->and($user->subdivision_id)
            ->toBeNull()
            ->and($user->isAdmin())
            ->toBeFalse()
            ->and(\App\Models\Organization::query()->count())
            ->toBe(0);

        $this->get(route('admin.index'))->assertForbidden();
    }
}
