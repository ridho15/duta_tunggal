<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UAT 13 Okt 2026, B2: Kasir dapat menyetujui Sales Order yang hartanya sesuai batas harga master produk.
     *  - beri peran Kasir izin melihat & menyetujui SO;
     *  - tambahkan Kasir ke peran penyetuju tingkat 1 (SO sampai Rp 10.000.000) — hanya bila aturan itu masih nilai awal.
     * Syarat harga sesuai batas ditegakkan di ApprovalControlService (bukan di data), jadi menambah peran di sini aman.
     */
    public function up(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
            $role = DB::table('roles')->where('name', 'Kasir')->where('guard_name', 'web')->first();
            if ($role) {
                foreach (['view any sales order', 'view sales order', 'response sales order'] as $name) {
                    $permission = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
                    if ($permission) {
                        DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permission->id, 'role_id' => $role->id], []);
                    }
                }
                app()['cache']->forget('spatie.permission.cache');
            }
        }

        if (Schema::hasTable('approval_rules')) {
            $rule = DB::table('approval_rules')->where('document_type', 'sale_order')->whereNull('above_amount')->first();
            if ($rule) {
                $roles = json_decode($rule->roles, true) ?: [];
                if (! in_array('Kasir', $roles, true)) {
                    $roles[] = 'Kasir';
                    DB::table('approval_rules')->where('id', $rule->id)->update([
                        'roles' => json_encode(array_values($roles)),
                        'approver_label' => 'Sales Manager / Kasir (harga sesuai batas) / Direktur',
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Sengaja kosong: izin/aturan yang sudah disesuaikan pengguna tidak dicabut otomatis.
    }
};
