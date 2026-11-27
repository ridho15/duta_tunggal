<?php

use App\Support\Access;
use App\Support\AccessDefaults;
use App\Support\AccessManager;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Data Master hanya untuk Owner, Super Admin, Admin, Manager & Finance Manager (keputusan Ko Handy 27 Nov 2026).
 * Centang Data Master dilepas dari peran lain yang checklist-nya sudah tersimpan; tercatat di riwayat akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        $masterKeys = collect(Access::allKeys())
            ->filter(fn ($k) => (Access::pages()[Access::split($k)[0]]['section'] ?? null) === 'master')
            ->values()->all();

        foreach (Role::query()->get() as $role) {
            if (AccessDefaults::mayHoldMasterData($role)
                || ! $role->permissions()->where('name', Access::CONFIGURED)->exists()) {
                continue;   // peran tanpa checklist tersimpan sudah memakai aturan bawaan baru
            }
            $keys = AccessManager::roleKeys($role);
            if (array_intersect($keys, $masterKeys) === []) {
                continue;
            }
            AccessManager::saveRoleKeys($role, array_values(array_diff($keys, $masterKeys)), 'update',
                'Data Master khusus tingkat manajer (keputusan Ko Handy 27 Nov 2026)');
        }
        Access::flush();
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan centang: atur ulang lewat Pengaturan > Peran & Hak Akses bila perlu.
    }
};
