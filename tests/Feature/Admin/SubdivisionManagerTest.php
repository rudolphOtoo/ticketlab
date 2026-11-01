<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SubdivisionManager;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SubdivisionManagerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['name' => 'Accra Tech Hub', 'slug' => 'accra-tech']);
        $this->admin = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'admin',
        ]);

        config(['services.paystack.secret_key' => 'sk_test_subdivisions']);
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/subaccount/ACCT_legon123' => Http::response([
                'status' => true,
                'data' => ['business_name' => 'East Legon', 'currency' => 'GHS', 'active' => true],
            ]),
            'api.paystack.co/subaccount/*' => Http::response([
                'status' => false,
                'message' => 'Subaccount not found',
            ], 404),
        ]);
    }

    public function test_admin_can_view_subdivisions(): void
    {
        $sub = Subdivision::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Campus',
            'slug' => 'main-campus',
        ]);

        $this
            ->actingAs($this->admin)
            ->get(route('admin.subdivisions'))
            ->assertOk()
            ->assertSee('Main Campus')
            ->assertSee('main-campus');
    }

    public function test_admin_can_create_subdivision(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SubdivisionManager::class)
            ->call('openCreateModal')
            ->set('name', 'East Legon Campus')
            ->set('slug', 'east-legon-campus')
            ->set('paystackSubaccountCode', 'ACCT_legon123')
            ->call('save')
            ->assertHasNoErrors();

        expect(
            Subdivision::query()
                ->where('organization_id', $this->org->id)
                ->where('slug', 'east-legon-campus')
                ->value('paystack_subaccount_code'),
        )
            ->toBe('ACCT_legon123');
    }

    public function test_a_payout_code_paystack_does_not_recognise_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SubdivisionManager::class)
            ->call('openCreateModal')
            ->set('name', 'Tema Campus')
            ->set('slug', 'tema-campus')
            ->set('paystackSubaccountCode', 'ACCT_unknown999')
            ->call('save')
            ->assertHasErrors('paystackSubaccountCode');

        expect(Subdivision::query()->where('slug', 'tema-campus')->exists())->toBeFalse();
    }

    public function test_zone_scoped_admins_can_rename_but_not_redirect_payouts(): void
    {
        $sub = Subdivision::create([
            'organization_id' => $this->org->id,
            'name' => 'Kumasi',
            'slug' => 'kumasi',
            'paystack_subaccount_code' => 'ACCT_legon123',
        ]);
        $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $sub->id);

        $component = Livewire::actingAs($zoneAdmin)->test(SubdivisionManager::class)->call('editSubdivision', $sub->id);

        $component->set('name', 'Kumasi Central')->call('save')->assertHasNoErrors();
        $component
            ->call('editSubdivision', $sub->id)
            ->set('paystackSubaccountCode', '')
            ->call('save')
            ->assertHasErrors('paystackSubaccountCode');

        expect($sub->fresh())->name->toBe('Kumasi Central')->paystack_subaccount_code->toBe('ACCT_legon123');
    }

    public function test_admin_can_edit_subdivision(): void
    {
        $sub = Subdivision::create([
            'organization_id' => $this->org->id,
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        Livewire::actingAs($this->admin)
            ->test(SubdivisionManager::class)
            ->call('editSubdivision', $sub->id)
            ->set('name', 'Updated Subdivision Name')
            ->call('save')
            ->assertHasNoErrors();

        expect($sub->fresh()->name)->toBe('Updated Subdivision Name');
    }

    public function test_admin_can_delete_subdivision(): void
    {
        $sub = Subdivision::create([
            'organization_id' => $this->org->id,
            'name' => 'To Be Deleted',
            'slug' => 'to-be-deleted',
        ]);

        Livewire::actingAs($this->admin)->test(SubdivisionManager::class)->call('deleteSubdivision', $sub->id);

        expect(Subdivision::query()->whereKey($sub->id)->exists())->toBeFalse();
    }

    public function test_staff_cannot_access_subdivision_manager(): void
    {
        $staff = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'staff',
        ]);

        $this->actingAs($staff)->get(route('admin.subdivisions'))->assertForbidden();
    }
}
