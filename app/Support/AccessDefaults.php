<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Akses bawaan checklist per peran = akses SEBELUM checklist dipasang (27 Nov 2026):
 *  - Lihat (menu)  : menu samping yang tampil untuk peran itu (database/data/access_default_views.php).
 *  - Aksi lain     : hanya pada halaman yang terlihat, bila peran punya salah satu izin teknis lama aksi itu,
 *                    atau halaman tsb dulu meloloskan peran tanpa izin teknis (bypass) / aksi tanpa izin teknis.
 * Peran yang tidak ada di data bawaan: Lihat dari izin teknis "view" saja.
 */
class AccessDefaults
{
    /** Buat permission "akses:*" untuk semua kunci katalog (aman diulang). */
    public static function ensurePermissions(): void
    {
        $existing = Permission::query()->where('name', 'like', Access::PREFIX . '%')->pluck('name')->flip();
        foreach ([...array_map(fn ($k) => Access::PREFIX . $k, Access::allKeys()), Access::CONFIGURED] as $name) {
            if (! isset($existing[$name])) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
        }
    }

    /** @return array<int, string> kunci akses bawaan untuk peran ini */
    public static function keysFor(Role $role): array
    {
        $views = (require database_path('data/access_default_views.php'))[$role->name] ?? null;
        $legacy = $role->permissions()->pluck('name')->flip();
        $keys = [];
        $masterAllowed = self::mayHoldMasterData($role);

        foreach (Access::pages() as $pageKey => $page) {
            if ($page['section'] === 'master' && ! $masterAllowed) {
                continue;
            }
            $viewPerms = $page['actions']['view'][2] ?? [];
            $canView = $views !== null
                ? in_array($pageKey, $views, true)
                : ($viewPerms !== [] && collect($viewPerms)->contains(fn ($p) => isset($legacy[$p])));
            if (! $canView) {
                continue;
            }

            foreach ($page['actions'] as $action => $def) {
                $perms = $def[2] ?? [];
                $granted = $action === 'view'
                    || ! empty($page['bypass'])
                    || $perms === []
                    || collect($perms)->contains(fn ($p) => isset($legacy[$p]));
                if ($granted) {
                    $keys[] = "{$pageKey}.{$action}";
                }
            }
        }

        return $keys;
    }

    /** Data Master hanya untuk peran tingkat manajer (config access.master_data_roles); peran duplikat dinilai dari peran asalnya. */
    public static function mayHoldMasterData(Role $role): bool
    {
        $allowed = [...config('access.super_roles', []), ...config('access.master_data_roles', [])];

        return in_array($role->name, $allowed, true) || in_array($role->base_role, $allowed, true);
    }

    /** Simpan akses bawaan sebagai checklist peran (hanya peran yang belum diatur). */
    public static function installFor(Role $role): void
    {
        if ($role->permissions()->where('name', Access::CONFIGURED)->exists()) {
            return;
        }
        $role->givePermissionTo([Access::CONFIGURED, ...array_map(fn ($k) => Access::PREFIX . $k, self::keysFor($role))]);
    }

    public static function installAll(): void
    {
        self::ensurePermissions();
        foreach (Role::query()->get() as $role) {
            self::installFor($role);
        }
        Access::flush();
    }
}
