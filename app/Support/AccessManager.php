<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Menyimpan checklist Peran & Hak Akses + mencatat jejak perubahan (access_change_logs).
 * Hanya dipanggil oleh controller yang sudah memastikan pelakunya Owner / Super Admin.
 */
class AccessManager
{
    /** @return array<int, string> kunci akses checklist yang tersimpan di peran (tanpa awalan) */
    public static function roleKeys(Role $role): array
    {
        $names = $role->permissions()->pluck('name');
        if (! $names->contains(Access::CONFIGURED)) {
            return AccessDefaults::keysFor($role);
        }

        return $names->filter(fn ($n) => str_starts_with($n, Access::PREFIX) && $n !== Access::CONFIGURED)
            ->map(fn ($n) => substr($n, strlen(Access::PREFIX)))->values()->all();
    }

    /** Simpan checklist peran. Izin teknis lama (non "akses:") tidak disentuh. */
    public static function saveRoleKeys(Role $role, array $keys, string $action = 'update', ?string $note = null): array
    {
        $valid = array_flip(Access::allKeys());
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => isset($valid[$k]))));
        $before = self::roleKeys($role);

        AccessDefaults::ensurePermissions();
        DB::transaction(function () use ($role, $keys) {
            $legacy = $role->permissions()->where('name', 'not like', Access::PREFIX . '%')->pluck('name')->all();
            // Izin teknis lama milik centang yang aktif ikut diberikan, agar peran baru / duplikat lolos Policy lama.
            // Tidak pernah dicabut di sini: pembatasan dilakukan checklist (middleware EnforceAccess).
            $mapped = collect($keys)->flatMap(fn ($k) => Access::legacyPermissionsFor($k))->unique()->values();
            $existing = \Spatie\Permission\Models\Permission::query()->where('guard_name', 'web')
                ->whereIn('name', $mapped)->pluck('name')->all();
            $role->syncPermissions(array_values(array_unique([
                ...$legacy, ...$existing, Access::CONFIGURED, ...array_map(fn ($k) => Access::PREFIX . $k, $keys),
            ])));
        });
        Access::flush();

        $added = array_values(array_diff($keys, $before));
        $removed = array_values(array_diff($before, $keys));
        self::log('role', $role->id, $role->name, $action, $added, $removed, $note);

        return [$added, $removed];
    }

    /**
     * Simpan akses khusus user: $add = kunci yang ditambah di luar peran, $revoke = kunci peran yang dicabut.
     * Kunci yang sama dengan peran (tambah padahal sudah ada / cabut padahal tidak ada) dibuang otomatis.
     */
    public static function saveUserOverrides(User $user, array $add, array $revoke, string $action = 'update', ?string $note = null): array
    {
        $valid = array_flip(Access::allKeys());
        $roleKeys = array_flip(Access::roleKeysForUser($user));
        $add = array_values(array_unique(array_filter($add, fn ($k) => isset($valid[$k]) && ! isset($roleKeys[$k]))));
        $revoke = array_values(array_unique(array_filter($revoke, fn ($k) => isset($valid[$k]) && isset($roleKeys[$k]))));

        $before = Access::overrides($user);
        DB::transaction(function () use ($user, $add, $revoke) {
            DB::table('user_access_overrides')->where('user_id', $user->id)->delete();
            $now = now();
            $rows = [];
            foreach ($add as $k) {
                $rows[] = ['user_id' => $user->id, 'access_key' => $k, 'allowed' => 1, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach ($revoke as $k) {
                $rows[] = ['user_id' => $user->id, 'access_key' => $k, 'allowed' => 0, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($rows) {
                DB::table('user_access_overrides')->insert($rows);
            }
            // Izin teknis lama untuk akses yang DITAMBAH khusus user ini (izin langsung user hanya dikelola dari sini)
            $mapped = collect($add)->flatMap(fn ($k) => Access::legacyPermissionsFor($k))->unique()->values();
            $user->syncPermissions(\Spatie\Permission\Models\Permission::query()->where('guard_name', 'web')
                ->whereIn('name', $mapped)->pluck('name')->all());
        });
        Access::flush();

        $after = array_fill_keys($add, true) + array_fill_keys($revoke, false);
        $changed = fn ($a, $b) => array_keys(array_filter($a, fn ($v, $k) => ! array_key_exists($k, $b) || $b[$k] !== $v, ARRAY_FILTER_USE_BOTH));
        self::log('user', $user->id, $user->name, $action,
            array_map(fn ($k) => ($after[$k] ? '+' : '−') . $k, $changed($after, $before)),
            array_map(fn ($k) => ($before[$k] ? '+' : '−') . $k, $changed($before, $after)),
            $note);

        return [$add, $revoke];
    }

    public static function log(string $type, int $id, ?string $name, string $action, array $added = [], array $removed = [], ?string $note = null): void
    {
        DB::table('access_change_logs')->insert([
            'actor_id' => Auth::id(),
            'target_type' => $type,
            'target_id' => $id,
            'target_name' => $name,
            'action' => $action,
            'added' => json_encode(array_values($added)),
            'removed' => json_encode(array_values($removed)),
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /** @return \Illuminate\Support\Collection riwayat perubahan terbaru */
    public static function history(string $type, int $id, int $limit = 15)
    {
        return DB::table('access_change_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.actor_id')
            ->where('l.target_type', $type)->where('l.target_id', $id)
            ->orderByDesc('l.id')->limit($limit)
            ->get(['l.*', 'u.name as actor_name'])
            ->map(function ($row) {
                $row->added = json_decode($row->added ?? '[]', true) ?: [];
                $row->removed = json_decode($row->removed ?? '[]', true) ?: [];

                return $row;
            });
    }

    /** Data katalog untuk komponen checklist (Alpine). */
    public static function catalogForUi(): array
    {
        $out = [];
        foreach (Access::sections() as $sectionKey => $section) {
            $pages = [];
            foreach ($section['pages'] as $pageKey => $page) {
                $actions = [];
                foreach ($page['actions'] as $action => $def) {
                    $actions[] = ['key' => "{$pageKey}.{$action}", 'action' => $action, 'label' => $def[0]];
                }
                $pages[] = ['key' => $pageKey, 'label' => $page['label'], 'actions' => $actions];
            }
            $out[] = ['key' => $sectionKey, 'label' => $section['label'], 'note' => $section['note'] ?? null, 'pages' => $pages];
        }

        return $out;
    }
}
