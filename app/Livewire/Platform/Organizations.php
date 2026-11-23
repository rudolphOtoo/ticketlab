<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\OrganizationAdminInvitation;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every organisation on the platform, with its events, the zones that cannot take payments yet, and paid totals.
 */
#[Layout('layouts.platform')]
#[Title('Organisations')]
class Organizations extends Component
{
    public string $search = '';

    /** Archived organisations are left out of the list unless asked for. */
    #[Url(as: 'archived')]
    public bool $showArchived = false;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        self::superAdmin();
        $this->flashMessage = session('platform_status');
    }

    /**
     * Re-send an invitation to an organisation admin who has not set a password yet.
     */
    public function resendInvitation(int $userId): void
    {
        self::superAdmin();

        $admin = User::query()
            ->whereKey($userId)
            ->whereNotNull('organization_id')
            ->where('role', 'admin')
            ->where(static fn(Builder $query) => $query->whereNull('password')->orWhere('password', ''))
            ->with('organization')
            ->first();

        if ($admin === null || $admin->organization === null) {
            $this->flashMessage = 'That admin has already accepted their invitation.';

            return;
        }

        $admin->notify(new OrganizationAdminInvitation($admin->organization));
        $this->flashMessage = "Invitation re-sent to {$admin->email}.";
    }

    public function render(PaystackSubaccountResolver $subaccounts): View
    {
        self::superAdmin();
        $term = trim($this->search);

        $organizations = Organization::query()
            ->when(!$this->showArchived, static fn(Builder $query) => $query->where(
                'status',
                '!=',
                OrganizationStatus::Archived->value,
            ))
            ->when($term !== '', static fn(Builder $query) => $query->where(static fn(Builder $match) => $match->where(
                'name',
                'like',
                "%{$term}%",
            )->orWhere('slug', 'like', "%{$term}%")))
            ->withCount('events')
            ->withSum([
                'orders as paid_total' => static fn(Builder $orders) => $orders->where(
                    'payment_status',
                    OrderStatus::Paid->value,
                ),
            ], 'gross_amount')
            ->with([
                'subdivisions:id,organization_id,name,paystack_subaccount_code,payout_verified_code,payout_verified_mode',
                'users' => static fn($users) => $users->where('role', 'admin')->whereNull('subdivision_id'),
            ])
            ->orderBy('name')
            ->get();

        return view('livewire.platform.organizations', [
            'organizations' => $organizations,
            'archivedCount' => Organization::query()->where('status', OrganizationStatus::Archived->value)->count(),
            // Stored verification only: listing never calls Paystack.
            'missingSubaccounts' => $organizations->mapWithKeys(static fn(Organization $organization): array => [
                $organization->id => $organization
                    ->subdivisions
                    ->filter(static fn(Subdivision $zone): bool => $subaccounts->forSubdivision($zone) === null)
                    ->pluck('name')
                    ->all(),
            ]),
        ]);
    }

    private static function superAdmin(): User
    {
        $user = Auth::user();

        if (!$user instanceof User || !$user->isSuperAdmin()) {
            abort(403, 'Platform Super Admin clearance required.');
        }

        return $user;
    }
}
