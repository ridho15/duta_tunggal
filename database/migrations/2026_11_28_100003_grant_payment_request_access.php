<?php

use App\Support\Access;
use App\Support\AccessDefaults;
use App\Support\AccessManager;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Permintaan Bayar (28 Nov 2026): semua peran yang checklist-nya tersimpan boleh membuka & mengajukan.
 * Menyetujui / membayar tetap dibatasi aturan nominal & peran di ExpenseRequestService.
 */
return new class extends Migration
{
    public function up(): void
    {
        AccessDefaults::ensurePermissions();
        $keys = collect(Access::allKeys())->filter(fn ($k) => str_starts_with($k, 'keuangan.payment-request.'))->values()->all();
        foreach (Role::query()->get() as $role) {
            if (! $role->permissions()->where('name', Access::CONFIGURED)->exists()) {
                continue;
            }
            $current = AccessManager::roleKeys($role);
            $add = array_values(array_diff($keys, $current));
            if ($add !== []) {
                AccessManager::saveRoleKeys($role, [...$current, ...$add], 'update', 'Menu baru Permintaan Bayar');
            }
        }
        Access::flush();
    }

    public function down(): void {}
};
