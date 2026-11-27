<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci server untuk checklist Peran & Hak Akses: route yang termasuk halaman/aksi yang TIDAK dicentang ditolak (403),
 * walaupun alamatnya diketik langsung. Route yang dicentang ditandai `access_granted` sehingga pemeriksaan lama
 * (Policy / peran per controller) ikut lolos; checklist menjadi penentu (AuthServiceProvider Gate::before).
 * Route di luar katalog tidak diubah perilakunya.
 */
class EnforceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();
        if (! $user || ! $routeName) {
            return $next($request);
        }

        $key = Access::keyForRoute($routeName);
        if ($key === null) {
            return $next($request);
        }

        if (! Access::allows($key, $user)) {
            $what = $key === 'export' ? 'Export/Import Excel (khusus Owner / Super Admin)' : Access::label($key);
            $message = "Anda tidak memiliki akses: {$what}. Solusi: minta Owner / Super Admin mencentang akses ini di Pengaturan → Peran & Hak Akses.";
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            abort(403, $message);
        }

        $request->attributes->set('access_granted', $key);

        return $next($request);
    }
}
