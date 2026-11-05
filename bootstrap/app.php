<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSubdivisionAccess;
use App\Http\Middleware\EnsureSuperAdmin;
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
            Route::middleware('web')->group(base_path('routes/ops.php'));
            Route::middleware('web')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
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

        // Shield Livewire from uncaught Flysystem metadata errors when temporary files are lost across serverless instances.
        $exceptions->render(function (\League\Flysystem\UnableToRetrieveMetadata $e, Request $request) {
            if ($request->hasHeader('x-livewire')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'banner' => 'The uploaded temporary file could not be retrieved from this serverless instance. Please re-select the image or paste a direct image URL.',
                ]);
            }
        });
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
