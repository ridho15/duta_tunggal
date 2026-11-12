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

        if (! empty($allowedRoles) && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($allowedRoles)) {
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

        $roleNames = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->all()
            : [];

        $codes = [];
        foreach ((array) config('menu_access.role_groups', []) as $code => $roles) {
            if (array_intersect($roleNames, (array) $roles)) {
                $codes[] = $code;
            }
        }

        return static::$codeCache[$user] = $codes;
    }

    /** Dipakai di test / setelah role user berubah. */
    public static function flush(): void
    {
        static::$codeCache = null;
    }
}
