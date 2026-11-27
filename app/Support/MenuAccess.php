<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Pembatasan menu & halaman per peran (Spesifikasi UI/UX Bagian 5). Sumber data: config/menu_access.php.
 */
class MenuAccess
{
    public const FINANCE_READ_ROLES = ['Finance Manager', 'Accounting', 'Admin Keuangan', 'Auditor'];
    public const FINANCE_WRITE_ROLES = ['Finance Manager', 'Accounting', 'Admin Keuangan'];
    public const FINANCE_DELETE_ROLES = ['Finance Manager'];
    public const CASHBANK_READ_ROLES = ['Finance Manager', 'Accounting', 'Admin Keuangan', 'Auditor', 'Kasir'];

    /** Cache kode peran per objek user (WeakMap: aman untuk test & antar-request). */
    protected static ?\WeakMap $codeCache = null;

    /**
     * Otorisasi akses backend berbasis role Spatie atau permission granular (deny by default).
     *
     * @param  array<int, string>  $allowedRoles
     * @param  string|array<int, string>|null  $permissions
     */
    public static function authorizeRolesOrPermissions(
        array $allowedRoles,
        string|array|null $permissions = null,
        string $message = 'Anda tidak memiliki hak akses untuk modul ini.'
    ): void {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (static::isSuper($user)) {
            return;
        }

        // Route sudah lolos checklist Peran & Hak Akses → aturan peran lama tidak menghalangi (checklist yang menentukan)
        if (request()->attributes->get('access_granted')) {
            return;
        }

        if (! empty($allowedRoles) && array_intersect(static::roleNamesFor($user), $allowedRoles)) {
            return;
        }

        foreach ((array) $permissions as $permission) {
            if ($permission && $user->can($permission)) {
                return;
            }
        }

        abort(403, $message);
    }

    /**
     * Apakah user yang login boleh membuka menu ini. Kunci menu TIDAK boleh mengandung titik
     * (config() membacanya sebagai kunci bersarang dan menu dianggap tidak dibatasi).
     */
    public static function allows(string $class): bool
    {
        $allowed = config("menu_access.menus.{$class}");

        // Class tidak terdaftar → tidak dibatasi oleh peta ini.
        if ($allowed === null) {
            return true;
        }

        $user = Auth::user();

        // Belum login: biarkan middleware Authenticate yang menangani.
        if (! $user) {
            return true;
        }

        $codes = static::codesFor($user);

        // User tanpa peran yang dikenal peta ini (mis. peran baru buatan admin lewat menu
        // Pengguna & Hak Akses, atau user dengan permission langsung) tidak dibatasi di sini;
        // aksesnya tetap diatur permission/Policy. Tambahkan perannya ke role_groups untuk membatasi.
        if ($codes === []) {
            return true;
        }

        if (array_intersect($codes, (array) config('menu_access.super_codes', ['OWN']))) {
            return true;
        }

        return (bool) array_intersect($codes, (array) $allowed);
    }

    /** Owner / Super Admin / Admin (kode super di config). */
    public static function isSuper($user): bool
    {
        return $user && (bool) array_intersect(static::codesFor($user), (array) config('menu_access.super_codes', ['OWN']));
    }

    /**
     * Kode peran (OWN, SM, SLS, ...) milik user.
     *
     * @return array<int, string>
     */
    public static function codesFor($user): array
    {
        static::$codeCache ??= new \WeakMap();

        if (isset(static::$codeCache[$user])) {
            return static::$codeCache[$user];
        }

        $roleNames = static::roleNamesFor($user);

        $codes = [];
        foreach ((array) config('menu_access.role_groups', []) as $code => $roles) {
            if (array_intersect($roleNames, (array) $roles)) {
                $codes[] = $code;
            }
        }

        return static::$codeCache[$user] = $codes;
    }

    /**
     * Boleh melihat harga/nilai dokumen pembelian (PO, penerimaan)?
     * Keputusan Ko Handy #6: staf gudang murni TIDAK boleh melihat harga PO. Hanya Owner/Super Admin/Admin,
     * Purchasing, dan Finance/Akuntansi yang boleh. Gudang hanya melihat barang & kuantitas.
     */
    public static function canSeePurchasePrices($user): bool
    {
        if (! $user) {
            return false;
        }
        // Manajer gudang (Inventory Manager) termasuk "Manager" yang boleh melihat harga; staf gudang biasa tidak.
        $isInventoryManager = method_exists($user, 'hasRole') && $user->hasRole('Inventory Manager');

        return static::isSuper($user) || $isInventoryManager || (bool) array_intersect(static::codesFor($user), ['PUR', 'FIN']);
    }

    /**
     * Nama peran user + nama peran ASAL (roles.base_role) untuk peran hasil duplikat / peran baru dari template,
     * sehingga aturan lama berbasis nama peran (mis. "Accounting") ikut berlaku untuk "Akuntan 2".
     *
     * @return array<int, string>
     */
    public static function roleNamesFor($user): array
    {
        if (! method_exists($user, 'getRoleNames')) {
            return [];
        }
        $names = $user->getRoleNames()->all();
        foreach ($user->roles ?? [] as $role) {
            if (! empty($role->base_role) && ! in_array($role->base_role, config('access.super_roles', ['Owner', 'Super Admin']), true)) {
                $names[] = $role->base_role;
            }
        }

        return array_values(array_unique($names));
    }

    /** Dipakai di test / setelah role user berubah. */
    public static function flush(): void
    {
        static::$codeCache = null;
    }
}
