<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Staff & Usher Management')]
class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $isModalOpen = false;

    #[Locked]
    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'usher';

    public ?string $subdivisionId = null;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->actor();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $actor = $this->actor();

        $this->resetValidation();
        $this->reset(['editingUserId', 'name', 'email', 'password']);
        $this->role = 'usher';
        $this->subdivisionId = $actor->subdivision_id === null ? null : (string) $actor->subdivision_id;
        $this->isModalOpen = true;
    }

    public function editUser(int $userId): void
    {
        $member = $this->members($this->actor())->whereKey($userId)->first();

        if ($member === null) {
            return;
        }

        $this->resetValidation();
        $this->editingUserId = $member->id;
        $this->name = $member->name;
        $this->email = $member->email;
        $this->password = '';
        $this->role = in_array($member->role, User::ASSIGNABLE_ROLES, true) ? $member->role : 'admin';
        $this->subdivisionId = $member->subdivision_id === null ? null : (string) $member->subdivision_id;
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingUserId = null;
        $this->password = '';
    }

    public function save(): void
    {
        $actor = $this->actor();
        $organizationId = (int) $actor->organization_id;
        $editingSelf = $this->editingUserId === $actor->id;

        // Admins can never change their own role, so a stray request cannot lock them out or escalate them.
        if ($editingSelf) {
            $this->role = (string) $actor->role;
        }

        // Zone-bound admins can only provision staff inside their own zone.
        if ($actor->subdivision_id !== null) {
            $this->subdivisionId = (string) $actor->subdivision_id;
        }

        if ($this->subdivisionId === '') {
            $this->subdivisionId = null;
        }

        $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'lowercase',
                    'email',
                    'max:255',
                    Rule::unique(User::class, 'email')->ignore($this->editingUserId),
                ],
                'password' => [$this->editingUserId === null ? 'required' : 'nullable', 'string', Password::default()],
                'role' => $editingSelf ? ['required', 'string'] : ['required', Rule::in(User::ASSIGNABLE_ROLES)],
                'subdivisionId' => [
                    'nullable',
                    'integer',
                    Rule::exists(Subdivision::class, 'id')->where('organization_id', $organizationId),
                ],
            ],
            [],
            ['subdivisionId' => 'zone'],
        );

        $member = DB::transaction(function () use ($actor, $organizationId): ?User {
            $member = $this->editingUserId === null
                ? new User()
                : $this->members($actor)->whereKey($this->editingUserId)->lockForUpdate()->first();

            if ($member === null) {
                return null;
            }

            $member->fill(['name' => $this->name, 'email' => $this->email]);

            if ($this->password !== '') {
                $member->password = $this->password;
            }

            if (!$member->exists) {
                // Accounts provisioned by an organisation admin are vouched for, so they can sign in immediately.
                $member->forceFill(['email_verified_at' => now()]);
            }

            // Privilege fields are never mass-assigned: membership goes through the sanctioned method.
            return $member->assignMembership(
                $this->role,
                $organizationId,
                $this->subdivisionId === null ? null : (int) $this->subdivisionId,
            );
        });

        if ($member !== null) {
            $this->flashMessage = $this->editingUserId === null
                ? "{$member->name} was added to your team."
                : "{$member->name}'s access was updated.";
        }

        $this->closeModal();
    }

    public function deleteUser(int $userId): void
    {
        $actor = $this->actor();

        if ($actor->id === $userId) {
            $this->flashMessage = 'You cannot delete your own account.';

            return;
        }

        $member = $this->members($actor)->whereKey($userId)->first();

        if ($member === null) {
            return;
        }

        $name = $member->name;
        $member->delete();

        $this->flashMessage = "{$name} was removed from your team.";
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401);
        }

        // Only organisation admins may grant access; staff and ushers cannot manage the team.
        if ($user->organization_id === null || !$user->canManageTeam()) {
            abort(403, 'Only organization admins can manage staff accounts.');
        }

        return $user;
    }

    /**
     * Team members the actor may see and manage: same organisation, same zone when zone-bound, never platform admins.
     *
     * @return Builder<User>
     */
    private function members(User $actor): Builder
    {
        return User::query()
            ->where('organization_id', $actor->organization_id)
            ->whereNotIn('role', ['super_admin', 'superadmin'])
            ->when($actor->subdivision_id, static fn(Builder $query, int $zoneId) => $query->where(
                'subdivision_id',
                $zoneId,
            ));
    }

    public function render(): View
    {
        $actor = $this->actor();
        $term = trim($this->search);

        $members = $this
            ->members($actor)
            ->with('subdivision')
            ->when($term !== '', static fn(Builder $query) => $query->where(static function (Builder $inner) use (
                $term,
            ): void {
                $inner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        $subdivisions = Subdivision::query()
            ->where('organization_id', $actor->organization_id)
            ->when($actor->subdivision_id, static fn($query, int $zoneId) => $query->whereKey($zoneId))
            ->orderBy('name')
            ->get();

        return view('livewire.admin.user-management', [
            'members' => $members,
            'subdivisions' => $subdivisions,
            'actor' => $actor,
            'roles' => User::ASSIGNABLE_ROLES,
        ]);
    }
}
