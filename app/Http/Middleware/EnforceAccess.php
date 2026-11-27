<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci server untuk checklist Peran & Hak Akses: route yang termasuk halaman/aksi yang TIDAK dicentang ditolak (403),
 * walaupun alamatnya diketik langsung. Yang dicentang tetap melewati aturan bisnis lama (Policy / peran per controller),
 * jadi checklist hanya mempersempit, tidak membuka hak yang dulu ditolak. Route di luar katalog tidak diubah perilakunya.
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

        // User tanpa peran & tanpa akses khusus: aturan lama per halaman yang berlaku (perilaku sebelum checklist); export tetap dicek
        if ($key !== 'export' && Access::unmanaged($user)) {
            return $next($request);
        }

        // Halaman detail / bantu (bukan daftar menu) sering dibuka dari modul lain: cukup punya salah satu centang halaman itu
        $allowed = str_ends_with($key, '.view') && ! str_ends_with($routeName, '.index')
            ? Access::allowsAnyOnPage(Access::split($key)[0], $user)
            : Access::allows($key, $user);

        if (! $allowed) {
            $what = $key === 'export' ? 'Export/Import Excel (khusus Owner / Super Admin)' : Access::label($key);
            $message = "Anda tidak memiliki akses: {$what}. Solusi: minta Owner / Super Admin mencentang akses ini di Pengaturan → Peran & Hak Akses.";
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            abort(403, $message);
        }

        return $next($request);
    }
}
