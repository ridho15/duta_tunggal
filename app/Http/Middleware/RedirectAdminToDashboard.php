<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminToDashboard
{
    /**
     * Intersep seluruh permintaan yang mengarah ke panel Filament (/admin atau /admin/*)
     * dan alihkan secara permanen/langsung ke Dashboard Blade Tailwind Native.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
