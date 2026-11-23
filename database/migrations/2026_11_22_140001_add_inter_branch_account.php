<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan 22 Nov 2026: mutasi stok antar cabang dijurnal per cabang pada nilai HPP lewat akun perantara
 * "Rekening Antar Cabang" (1180.02, di bawah 1180 Aktiva Lancar Lainnya). Akun dibuat bila belum ada; akun yang
 * sudah ada (mis. dibuat manual) tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('chart_of_accounts')->where('code', '1180.02')->exists()) {
            return;
        }

        $parentId = DB::table('chart_of_accounts')->where('code', '1180')->value('id');

        DB::table('chart_of_accounts')->insert([
            'code' => '1180.02',
            'name' => 'REKENING ANTAR CABANG',
            'type' => 'Asset',
            'parent_id' => $parentId,
            'is_active' => true,
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Akun hanya dihapus bila belum pernah dijurnal (data keuangan tidak boleh hilang).
        $id = DB::table('chart_of_accounts')->where('code', '1180.02')->value('id');
        if ($id && ! DB::table('journal_entries')->where('coa_id', $id)->exists()) {
            DB::table('chart_of_accounts')->where('id', $id)->delete();
        }
    }
};
