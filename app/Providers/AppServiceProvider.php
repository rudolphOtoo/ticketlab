<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\MailCategory;
use App\Http\Middleware\EnsureSubdivisionAccess;
use App\Services\Mail\BrevoApiTransport;
use App\Services\Mail\MailQuota;
use App\Services\Media\CloudinaryClient;
use App\Services\Media\SdkCloudinaryClient;
use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\SmsManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        $this->app->singleton(SmsGatewayInterface::class, SmsManager::class);
        $this->app->singleton(
            CloudinaryClient::class,
            static fn(): CloudinaryClient => new SdkCloudinaryClient(
                is_string(config('services.cloudinary.url')) ? config('services.cloudinary.url') : null,
            ),
        );

        // Development-only packages: never register in production or when not installed.
        if (!$this->app->isProduction()) {
            if (class_exists(\LaraMint\LaravelBrain\LaravelBrainServiceProvider::class)) {
                $this->app->register(\LaraMint\LaravelBrain\LaravelBrainServiceProvider::class);
            }
            if (class_exists(\LaraMint\LaravelStress\LaravelStressServiceProvider::class)) {
                $this->app->register(\LaraMint\LaravelStress\LaravelStressServiceProvider::class);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Re-run tenant/role checks on every Livewire update request, not only the initial page load.
        Livewire::addPersistentMiddleware([EnsureSubdivisionAccess::class]);

        // MAIL_MAILER=brevo: Brevo's HTTP API instead of its SMTP relay, so the account's xkeysib- API key is enough.
        Mail::extend('brevo', static fn(array $config): BrevoApiTransport => new BrevoApiTransport(
            apiKey: (string) ($config['key'] ?? ''),
            endpoint: (string) ($config['endpoint'] ?? 'https://api.brevo.com/v3/smtp/email'),
            timeout: (int) ($config['timeout'] ?? 8),
            forceIpv4: (bool) ($config['ipv4'] ?? true),
        ));

        // Emails Laravel sends itself (password resets, verification links) also count against the provider's daily
        // cap; they are never held back. Emails behind MailQuotaGate or TicketEmailer already took their slot.
        Event::listen(MessageSent::class, static function (): void {
            if (MailQuota::isInsideCountedSend()) {
                return;
            }

            try {
                app(MailQuota::class)->count(MailCategory::Other);
            } catch (\Throwable $exception) {
                Log::warning('Could not count a sent email against the daily cap.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Never bind to a local Vite dev server in production or serverless environments.
        if (app()->isProduction() || ($_SERVER['VERCEL'] ?? $_ENV['VERCEL'] ?? null) !== null) {
            Vite::useHotFile(storage_path('framework/no_hot_file'));
        }

        DB::prohibitDestructiveCommands(app()->isProduction());

        // Outside production, lazy-loaded relations (N+1 queries), silently discarded mass-assignment keys, and
        // reads of attributes a query never selected all throw, so tests catch them before a request path does.
        Model::shouldBeStrict(!app()->isProduction());

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
