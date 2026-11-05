<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inspect one team member's identity, access, activity and sign-in sessions, and manage their access.
 *
 * Every request re-resolves the member through User::manageableBy(), so an admin can only ever reach accounts
 * in their own organisation (and zone, when zone-bound); anything else is a 403, including on Livewire updates.
 */
#[Layout('layouts.admin')]
#[Title('Team Member')]
class UserDetails extends Component
{
    /** Roles that can be granted here, with the label each one carries in the interface. */
    public const array ROLE_LABELS = ['admin' => 'Admin', 'staff' => 'Staff', 'usher' => 'Gate usher'];

    #[Locked]
    public int $userId;

    public string $role = 'staff';

    public ?string $subdivisionId = null;

    public ?string $flashMessage = null;

    public function mount(User $user): void
    {
        $member = $this->member($user->id);

        $this->userId = $member->id;
        $this->role = array_key_exists((string) $member->role, self::ROLE_LABELS) ? (string) $member->role : 'admin';
        $this->subdivisionId = $member->subdivision_id === null ? null : (string) $member->subdivision_id;
    }

    public function updateAccess(): void
    {
        $actor = $this->actor();
        $member = $this->member();

        if (!$this->guard($actor, $member)) {
            return;
        }

        // Zone-bound admins can only place people inside their own zone.
        if ($actor->subdivision_id !== null) {
            $this->subdivisionId = (string) $actor->subdivision_id;
        }

        $this->validate(
            [
                'role' => ['required', Rule::in(array_keys(self::ROLE_LABELS))],
                'subdivisionId' => [
                    'nullable',
                    'integer',
                    Rule::exists(Subdivision::class, 'id')->where('organization_id', $member->organization_id),
                ],
            ],
            [],
            ['subdivisionId' => 'zone'],
        );

        DB::transaction(function () use ($member): void {
            $locked = User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            // Privilege fields are never mass-assigned: membership goes through the sanctioned method.
            $locked->assignMembership(
                $this->role,
                $locked->organization_id,
                $this->subdivisionId === null || $this->subdivisionId === '' ? null : (int) $this->subdivisionId,
            );
        });

        $this->flashMessage = "{$member->name}'s access was updated.";
    }

    public function sendPasswordReset(): void
    {
        $actor = $this->actor();
        $member = $this->member();

        if (!$this->guard($actor, $member)) {
            return;
        }

        $status = PasswordBroker::broker()->sendResetLink(['email' => $member->email]);

        $this->flashMessage = $status === PasswordBroker::RESET_LINK_SENT
            ? "A password reset link was emailed to {$member->email}."
            : 'A reset link was sent recently. Please wait a minute before sending another.';
    }

    /**
     * End every signed-in session, including "remember me" logins, so the member must sign in again.
     */
    public function signOutEverywhere(): void
    {
        $actor = $this->actor();
        $member = $this->member();

        if (!$this->guard($actor, $member)) {
            return;
        }

        $ended = $this->sessions($member)->delete();
        $member->setRememberToken(Str::random(60));
        $member->save();

        $this->flashMessage = "{$member->name} was signed out of {$ended} " . Str::plural('session', $ended) . '.';
    }

    public function removeMember(): void
    {
        $actor = $this->actor();
        $member = $this->member();

        if (!$this->guard($actor, $member)) {
            return;
        }

        $this->sessions($member)->delete();
        $member->delete();

        session()->flash('status', "{$member->name} was removed from your team.");

        $this->redirectRoute('admin.users', navigate: true);
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401);
        }

        if (!$user->isAdmin()) {
            abort(403, 'Only administrators can inspect team accounts.');
        }

        return $user;
    }

    private function member(?int $id = null): User
    {
        $member = User::manageableBy($this->actor())
            ->with(['organization', 'subdivision'])
            ->find($id ?? $this->userId);

        if (!$member instanceof User) {
            abort(403, 'You can only inspect accounts inside your organization and zone.');
        }

        return $member;
    }

    /**
     * Safety guards shared by every action; records why an action was refused and returns false.
     */
    private function guard(User $actor, User $member): bool
    {
        $refusal = match (true) {
            $member->id === $actor->id
                => 'You cannot change your own account here. Ask another admin, or use your Settings page.',
            $member->isSuperAdmin() => 'Platform administrator accounts are managed from the platform console.',
            $member->organization_id === null => 'This account is not part of an organization team.',
            default => null,
        };

        if ($refusal !== null) {
            $this->addError('action', $refusal);
        }

        return $refusal === null;
    }

    private function sessions(User $member): \Illuminate\Database\Query\Builder
    {
        return DB::table((string) config('session.table', 'sessions'))->where('user_id', $member->id);
    }

    /**
     * Activity records the actor may see: their organisation, narrowed to their zone when zone-bound.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    private static function withinScope(Builder $query, User $actor): Builder
    {
        return $query->when(!$actor->isSuperAdmin(), static fn(Builder $q) => $q->where(
            'organization_id',
            $actor->organization_id,
        ))->when($actor->subdivision_id, static fn(Builder $q, int $zoneId) => $q->where('subdivision_id', $zoneId));
    }

    /**
     * @return Collection<int, object{ip_address: string|null, user_agent: string|null, last_activity: int}>
     */
    private function recentSessions(User $member): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        /** @var Collection<int, object{ip_address: string|null, user_agent: string|null, last_activity: int}> */
        return $this
            ->sessions($member)
            ->orderByDesc('last_activity')
            ->limit(5)
            ->get(['ip_address', 'user_agent', 'last_activity']);
    }

    public function render(): View
    {
        $actor = $this->actor();
        $member = $this->member();

        $checkIns = self::withinScope(Attendee::query(), $actor)->where('checked_in_by', $member->id);
        $purchases = self::withinScope(Order::query(), $actor)->where('user_id', $member->id);

        return view('livewire.admin.user-details', [
            'actor' => $actor,
            'member' => $member,
            'isSelf' => $member->id === $actor->id,
            'roleLabels' => self::ROLE_LABELS,
            'zones' => Subdivision::query()
                ->where('organization_id', $member->organization_id)
                ->when($actor->subdivision_id, static fn(Builder $q, int $zoneId) => $q->whereKey($zoneId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'checkInCount' => (clone $checkIns)->count(),
            'recentCheckIns' => $checkIns
                ->with(['ticketType', 'subdivision'])
                ->latest('checked_in_at')
                ->limit(5)
                ->get(),
            'orderCount' => (clone $purchases)->count(),
            'passCount' => self::withinScope(Attendee::query(), $actor)
                ->whereIn('order_id', $purchases->select('id'))
                ->count(),
            'sessions' => $this->recentSessions($member),
            'sessionsTracked' => config('session.driver') === 'database',
        ]);
    }
}
