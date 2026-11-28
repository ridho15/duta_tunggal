<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Akses bawaan checklist per peran = akses SEBELUM checklist dipasang (27 Nov 2026). Checklist hanya mempersempit:
 *  - Lihat (menu)  : menu samping yang tampil untuk peran itu (database/data/access_default_views.php).
 *  - Aksi lain     : semua (kecuali Data Master untuk non-manajer); aturan lama di controller/Policy tetap menentukan.
 * Peran yang tidak ada di data bawaan & belum punya checklist: semua halaman (kecuali Data Master), aturan lama menentukan.
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
            // Peran di luar daftar bawaan yang belum punya checklist (mis. dibuat langsung di database): tidak dipersempit,
            // aturan lama yang menentukan. Peran baru dari halaman Peran selalu menyimpan checklist sendiri.
            $canView = $views === null || in_array($pageKey, $views, true)
                || array_intersect($page['default_from'] ?? [], $views) !== [];
            foreach ($page['actions'] as $action => $def) {
                // Checklist hanya mempersempit (aturan lama tetap berlaku): Lihat = menu lama; aksi = semua (aksi lintas modul,
                // mis. Checker mengirim DO / membuat SJ, dulu diizinkan aturan lama berdasarkan nama peran).
                $granted = ($action !== 'view' || $canView)
                    && (! in_array($action, $page['manager_actions'] ?? [], true) || $masterAllowed);
                if ($granted) {
                    $keys[] = "{$pageKey}.{$action}";
                }
            }
        }

        // Peran yang boleh mencetak semua Voucher Penerimaan perlu melihat & mencetak di Terima Pembayaran
        if (in_array($role->name, config('access.receipt_voucher_all_roles', []), true)
            || in_array($role->base_role, config('access.receipt_voucher_all_roles', []), true)) {
            $keys = array_values(array_unique([...$keys, ...self::RECEIPT_VOUCHER_KEYS]));
        }

        return $keys;
    }

    public const RECEIPT_VOUCHER_KEYS = ['penjualan.receipts.view', 'penjualan.receipts.print', 'penjualan.receipts.voucher'];

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
