<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses checklist (Peran & Hak Akses, keputusan Ko Handy 27 Nov 2026).
 *
 * Kunci akses: "{halaman}.{aksi}" dari config/access.php (mis. "penjualan.so.approve"). Disimpan sebagai permission Spatie
 * berawalan "akses:" pada PERAN. Per USER: tambah / cabut lewat tabel user_access_overrides (allowed = 1 tambah, 0 cabut).
 * Owner & Super Admin selalu penuh (tidak bisa dikurangi). Hanya mereka yang boleh mengatur peran & export/import.
 */
class Access
{
    public const PREFIX = 'akses:';

    /** @var array<int, array<string, bool>> */
    private static array $cache = [];

    private static ?array $routeIndex = null;

    // ------------------------------------------------------------------ katalog

    /** @return array<string, array> [section => [label, icon, pages => [pageKey => page]]] */
    public static function sections(): array
    {
        return config('access.sections', []);
    }

    /** @return array<string, array> [pageKey => page + section] */
    public static function pages(): array
    {
        $pages = [];
        foreach (self::sections() as $sectionKey => $section) {
            foreach ($section['pages'] as $pageKey => $page) {
                $pages[$pageKey] = $page + ['section' => $sectionKey];
            }
        }

        return $pages;
    }

    /** @return array<int, string> semua kunci akses "{halaman}.{aksi}" */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::pages() as $pageKey => $page) {
            foreach (array_keys($page['actions']) as $action) {
                $keys[] = "{$pageKey}.{$action}";
            }
        }

        return $keys;
    }

    public static function label(string $key): string
    {
        [$pageKey, $action] = self::split($key);
        $page = self::pages()[$pageKey] ?? null;

        return $page ? $page['label'] . ': ' . ($page['actions'][$action][0] ?? $action) : $key;
    }

    /** @return array{0: string, 1: string} */
    public static function split(string $key): array
    {
        $pos = strrpos($key, '.');

        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }

    /** Kunci akses yang dibutuhkan route ini (null = route tidak diatur checklist). "export" = khusus Owner/Super Admin. */
    public static function keyForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }
        self::$routeIndex ??= self::buildRouteIndex();
        $lastSegment = substr($routeName, strrpos($routeName, '.') !== false ? strrpos($routeName, '.') + 1 : 0);
        if (in_array($lastSegment, config('access.export_route_suffixes', []), true)
            || str_starts_with($lastSegment, 'export') || str_starts_with($lastSegment, 'import')) {
            return 'export';
        }
        foreach (config('access.shared_prefixes', []) as $shared) {
            if (str_starts_with($routeName, $shared . '.')) {
                return null;
            }
        }
        if (isset(self::$routeIndex['exact'][$routeName])) {
            return self::$routeIndex['exact'][$routeName];
        }
        // Akhiran route yang tidak terdaftar di halaman → ikut "Lihat" halaman (endpoint bantu: search, detail JSON, dll.)
        foreach (self::$routeIndex['prefixes'] as $prefix => $pageKeyOrKey) {
            if (str_starts_with($routeName, $prefix . '.')) {
                return $pageKeyOrKey;
            }
        }

        return null;
    }

    private static function buildRouteIndex(): array
    {
        $exact = [];
        $prefixes = [];
        foreach (self::pages() as $pageKey => $page) {
            foreach ($page['actions'] as $action => $def) {
                foreach ($def[1] ?? [] as $suffix) {
                    $exact[$page['prefix'] . '.' . $suffix] = "{$pageKey}.{$action}";
                }
            }
            foreach ($page['export_suffixes'] ?? [] as $suffix) {
                $exact[$page['prefix'] . '.' . $suffix] = 'export';
            }
            $prefixes[$page['prefix']] = "{$pageKey}.view";
            foreach ($page['extra_prefixes'] ?? [] as $prefix => $action) {
                if ($action === null) {
                    // akhiran route sama dengan halaman utama (mis. master.driver.create → master.kendaraan.create)
                    foreach ($page['actions'] as $a => $def) {
                        foreach ($def[1] ?? [] as $suffix) {
                            $exact[$prefix . '.' . $suffix] = "{$pageKey}.{$a}";
                        }
                    }
                    $prefixes[$prefix] = "{$pageKey}.view";
                } else {
                    $prefixes[$prefix] = "{$pageKey}.{$action}";
                }
            }
        }
        // Awalan terpanjang dicocokkan lebih dulu
        uksort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

        return ['exact' => $exact, 'prefixes' => $prefixes];
    }

    // ------------------------------------------------------------------ cek akses

    /** Request ini sudah lolos checklist (EnforceAccess) → cek peran lama di controller tidak perlu menolak lagi. */
    public static function grantedByChecklist(): bool
    {
        return app()->bound('request') && request()->attributes->has('access_granted');
    }

    public static function isSuper(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(config('access.super_roles', ['Owner', 'Super Admin']));
    }

    /** Boleh export/import Excel/CSV: hanya Owner / Super Admin. */
    public static function canExportImport(?User $user = null): bool
    {
        return self::isSuper($user ?? Auth::user());
    }

    /**
     * Voucher Penerimaan Kas/Bank (penerimaan & titip dana customer): wajib centang "Cetak Voucher Penerimaan";
     * pencatat (mis. kasir) mencetak miliknya, Finance Manager / Owner / Super Admin semua (keputusan Ko Handy 27 Nov 2026).
     */
    public static function canPrintReceiptVoucher(?User $user, $createdBy): bool
    {
        if (! $user || ! self::allows('penjualan.receipts.voucher', $user)) {
            return false;
        }
        if (self::isSuper($user) || $user->hasRole(config('access.receipt_voucher_all_roles', []))) {
            return true;
        }

        return $createdBy !== null && (int) $createdBy === (int) $user->getKey();
    }

    /** Boleh mengatur peran & hak akses: hanya Owner / Super Admin. */
    public static function canManageRoles(?User $user = null): bool
    {
        return self::isSuper($user ?? Auth::user());
    }

    public static function allows(string $key, ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return false;
        }
        if ($key === 'export') {
            return self::canExportImport($user);
        }
        if (self::isSuper($user) || self::unmanaged($user)) {
            return true;   // user tanpa peran: tombol tidak disembunyikan checklist, aturan lama di halaman yang menentukan
        }

        return self::grantedKeys($user)[$key] ?? false;
    }

    /** Boleh membuka route bernama ini (true bila route tidak diatur checklist). */
    public static function allowsRoute(string $routeName, ?User $user = null): bool
    {
        $key = self::keyForRoute($routeName);

        return $key === null || self::allows($key, $user);
    }

    /** User tanpa peran dan tanpa akses khusus: tidak diatur checklist (aturan lama per halaman berlaku). */
    public static function unmanaged(User $user): bool
    {
        return $user->roles->isEmpty() && self::overrides($user) === [];
    }

    /** Boleh salah satu halaman (untuk judul kelompok menu). */
    public static function allowsAny(array $keys, ?User $user = null): bool
    {
        foreach ($keys as $key) {
            if (self::allows($key, $user)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, bool> kunci yang dimiliki user = kunci peran ± override user */
    public static function grantedKeys(User $user): array
    {
        if (isset(self::$cache[$user->getKey()])) {
            return self::$cache[$user->getKey()];
        }

        $keys = array_fill_keys(self::roleKeysForUser($user), true);
        foreach (self::overrides($user) as $key => $allowed) {
            if ($allowed) {
                $keys[$key] = true;
            } else {
                unset($keys[$key]);
            }
        }

        return self::$cache[$user->getKey()] = $keys;
    }

    /** Penanda peran sudah punya checklist tersimpan (peran tanpa penanda memakai akses bawaan AccessDefaults). */
    public const CONFIGURED = 'akses:__diatur';

    /** @var array<int, array<int, string>> */
    private static array $defaultsCache = [];

    /** @return array<int, string> */
    public static function roleKeysForUser(User $user): array
    {
        $keys = [];
        foreach ($user->loadMissing('roles.permissions')->roles as $role) {
            $names = $role->permissions->pluck('name');
            if ($names->contains(self::CONFIGURED)) {
                foreach ($names as $name) {
                    if (str_starts_with($name, self::PREFIX) && $name !== self::CONFIGURED) {
                        $keys[] = substr($name, strlen(self::PREFIX));
                    }
                }
            } else {
                array_push($keys, ...(self::$defaultsCache[$role->id] ??= AccessDefaults::keysFor($role)));
            }
        }

        return array_values(array_unique($keys));
    }

    /** @return array<string, bool> [kunci => true tambah / false cabut] */
    public static function overrides(User $user): array
    {
        if (! Schema::hasTable('user_access_overrides')) {
            return [];
        }

        return DB::table('user_access_overrides')->where('user_id', $user->getKey())
            ->pluck('allowed', 'access_key')->map(fn ($v) => (bool) $v)->all();
    }

    /** @var array<string, array<int, string>>|null izin teknis lama => kunci akses */
    private static ?array $legacyIndex = null;

    private const POLICY_ACTIONS = [
        'viewAny' => 'view', 'view' => 'view', 'create' => 'create', 'update' => 'update',
        'delete' => 'delete', 'deleteAny' => 'delete', 'forceDelete' => 'delete', 'restore' => 'delete',
    ];

    /**
     * Dipanggil Gate::before. True bila:
     *  - kemampuan = izin teknis lama yang dipetakan ke kunci akses yang dimiliki user; atau
     *  - kemampuan Policy (viewAny/view/create/update/delete/approve/…) pada halaman route yang sedang dibuka dan aksinya dicentang.
     */
    public static function gateAllows(?User $user, string $ability): bool
    {
        if (! $user || self::isSuper($user) || self::unmanaged($user)) {
            return false;   // super & user tanpa peran ditangani aturan lama
        }
        if (self::$legacyIndex === null) {
            self::$legacyIndex = [];
            foreach (self::pages() as $pageKey => $page) {
                foreach ($page['actions'] as $action => $def) {
                    foreach ($def[2] ?? [] as $perm) {
                        self::$legacyIndex[$perm][] = "{$pageKey}.{$action}";
                    }
                }
            }
        }
        foreach (self::$legacyIndex[$ability] ?? [] as $key) {
            if (self::allows($key, $user)) {
                return true;
            }
        }

        $granted = self::grantedByChecklist() ? request()->attributes->get('access_granted') : null;
        if (is_string($granted) && $granted !== 'export') {
            [$pageKey] = self::split($granted);
            $action = self::POLICY_ACTIONS[$ability] ?? $ability;
            if (isset(self::pages()[$pageKey]['actions'][$action])) {
                return self::allows("{$pageKey}.{$action}", $user);
            }
        }

        return false;
    }

    public static function flush(): void
    {
        self::$cache = [];
        self::$defaultsCache = [];
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Izin teknis lama (Spatie) yang mewakili kunci akses ini; dipakai Gate::before agar Policy lama ikut lolos. */
    public static function legacyPermissionsFor(string $key): array
    {
        [$pageKey, $action] = self::split($key);

        return self::pages()[$pageKey]['actions'][$action][2] ?? [];
    }
}
