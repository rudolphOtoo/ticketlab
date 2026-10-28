<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(static fn() => view('pages::auth.login'));
        Fortify::verifyEmailView(static fn() => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(static fn() => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(static fn() => view('pages::auth.confirm-password'));
        Fortify::registerView(static fn() => view('pages::auth.register'));
        Fortify::resetPasswordView(static fn() => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(static fn() => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', static fn(Request $request): Limit => Limit::perMinute(
            5,
        )->by((string) $request->session()->get('login.id')));

        RateLimiter::for('login', static function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input(Fortify::username())) . '|' . (string) $request->ip(),
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', static function (Request $request) {
            $credentialId = $request->input('credential.id');
            $bucket = is_string($credentialId) && $credentialId !== '' ? $credentialId : $request->session()->getId();

            return Limit::perMinute(10)->by($bucket . '|' . (string) $request->ip());
        });
    }
}
