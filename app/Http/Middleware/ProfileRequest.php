<?php

namespace App\Http\Middleware;

use App\Support\Perf\ProfileRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profiler request (T1.6). TIDAK melakukan apa pun kecuali config('perf.enabled') = true.
 * Mencatat request lambat / banyak query ke channel log `perf` — lihat config/perf.php dan `php artisan perf:report`.
 */
class ProfileRequest
{
    public function __construct(private readonly ProfileRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('perf.enabled') && $this->sampled()) {
            $this->recorder->start();
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->recorder->isRunning()) {
            return;
        }

        try {
            $entry = $this->recorder->finish($request, $response->getStatusCode());

            if ($entry !== null) {
                Log::channel((string) config('perf.channel', 'perf'))->info('perf', $entry);
            }
        } catch (\Throwable $e) {
            // Profiler tidak boleh mengganggu aplikasi.
            report($e);
        }
    }

    private function sampled(): bool
    {
        $sample = (float) config('perf.sample', 1.0);

        return $sample >= 1.0 || (mt_rand() / mt_getrandmax()) < $sample;
    }
}
