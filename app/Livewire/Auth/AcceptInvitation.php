<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * An invited organisation admin sets their password once, from the signed link in their invitation email.
 *
 * The route's signature proves the link was issued by us and has not expired. The account must still have no
 * password, so the link stops working as soon as it has been used.
 */
#[Layout('layouts.auth')]
#[Title('Accept invitation')]
class AcceptInvitation extends Component
{
    use PasswordValidationRules;

    #[Locked]
    public int $userId;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        abort_unless(self::isPending($user), 410, 'This invitation has already been used. Sign in instead.');

        $this->userId = $user->id;
    }

    public function accept(): void
    {
        $this->validate(['password' => $this->passwordRules()]);

        $user = User::query()->findOrFail($this->userId);

        // Re-checked here: the page may have been open in two tabs.
        abort_unless(self::isPending($user), 410, 'This invitation has already been used. Sign in instead.');

        $user->forceFill(['password' => $this->password, 'email_verified_at' => now()])->save();

        Auth::login($user);
        session()->regenerate();

        $this->redirect($user->staffHomeUrl() ?? route('dashboard'));
    }

    public function render(): View
    {
        $user = User::query()->with('organization')->findOrFail($this->userId);

        return view('livewire.auth.accept-invitation', ['user' => $user]);
    }

    private static function isPending(User $user): bool
    {
        return $user->organization_id !== null && ($user->password === null || $user->password === '');
    }
}
