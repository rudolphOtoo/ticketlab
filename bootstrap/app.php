<?php

declare(strict_types=1);

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Http\Middleware\EnsureSubdivisionAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RemovePoweredByHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware([])->group(base_path('routes/maintenance.php'));
            Route::middleware('web')->group(base_path('routes/platform.php'));
            Route::middleware('web')->group(base_path('routes/ops.php'));
            Route::middleware('web')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        // Every response, the machine endpoints outside the web group included.
        $middleware->append(RemovePoweredByHeader::class);
        // Off unless CSP_MODE is "report-only" or "enforce" (config/csp.php).
        $middleware->web(append: [ContentSecurityPolicy::class, EnsureOrganizationIsActive::class]);
        $middleware->encryptCookies(except: [
            'theme',
        ]);
        $middleware->alias([
            'subdivision.access' => EnsureSubdivisionAccess::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn(Request $request) => $request->is('api/*') || $request->expectsJson());
    })
    ->create();

if (($_SERVER['VERCEL'] ?? $_ENV['VERCEL'] ?? null) !== null) {
    $app->useStoragePath('/tmp/storage');

    $directories = [
        '/tmp/storage/framework/cache',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/app/private/livewire-tmp',
        '/tmp/storage/app/public',
        '/tmp/storage/bootstrap/cache',
    ];
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            continue;
        }

        mkdir($dir, 0o755, true);
    }

    putenv('APP_PACKAGES_CACHE=/tmp/storage/bootstrap/cache/packages.php');
    putenv('APP_SERVICES_CACHE=/tmp/storage/bootstrap/cache/services.php');
    $_ENV['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
    $_ENV['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';
    $_SERVER['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
    $_SERVER['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';
}

return $app;
