<?php

use App\Support\Access;
use App\Support\AccessDefaults;
use App\Support\AccessManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Akses bawaan versi final (27 Nov 2026): checklist hanya mempersempit, aturan lama tetap berlaku, sehingga bawaan =
 * semua aksi di halaman yang terlihat. Konfirmasi Stok DO pindah ke halaman Siapkan & Kirim (gudang).
 * Peran yang centangnya pernah diubah orang (riwayat dengan pelaku) tidak ditimpa; hanya ditambah Konfirmasi Stok DO.
 */
return new class extends Migration
{
    public function up(): void
    {
        AccessDefaults::ensurePermissions();
        foreach (Role::query()->get() as $role) {
            if (! $role->permissions()->where('name', Access::CONFIGURED)->exists()) {
                continue;
            }
            $editedByPerson = DB::table('access_change_logs')
                ->where('target_type', 'role')->where('target_id', $role->id)->whereNotNull('actor_id')->exists();
            $current = AccessManager::roleKeys($role);

            if (! $editedByPerson) {
                $new = AccessDefaults::keysFor($role);
                if (array_diff($new, $current) !== [] || array_diff($current, $new) !== []) {
                    AccessManager::saveRoleKeys($role, $new, 'update', 'Akses bawaan disegarkan (checklist hanya mempersempit aturan lama)');
                }
            } elseif (in_array('gudang.kiriman.process', $current, true) && ! in_array('gudang.kiriman.confirm', $current, true)) {
                AccessManager::saveRoleKeys($role, [...$current, 'gudang.kiriman.confirm'], 'update', 'Konfirmasi Stok DO pindah ke Siapkan & Kirim');
            }
        }
        Access::flush();
    }

    public function down(): void
    {
        // Tidak dikembalikan; atur lewat Pengaturan > Peran & Hak Akses bila perlu.
    }
};
