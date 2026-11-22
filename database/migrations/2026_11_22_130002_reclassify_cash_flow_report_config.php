<?php

use Database\Seeders\Finance\FinanceReportConfigSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbarui konfigurasi Laporan Arus Kas yang SUDAH ADA di database (PSAK 2):
 *  - pembayaran hutang dagang (2110/2100) & biaya akrual dipindah dari Pendanaan ke Operasi;
 *  - Pendanaan hanya pinjaman (2140/2150/2160.03/2200/2220) dan ekuitas (3xxx);
 *  - Investasi = aset tetap (12xx); penyesuaian "include_assets" (dari tabel aset) dimatikan karena
 *    arus kas kini dihitung dari jurnal buku besar kas/bank.
 * Database tanpa konfigurasi (belum pernah di-seed) dibiarkan; jalankan seeder FinanceReportConfigSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_cash_flow_sections') || ! DB::table('report_cash_flow_sections')->exists()) {
            return;
        }

        DB::transaction(fn () => (new FinanceReportConfigSeeder())->seedCashFlow());
    }

    public function down(): void
    {
        // Konfigurasi lama mengklasifikasikan hutang dagang sebagai pendanaan (keliru menurut PSAK 2);
        // tidak dipulihkan. Ubah manual lewat tabel report_cash_flow_* bila memang diperlukan.
    }
};
