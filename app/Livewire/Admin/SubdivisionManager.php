<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Subdivision;
use App\Models\User;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Subdivision Management')]
class SubdivisionManager extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $isModalOpen = false;

    #[Locked]
    public ?int $editingSubdivisionId = null;

    public string $name = '';

    public string $slug = '';

    public ?string $paystackSubaccountCode = null;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->actor();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(): void
    {
        if ($this->editingSubdivisionId === null) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function openCreateModal(): void
    {
        $this->requireOrganizationWide($this->actor());
        $this->resetValidation();
        $this->reset(['editingSubdivisionId', 'name', 'slug', 'paystackSubaccountCode']);
        $this->isModalOpen = true;
    }

    public function editSubdivision(int $id): void
    {
        $actor = $this->actor();
        $sub = $this->zones($actor)->whereKey($id)->first();

        if ($sub === null) {
            return;
        }

        $this->resetValidation();
        $this->editingSubdivisionId = $sub->id;
        $this->name = $sub->name;
        $this->slug = $sub->slug;
        $this->paystackSubaccountCode = $sub->paystack_subaccount_code;
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingSubdivisionId = null;
        $this->reset(['name', 'slug', 'paystackSubaccountCode']);
    }

    public function save(PaystackSubaccountResolver $subaccounts): void
    {
        $actor = $this->actor();
        $orgId = (int) $actor->organization_id;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subdivisions', 'slug')
                    ->where('organization_id', $orgId)
                    ->ignore($this->editingSubdivisionId),
            ],
            'paystackSubaccountCode' => ['nullable', 'string', 'max:64', 'regex:/^ACCT_[a-z0-9]+$/i'],
        ], [
            'paystackSubaccountCode.regex' => 'Paystack subaccount codes look like ACCT_xxxxxxxxxxxx.',
        ]);

        $sub = $this->editingSubdivisionId === null
            ? $this->newZone($actor, $orgId)
            : $this->zones($actor)->whereKey($this->editingSubdivisionId)->first();

        if ($sub === null) {
            $this->closeModal();

            return;
        }

        $code = filled($this->paystackSubaccountCode) ? trim($this->paystackSubaccountCode) : null;

        if ($code !== $sub->paystack_subaccount_code) {
            $this->authorizePayoutChange($actor, $code, $subaccounts);
        }

        $sub->fill([
            'name' => $this->name,
            'slug' => Str::slug($this->slug),
            'paystack_subaccount_code' => $code,
        ])->save();

        $this->flashMessage = $sub->wasRecentlyCreated
            ? "Subdivision '{$this->name}' was created successfully."
            : "Subdivision '{$this->name}' was updated.";

        $this->closeModal();
    }

    /**
     * Where a zone's revenue settles is an owner decision, and a code Paystack rejects would fail every checkout.
     */
    private function authorizePayoutChange(User $actor, ?string $code, PaystackSubaccountResolver $subaccounts): void
    {
        if ($actor->subdivision_id !== null) {
            throw ValidationException::withMessages([
                'paystackSubaccountCode' => 'Only organisation-wide admins can change payout accounts.',
            ]);
        }

        $reason = $code === null ? null : $subaccounts->rejectionReason($subaccounts->inspect($code));

        if ($reason !== null) {
            throw ValidationException::withMessages(['paystackSubaccountCode' => $reason]);
        }
    }

    public function deleteSubdivision(int $id): void
    {
        $actor = $this->actor();
        $this->requireOrganizationWide($actor);
        $sub = $this->zones($actor)->whereKey($id)->first();

        if ($sub === null) {
            return;
        }

        $name = $sub->name;
        $sub->delete();

        $this->flashMessage = "Subdivision '{$name}' was deleted.";
    }

    /**
     * Zones the actor may see and edit: the whole organisation, or only their own zone when zone-bound.
     *
     * @return Builder<Subdivision>
     */
    private function zones(User $actor): Builder
    {
        return Subdivision::query()
            ->where('organization_id', $actor->organization_id)
            ->when($actor->subdivision_id, static fn(Builder $query, int $zoneId) => $query->whereKey($zoneId));
    }

    private function newZone(User $actor, int $orgId): Subdivision
    {
        $this->requireOrganizationWide($actor);

        return new Subdivision(['organization_id' => $orgId]);
    }

    /**
     * Creating or deleting a zone reshapes every other zone's attribution, so it is an organisation-wide decision.
     */
    private function requireOrganizationWide(User $actor): void
    {
        if ($actor->subdivision_id !== null) {
            abort(403, 'Only organization-wide admins can create or delete subdivisions.');
        }
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401);
        }

        if ($user->organization_id === null || !$user->isAdmin()) {
            abort(403, 'Only organization admins can manage subdivisions.');
        }

        return $user;
    }

    public function render(): View
    {
        $actor = $this->actor();
        $term = trim($this->search);

        $subdivisions = $this
            ->zones($actor)
            ->when($term !== '', static fn($query) => $query->where('name', 'like', "%{$term}%"))
            ->withCount(['attendees', 'orders'])
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.subdivision-manager', [
            'subdivisions' => $subdivisions,
            'actor' => $actor,
        ]);
    }
}
