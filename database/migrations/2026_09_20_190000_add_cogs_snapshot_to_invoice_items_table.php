<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot HPP per baris invoice penjualan (Fase 6). Diisi pada saat jurnal HPP diposting dari
     * perhitungan yang SAMA dengan jurnal (qty × cost_price produk saat itu), sehingga laporan tidak
     * bergantung pada cost_price master yang berubah kemudian.
     *
     * cogs_source: 'jurnal' (snapshot saat posting), 'stok' (dari stock_movements DO), 'estimasi' (cost_price master
     * saat backfill — perlu ditinjau). NULL = belum ada snapshot (dihitung sebagai estimasi oleh laporan).
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'cost_price')) {
                $table->decimal('cost_price', 18, 4)->nullable()->after('total');
            }
            if (! Schema::hasColumn('invoice_items', 'cogs_amount')) {
                $table->decimal('cogs_amount', 18, 2)->nullable()->after('cost_price');
            }
            if (! Schema::hasColumn('invoice_items', 'cogs_source')) {
                $table->string('cogs_source', 20)->nullable()->after('cogs_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['cogs_source', 'cogs_amount', 'cost_price'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
