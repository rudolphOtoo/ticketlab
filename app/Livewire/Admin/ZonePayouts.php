<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Connects each zone to its Paystack subaccount and reconciles what every zone has been paid.
 */
#[Layout('layouts.admin')]
#[Title('Zone Payouts')]
class ZonePayouts extends Component
{
    #[Locked]
    public ?int $editingZoneId = null;

    public string $subaccountCode = '';

    /** @var array<int, array{recognised: bool|null, message: string|null, name: string|null, bank: string|null, account: string|null}> */
    #[Locked]
    public array $checks = [];

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->organizationId();
    }

    public function editZone(int $zoneId): void
    {
        $zone = $this->zone($zoneId);

        if ($zone === null) {
            return;
        }

        $this->resetValidation();
        $this->editingZoneId = $zone->id;
        $this->subaccountCode = $zone->paystack_subaccount_code ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingZoneId = null;
        $this->subaccountCode = '';
        $this->resetValidation();
    }

    public function saveZone(PaystackSubaccountResolver $subaccounts): void
    {
        $zone = $this->zone($this->editingZoneId);

        if ($zone === null) {
            $this->cancelEdit();

            return;
        }

        $code = trim($this->subaccountCode);

        if ($code === '') {
            $zone->forceFill(['paystack_subaccount_code' => null])->save();
            unset($this->checks[$zone->id]);
            $this->flashMessage = "{$zone->name} is disconnected; its sales now settle to the main account.";
            $this->cancelEdit();

            return;
        }

        $this->validate(['subaccountCode' => ['regex:/^ACCT_[a-z0-9]+$/i', 'max:64']], [
            'subaccountCode.regex' => 'Paystack subaccount codes look like ACCT_xxxxxxxxxxxx.',
        ]);

        // Never save a code Paystack has not confirmed for the active key; a bad code fails every checkout.
        $check = $this->check($subaccounts, $code);

        if ($check['recognised'] !== true) {
            $this->addError('subaccountCode', $check['message'] ?? 'Paystack does not recognise this subaccount.');

            return;
        }

        $zone->forceFill(['paystack_subaccount_code' => $code])->save();
        $this->checks[$zone->id] = $check;
        $this->flashMessage = "{$zone->name} now settles to {$check['name']} ({$check['bank']}).";
        $this->cancelEdit();
    }

    public function verifyZone(int $zoneId, PaystackSubaccountResolver $subaccounts): void
    {
        $zone = $this->zone($zoneId);

        if ($zone?->paystack_subaccount_code !== null) {
            $this->checks[$zone->id] = $this->check($subaccounts, $zone->paystack_subaccount_code);
        }
    }

    /**
     * @return array{recognised: bool|null, message: string|null, name: string|null, bank: string|null, account: string|null}
     */
    private function check(PaystackSubaccountResolver $subaccounts, string $code): array
    {
        $result = $subaccounts->inspect($code);

        $message = match (true) {
            $result['recognised'] === true => null,
            // Known to Paystack but unusable (deactivated, wrong currency): the resolver says why.
            $result['details'] !== [] => $result['message'],
            $result['recognised'] === false => 'Paystack does not recognise this subaccount for the '
                . $subaccounts->mode()
                . ' keys.',
            default => $result['message'] ?? 'Paystack could not be reached. Try again.',
        };

        return [...$this->summary($result['details']), 'recognised' => $result['recognised'], 'message' => $message];
    }

    /**
     * @param array<array-key, mixed> $details
     *
     * @return array{name: string|null, bank: string|null, account: string|null}
     */
    private function summary(array $details): array
    {
        $field = static fn(string $key): ?string => is_scalar($details[$key] ?? null) ? (string) $details[$key] : null;

        return [
            'name' => $field('business_name'),
            'bank' => $field('settlement_bank'),
            'account' => $field('account_number'),
        ];
    }

    private function organizationId(): int
    {
        $user = Auth::user();

        // Redirecting revenue is an owner decision: zone-scoped staff cannot change where payouts go.
        if (
            !$user instanceof User
            || $user->organization_id === null
            || !$user->canManageTeam()
            || $user->subdivision_id !== null
        ) {
            abort(403);
        }

        return $user->organization_id;
    }

    private function zone(?int $zoneId): ?Subdivision
    {
        return $zoneId === null
            ? null
            : Subdivision::query()->where('organization_id', $this->organizationId())->whereKey($zoneId)->first();
    }

    /**
     * @return EloquentCollection<int, Subdivision>
     */
    private function zones(): EloquentCollection
    {
        return Subdivision::query()->where('organization_id', $this->organizationId())->orderBy('name')->get();
    }

    /**
     * Paid revenue per zone: what settled to its subaccount, and what was collected centrally and is still owed.
     *
     * @return Collection<int|string, object{subdivision_id: int|null, orders: int, gross: float, net: float, routed: float, owed: float}>
     */
    private function ledger(int $organizationId): Collection
    {
        /** @var Collection<int|string, object{subdivision_id: int|null, orders: int, gross: float, net: float, routed: float, owed: float}> */
        return Order::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', OrderStatus::Paid)
            ->groupBy('subdivision_id')
            ->select('subdivision_id')
            ->selectRaw('count(*) as orders')
            ->selectRaw('coalesce(sum(gross_amount), 0) as gross')
            ->selectRaw('coalesce(sum(net_amount), 0) as net')
            ->selectRaw(
                'coalesce(sum(case when payout_subaccount_code is not null then net_amount else 0 end), 0) as routed',
            )
            ->selectRaw('coalesce(sum(case when payout_subaccount_code is null then net_amount else 0 end), 0) as owed')
            ->toBase()
            ->get()
            ->keyBy(static fn(object $row): string => (string) ($row->subdivision_id ?? 'none'));
    }

    public function render(PaystackSubaccountResolver $subaccounts): View
    {
        $organizationId = $this->organizationId();
        $organization = Organization::query()->findOrFail($organizationId);
        $settings = $organization->settings ?? [];

        return view('livewire.admin.zone-payouts', [
            'organization' => $organization,
            'zones' => $this->zones(),
            'ledger' => $this->ledger($organizationId),
            'splitsEnabled' =>
                (bool) config('services.paystack.split_payments')
                    && ($settings['paystack_split_payments'] ?? true) !== false,
            'mode' => $subaccounts->mode(),
            'policy' => config('services.paystack.unrouted_zone_policy') === 'block' ? 'block' : 'fallback',
            'subaccounts' => $subaccounts,
        ]);
    }
}
