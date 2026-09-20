<?php

use App\Providers\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(\App\Http\Middleware\IncreaseMemoryLimit::class);
        $middleware->api(\App\Http\Middleware\IncreaseMemoryLimit::class);
        // Profiler request: tidak melakukan apa pun kecuali PERF_PROFILE=true (config/perf.php).
        // Global (bukan grup web/api): rute panel Filament memakai daftar middleware panel sendiri, bukan grup web.
        $middleware->append(\App\Http\Middleware\ProfileRequest::class);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
    })
    ->withProviders([
        EventServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\FilamentServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
