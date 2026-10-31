<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $this->resetValidation();
        $this->reset(['editingSubdivisionId', 'name', 'slug', 'paystackSubaccountCode']);
        $this->isModalOpen = true;
    }

    public function editSubdivision(int $id): void
    {
        $actor = $this->actor();
        $sub = Subdivision::query()->where('organization_id', $actor->organization_id)->whereKey($id)->first();

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

    public function save(): void
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
            'paystackSubaccountCode' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->editingSubdivisionId === null) {
            Subdivision::create([
                'organization_id' => $orgId,
                'name' => $this->name,
                'slug' => Str::slug($this->slug),
                'paystack_subaccount_code' => filled($this->paystackSubaccountCode) ? trim($this->paystackSubaccountCode) : null,
            ]);

            $this->flashMessage = "Subdivision '{$this->name}' was created successfully.";
        } else {
            $sub = Subdivision::query()->where('organization_id', $orgId)->whereKey($this->editingSubdivisionId)->first();
            if ($sub !== null) {
                $sub->update([
                    'name' => $this->name,
                    'slug' => Str::slug($this->slug),
                    'paystack_subaccount_code' => filled($this->paystackSubaccountCode) ? trim($this->paystackSubaccountCode) : null,
                ]);

                $this->flashMessage = "Subdivision '{$this->name}' was updated.";
            }
        }

        $this->closeModal();
    }

    public function deleteSubdivision(int $id): void
    {
        $actor = $this->actor();
        $sub = Subdivision::query()->where('organization_id', $actor->organization_id)->whereKey($id)->first();

        if ($sub === null) {
            return;
        }

        $name = $sub->name;
        $sub->delete();

        $this->flashMessage = "Subdivision '{$name}' was deleted.";
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

        $subdivisions = Subdivision::query()
            ->where('organization_id', $actor->organization_id)
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

