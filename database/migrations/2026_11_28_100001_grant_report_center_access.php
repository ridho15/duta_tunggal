<?php

use App\Support\Access;
use App\Support\AccessDefaults;
use App\Support\AccessManager;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Pusat Laporan (28 Nov 2026): peran yang checklist-nya sudah tersimpan mendapat laporan baru sesuai halaman yang
 * dulu bisa mereka lihat (default_from di config/access.php). Nilai harga pokok hanya tingkat manajer.
 */
return new class extends Migration
{
    public function up(): void
    {
        AccessDefaults::ensurePermissions();
        $newPages = collect(Access::pages())->filter(fn ($p) => $p['section'] === 'laporan');

        foreach (Role::query()->get() as $role) {
            if (! $role->permissions()->where('name', Access::CONFIGURED)->exists()) {
                continue;
            }
            $current = AccessManager::roleKeys($role);
            $defaults = array_values(array_filter(AccessDefaults::keysFor($role),
                fn ($k) => $newPages->has(Access::split($k)[0])));
            // Hanya halaman yang "Lihat"-nya didapat dari halaman asal yang sudah dicentang peran ini
            $add = array_values(array_filter($defaults, function ($k) use ($current, $newPages) {
                $from = $newPages[Access::split($k)[0]]['default_from'] ?? [];

                return collect($from)->contains(fn ($page) => in_array("{$page}.view", $current, true));
            }));
            $add = array_values(array_diff($add, $current));
            if ($add !== []) {
                AccessManager::saveRoleKeys($role, [...$current, ...$add], 'update', 'Laporan baru di Pusat Laporan');
            }
        }
        Access::flush();
    }

    public function down(): void
    {
        // Tidak dikembalikan; atur lewat Pengaturan > Peran & Hak Akses bila perlu.
    }
};
