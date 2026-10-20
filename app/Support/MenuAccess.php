<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pembatasan menu & halaman per peran (Spesifikasi UI/UX Bagian 5) dan
 * pengaturan Modul Aktif. Sumber data: config/menu_access.php.
 */
class MenuAccess
{
    /** Cache kode peran per objek user (WeakMap: aman untuk test & antar-request). */
    protected static ?\WeakMap $codeCache = null;

    /** @var array<string, bool>|null */
    protected static ?array $moduleCache = null;

    /**
     * Apakah user yang login boleh melihat menu / membuka halaman class ini.
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

    /**
     * Nama modul (manufacturing, delivery_schedule, other_sales) untuk class ini, atau null.
     */
    public static function moduleFor(string $class): ?string
    {
        foreach ((array) config('menu_access.modules', []) as $module => $definition) {
            if (in_array($class, (array) ($definition['menus'] ?? []), true)) {
                return $module;
            }
        }

        return null;
    }

    public static function moduleEnabled(string $module): bool
    {
        if (static::$moduleCache === null) {
            static::$moduleCache = [];
        }

        if (array_key_exists($module, static::$moduleCache)) {
            return static::$moduleCache[$module];
        }

        $default = (bool) config("menu_access.modules.{$module}.default", false);

        try {
            $value = Schema::hasTable('app_settings')
                ? AppSetting::get("modules.{$module}", $default)
                : $default;
        } catch (Throwable) {
            $value = $default;
        }

        return static::$moduleCache[$module] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setModule(string $module, bool $enabled): void
    {
        $label = config("menu_access.modules.{$module}.label", $module);
        AppSetting::set("modules.{$module}", $enabled ? '1' : '0', "Modul Aktif: {$label}");
        static::$moduleCache = null;
    }

    /** Dipakai di test / setelah role user berubah. */
    public static function flush(): void
    {
        static::$codeCache = null;
        static::$moduleCache = null;
    }
}
