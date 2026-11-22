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
        // Kolom dpp_nilai_lain bisa sudah dibuat migrasi 2026_11_22_100002 (siklus pembelian): samakan definisinya saja.
        $exists = Schema::hasColumn('invoices', 'dpp_nilai_lain');
        Schema::table('invoices', function (Blueprint $table) use ($exists) {
            $table->decimal('tax', 18, 2)->default(0)->change();
            $column = $table->decimal('dpp_nilai_lain', 18, 2)->default(0);
            $exists ? $column->change() : $column->after('dpp');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('tax')->default(0)->change();
        });
    }
};
