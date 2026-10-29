<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.platform')]
#[Title('Developer Super Admin Console')]
class PlatformDashboard extends Component
{
    public string $tenantSearch = '';

    public ?int $selectedTenantId = null;

    public float $grossMerchandiseValue = 0.0;

    public float $platformFeeAccumulator = 0.0;

    public int $paidTicketsCount = 0;

    public string $deploymentMode = 'platform';

    public ?string $homeOrgSlug = null;

    public ?string $settingSuccessMessage = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user === null || !$user->isSuperAdmin()) {
            abort(403, 'Platform Super Admin clearance required.');
        }

        $this->deploymentMode = (string) \App\Models\PlatformSetting::get('deployment_mode', 'platform');
        $this->homeOrgSlug = \App\Models\PlatformSetting::getHomeOrgSlug();
    }

    public function savePlatformSettings(): void
    {
        $rules = [
            'deploymentMode' => ['required', 'string', 'in:platform,dedicated'],
            'homeOrgSlug' => ['nullable', 'string', 'max:100'],
        ];

        if ($this->deploymentMode === 'dedicated') {
            $rules['homeOrgSlug'] = ['required', 'string', 'exists:organizations,slug'];
        }

        $this->validate($rules);

        \App\Models\PlatformSetting::set('deployment_mode', $this->deploymentMode);
        \App\Models\PlatformSetting::set(
            'home_org_slug',
            $this->deploymentMode === 'dedicated' ? (string) $this->homeOrgSlug : '',
        );

        $this->settingSuccessMessage = $this->deploymentMode === 'dedicated'
            ? "Runtime mode updated to Dedicated Bespoke Client ({$this->homeOrgSlug}). Root / now serves this client."
            : 'Runtime mode updated to Multi-Tenant Platform Hub. Root / now serves discovery hub.';
    }

    public function viewTenant(int $tenantId): void
    {
        $this->selectedTenantId = $tenantId;
    }

    public function closeTenantModal(): void
    {
        $this->selectedTenantId = null;
    }

    /**
     * @return array{database: string, cache: string, queue: string, failed_jobs: int}
     */
    protected function getSystemHealth(): array
    {
        $dbStatus = 'Connected';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbStatus = 'Degraded';
        }

        $failedJobsCount = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobsCount = DB::table('failed_jobs')->count();
        }

        return [
            'database' => $dbStatus . ' (' . config('database.default', 'pgsql') . ')',
            'cache' => (string) config('cache.default', 'file'),
            'queue' => (string) config('queue.default', 'database'),
            'failed_jobs' => $failedJobsCount,
        ];
    }

    public function render(): View
    {
        $totalTenants = Organization::query()->count();
        $totalEvents = Event::query()->where('status', 'published')->count();

        // Calculate GMV from paid orders
        $this->grossMerchandiseValue = (float) Order::query()->where('payment_status', OrderStatus::Paid->value)->sum(
            'gross_amount',
        );

        // Total paid tickets count across all organizations
        $this->paidTicketsCount = Attendee::query()
            ->whereHas('order', static function ($query): void {
                $query->where('payment_status', OrderStatus::Paid->value);
            })
            ->count();

        // Platform fee accumulator: GHS 3.00 per paid ticket sold
        $this->platformFeeAccumulator = $this->paidTicketsCount * 3.00;

        // Fetch tenants with aggregated event counts
        $tenantQuery = Organization::query()->withCount('events');

        if (trim($this->tenantSearch) !== '') {
            $term = '%' . trim($this->tenantSearch) . '%';
            $tenantQuery->where(static function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('slug', 'like', $term);
            });
        }

        $tenants = $tenantQuery->latest('id')->take(25)->get();

        $selectedTenant = null;
        $tenantEvents = Collection::empty();

        if ($this->selectedTenantId !== null) {
            $selectedTenant = Organization::query()->find($this->selectedTenantId);
            if ($selectedTenant !== null) {
                $tenantEvents = $selectedTenant->events()->with('ticketTypes')->latest('id')->get();
            }
        }

        return view('livewire.platform.platform-dashboard', [
            'totalTenants' => $totalTenants,
            'totalEvents' => $totalEvents,
            'paidTicketsCount' => $this->paidTicketsCount,
            'grossMerchandiseValue' => $this->grossMerchandiseValue,
            'platformFeeAccumulator' => $this->platformFeeAccumulator,
            'health' => $this->getSystemHealth(),
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'tenantEvents' => $tenantEvents,
            'allOrganizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
