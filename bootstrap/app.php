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
        $middleware->validateCsrfTokens(except: ['api/*']);
        // Profiler request: tidak melakukan apa pun kecuali PERF_PROFILE=true (config/perf.php).
        // Global (bukan grup web/api): rute panel Filament memakai daftar middleware panel sendiri, bukan grup web.
        $middleware->append(\App\Http\Middleware\ProfileRequest::class);
        // Lockdown Filament: alihkan seluruh akses /admin ke Dashboard Blade Native
        $middleware->append(\App\Http\Middleware\RedirectAdminToDashboard::class);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
    })
    ->withProviders([
        EventServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\FilamentServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\ClosedPeriodException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'CLOSED_ACCOUNTING_PERIOD',
                ], 422);
            }

            return back()->withErrors(['date' => $e->getMessage()])->with('error', $e->getMessage());
        });

        // Spesifikasi UI/UX 6.6: error server tampil sebagai pesan umum + kode, detail hanya di log.
        // Kode (mis. SJ-500-7KQ2) memuat `error_ref` yang sama dengan entri log.
        $exceptions->context(fn () => ['error_ref' => \App\Support\ErrorCode::ref()]);

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (config('app.debug')) {
                return null; // mode pengembangan: biarkan halaman error Laravel tampil
            }

            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if ($status < 500) {
                return null; // 403/404/419 memakai view resources/views/errors/{status}.blade.php
            }

            $code = \App\Support\ErrorCode::make($status, $request);
            $message = "Terjadi kesalahan sistem. Silakan hubungi admin (kode: {$code}).";

            if ($request->expectsJson() && ! $request->header('X-Livewire')) {
                return response()->json(['message' => $message, 'error_code' => $code], $status);
            }

            return response()
                ->view('errors.500', ['errorCode' => $code, 'status' => $status], $status)
                ->header('X-Error-Code', $code);
        });
    })->create();
