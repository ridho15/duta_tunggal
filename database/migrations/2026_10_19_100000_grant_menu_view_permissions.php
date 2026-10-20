<?php

use App\Support\MenuViewPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spesifikasi UI/UX v1.1 Bagian 5.2 (Tahap 1): beri izin MELIHAT (view any + view) agar
     * setiap peran bisa membuka semua menu di matriksnya. Izin yang sudah ada tidak dicabut,
     * izin membuat/mengubah/menyetujui tidak ditambah.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (MenuViewPermissions::permissionsByRole() as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->where('guard_name', 'web')
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], []);
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        // Sengaja kosong: izin yang mungkin sudah disesuaikan admin tidak dicabut otomatis.
    }
};
