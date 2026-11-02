<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureSubdivisionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Services\Sms\Contracts\SmsGatewayInterface::class,
            \App\Services\Sms\SmsManager::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Re-run tenant/role checks on every Livewire update request, not only the initial page load.
        Livewire::addPersistentMiddleware([EnsureSubdivisionAccess::class]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Never bind to a local Vite dev server in production or serverless environments.
        if (app()->isProduction() || isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
            Vite::useHotFile(storage_path('framework/no_hot_file'));
        }

        DB::prohibitDestructiveCommands(app()->isProduction());

        // Outside production, lazy-loaded relations (N+1 queries) and silently discarded mass-assignment keys
        // throw, so the test suite catches them before they reach a request path.
        Model::preventLazyLoading(!app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(!app()->isProduction());

        Password::defaults(static fn(): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null);
    }
}
