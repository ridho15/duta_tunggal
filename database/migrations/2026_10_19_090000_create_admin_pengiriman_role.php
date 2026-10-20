<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spesifikasi UI/UX v1.1 Bagian 5 (Tahap 1): peran baru "Admin Pengiriman" (kode ADP).
     * Mengurus SO → DO → Surat Jalan → Invoice, kendaraan & driver. Tidak bisa menghapus data
     * dan tidak bisa mengonfirmasi DO (itu tugas gudang).
     */
    public const ROLE = 'Admin Pengiriman';

    public const PERMISSIONS = [
        'view any sales order', 'view sales order',
        'view any delivery order', 'view delivery order', 'create delivery order', 'update delivery order', 'request delivery order',
        'view any delivery order item', 'view delivery order item', 'create delivery order item', 'update delivery order item',
        'view any surat jalan', 'view surat jalan', 'create surat jalan', 'update surat jalan', 'request surat jalan',
        'view any invoice', 'view invoice', 'create invoice', 'update invoice',
        'view any return product', 'view return product', 'create return product', 'update return product',
        'view any return product item', 'view return product item', 'create return product item', 'update return product item',
        'view any vehicle', 'view vehicle', 'create vehicle', 'update vehicle',
        'view any driver', 'view driver', 'create driver', 'update driver',
        'view any delivery schedule', 'view delivery schedule', 'create delivery schedule', 'update delivery schedule', 'update status delivery schedule',
        'view any inventory stock', 'view inventory stock',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $role = DB::table('roles')->where('name', self::ROLE)->where('guard_name', 'web')->first();

        if (! $role) {
            $data = ['name' => self::ROLE, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('roles', 'description')) {
                $data['description'] = 'Admin pengiriman: membuat DO dari SO, Surat Jalan, invoice, serta mengelola kendaraan & driver.';
            }
            $roleId = DB::table('roles')->insertGetId($data);
        } else {
            $roleId = $role->id;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], []);
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        // Sengaja kosong: peran yang mungkin sudah dipakai user tidak dihapus otomatis.
    }
};
