<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Gudang yang sedang dikerjakan user di layar HP (checker, opname, picking list).
 * Sumber: kolom users.warehouse_id; bila kosong, pilihan user disimpan di sesi.
 */
class HpWarehouse
{
    public const SESSION_KEY = 'dt_hp_warehouse_id';

    /** Gudang (bukan karantina) yang boleh dipilih user. */
    public static function options(User $user): Collection
    {
        return Warehouse::query()
            ->where(fn ($q) => $q->whereNull('is_quarantine')->orWhere('is_quarantine', false))
            ->orderBy('name')
            ->get(['id', 'kode', 'name', 'cabang_id'])
            ->filter(fn (Warehouse $warehouse) => $user->canAccessCabang($warehouse->cabang_id))
            ->values();
    }

    public static function currentId(User $user): ?int
    {
        if ($user->warehouse_id) {
            return (int) $user->warehouse_id;
        }

        $selected = session(self::SESSION_KEY);
        if ($selected && static::options($user)->contains('id', (int) $selected)) {
            return (int) $selected;
        }

        $options = static::options($user);

        return $options->count() === 1 ? (int) $options->first()->id : null;
    }

    /** User yang terikat ke satu gudang tidak bisa berganti gudang. */
    public static function canSwitch(User $user): bool
    {
        return ! $user->warehouse_id;
    }

    public static function select(User $user, int $warehouseId): bool
    {
        if (! static::canSwitch($user) || ! static::options($user)->contains('id', $warehouseId)) {
            return false;
        }

        session([self::SESSION_KEY => $warehouseId]);

        return true;
    }

    public static function allows(User $user, ?int $warehouseId): bool
    {
        if (! $warehouseId) {
            return false;
        }

        if ($user->warehouse_id) {
            return (int) $user->warehouse_id === (int) $warehouseId;
        }

        return static::options($user)->contains('id', (int) $warehouseId);
    }

    public static function label(?int $warehouseId): ?string
    {
        if (! $warehouseId) {
            return null;
        }

        $warehouse = Warehouse::find($warehouseId);

        return $warehouse ? ($warehouse->kode ?: $warehouse->name) : null;
    }
}
