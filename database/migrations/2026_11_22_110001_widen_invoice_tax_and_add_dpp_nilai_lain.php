<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit penjualan #7/#8: header PPN (`invoices.tax`) sebelumnya INT sehingga 9.077,92 tersimpan 9.078 dan Σ PPN header ≠ GL 2120.06.
     * Kolom diperlebar ke decimal(18,2) (nilai lama tetap; tidak ada konversi data). `dpp_nilai_lain` = 11/12 × DPP (PMK 131/2024)
     * untuk Faktur Pajak; PPN = 12% × DPP Nilai Lain (= 11% efektif).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tax', 18, 2)->default(0)->change();
            $table->decimal('dpp_nilai_lain', 18, 2)->default(0)->after('dpp');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('dpp_nilai_lain');
            $table->integer('tax')->default(0)->change();
        });
    }
};
