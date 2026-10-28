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
        // Pengecualian: Layar HP khusus operasional (Opname HP, Cek Stok HP, Kiriman HP, Retur DO HP) & Livewire
        if ($request->is([
            'admin/opname-hp*',
            'admin/cek-stok-hp*',
            'admin/kiriman*',
            'admin/retur-do*',
            'livewire/*',
        ]) || $request->header('X-Livewire')) {
            return $next($request);
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
