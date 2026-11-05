<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

$providers = [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];

if (class_exists(App\EnvKit\EnvKitDebugServiceProvider::class)) {
    $providers[] = App\EnvKit\EnvKitDebugServiceProvider::class;
}

return $providers;
