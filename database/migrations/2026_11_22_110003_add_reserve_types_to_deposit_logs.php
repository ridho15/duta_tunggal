<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Audit penjualan #3: verifikasi kasir COD hanya MENCADANGKAN saldo titip untuk SO (tanpa jurnal; saldo deposit tidak berubah).
     * `reserve` = dicadangkan untuk SO; `release` = cadangan dilepas (SO batal, atau dipakai melunasi invoice SO tersebut).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE deposit_logs MODIFY `type` ENUM('create','use','add','return','cancel','edit','reserve','release') NOT NULL");
    }

    public function down(): void
    {
        if (DB::table('deposit_logs')->whereIn('type', ['reserve', 'release'])->exists()) {
            throw new RuntimeException('Rollback ditolak: deposit_logs masih memuat baris reserve/release (cadangan saldo titip COD). Lepas/arsipkan baris tersebut lebih dulu.');
        }

        DB::statement("ALTER TABLE deposit_logs MODIFY `type` ENUM('create','use','add','return','cancel','edit') NOT NULL");
    }
};
