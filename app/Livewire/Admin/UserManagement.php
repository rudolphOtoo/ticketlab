<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Team & Staff Management')]
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

    public string $role = 'staff';

    public string $provisionMode = 'password';

    public ?string $generatedInviteLink = null;

    public ?string $subdivisionId = null;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->actor();

        // Confirmation carried over from the member details page after a removal.
        $status = session('status');
        $this->flashMessage = is_string($status) ? $status : null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $actor = $this->actor();

        $this->resetValidation();
        $this->reset(['editingUserId', 'name', 'email', 'password', 'generatedInviteLink']);
        $this->role = 'staff';
        $this->provisionMode = 'password';
        $this->subdivisionId = $actor->subdivision_id === null ? null : (string) $actor->subdivision_id;
        $this->isModalOpen = true;
    }

    public function editUser(int $userId): void
    {
        $member = $this->member($this->actor(), $userId);

        $this->resetValidation();
        $this->editingUserId = $member->id;
        $this->name = $member->name;
        $this->email = $member->email;
        $this->password = '';
        $this->generatedInviteLink = null;
        $this->role = in_array($member->role, User::ASSIGNABLE_ROLES, true) ? $member->role : 'admin';
        $this->subdivisionId = $member->subdivision_id === null ? null : (string) $member->subdivision_id;
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingUserId = null;
        $this->password = '';
        $this->generatedInviteLink = null;
    }

    private function isInviteMode(): bool
    {
        return $this->editingUserId === null && $this->provisionMode === 'invite';
    }

    /**
     * Admins never change their own role, and subdivision-bound admins only provision inside their subdivision.
     */
    private function pinScopeTo(User $actor): void
    {
        if ($this->editingUserId === $actor->id) {
            $this->role = (string) $actor->role;
        }

        if ($actor->subdivision_id !== null) {
            $this->subdivisionId = (string) $actor->subdivision_id;
        }

        if ($this->subdivisionId === '') {
            $this->subdivisionId = null;
        }
    }

    /**
     * @return list<mixed>
     */
    private function passwordRules(): array
    {
        return match (true) {
            $this->isInviteMode() => ['nullable'],
            $this->editingUserId === null => ['required', 'string', Password::default()],
            default => ['nullable', 'string', Password::default()],
        };
    }

    /**
     * Invited members get an unusable password until they set their own; a typed password is trusted as verified.
     */
    private function applyCredentials(User $member): void
    {
        if ($this->isInviteMode()) {
            $member->password = Str::random(32);
            $member->forceFill(['email_verified_at' => null]);

            return;
        }

        if ($this->password === '') {
            return;
        }

        $member->password = $this->password;

        if (!$member->exists) {
            $member->forceFill(['email_verified_at' => now()]);
        }
    }

    public function save(): void
    {
        $actor = $this->actor();
        $organizationId = (int) $actor->organization_id;
        $editingSelf = $this->editingUserId === $actor->id;
        $this->pinScopeTo($actor);

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
                'password' => $this->passwordRules(),
                'role' => $editingSelf ? ['required', 'string'] : ['required', Rule::in(User::ASSIGNABLE_ROLES)],
                'subdivisionId' => [
                    'nullable',
                    'integer',
                    Rule::exists(Subdivision::class, 'id')->where('organization_id', $organizationId),
                ],
            ],
            [],
            ['subdivisionId' => 'subdivision'],
        );

        $member = DB::transaction(function () use ($actor, $organizationId): User {
            $member = $this->editingUserId === null
                ? new User()
                : $this->member($actor, $this->editingUserId, lock: true);

            $member->fill(['name' => $this->name, 'email' => $this->email]);
            $this->applyCredentials($member);

            // Privilege fields are never mass-assigned: membership goes through the sanctioned method.
            return $member->assignMembership(
                $this->role,
                $organizationId,
                $this->subdivisionId === null ? null : (int) $this->subdivisionId,
            );
        });

        if ($this->isInviteMode()) {
            $token = PasswordBroker::broker()->createToken($member);
            $this->generatedInviteLink = route('password.reset', ['token' => $token, 'email' => $member->email]);
            $this->flashMessage = "{$member->name} was invited. Copy the activation link below if email is offline.";

            return;
        }

        $this->flashMessage = $this->editingUserId === null
            ? "{$member->name} was added to your team with instant password login."
            : "{$member->name}'s access was updated.";

        $this->closeModal();
    }

    public function deleteUser(int $userId): void
    {
        $actor = $this->actor();

        if ($actor->id === $userId) {
            $this->flashMessage = 'You cannot delete your own account.';

            return;
        }

        $member = $this->member($actor, $userId);
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
        return User::manageableBy($actor);
    }

    /**
     * Resolve one member the actor may manage. Anything outside their organisation or zone, whether reached by a
     * tampered id or an account that moved zones mid-session, is refused outright rather than silently ignored.
     */
    private function member(User $actor, int $userId, bool $lock = false): User
    {
        $member = $this
            ->members($actor)
            ->whereKey($userId)
            ->when($lock, static fn(Builder $query) => $query->lockForUpdate())
            ->first();

        if (!$member instanceof User) {
            abort(403, 'You can only manage team members inside your organization and zone.');
        }

        return $member;
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
            'roles' => ['admin', 'staff'],
        ]);
    }
}
