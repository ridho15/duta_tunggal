<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PMK 131/2024: Faktur Pajak PPN 12% atas barang non-mewah memakai DPP Nilai Lain = 11/12 × harga (PPN efektif 11%).
     * Disimpan terpisah agar `dpp` (DPP harga) dan `ppn_rate` (tarif efektif 11%) yang dipakai jurnal & retur tidak berubah.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'dpp_nilai_lain')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->decimal('dpp_nilai_lain', 18, 2)->nullable()->after('dpp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'dpp_nilai_lain')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('dpp_nilai_lain');
            });
        }
    }
};
