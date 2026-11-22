<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit penjualan #5: biaya per unit yang dipakai jurnal Barang Terkirim (Dr 1140.20 / Cr Persediaan) saat DO berangkat.
     * Invoice melepas 1140.20 ke HPP dengan nilai yang SAMA, sehingga perubahan HPP rata-rata di antaranya tidak meninggalkan saldo.
     * NULL = DO lama sebelum kolom ini ada (fallback ke cost_price produk, perilaku lama).
     */
    public function up(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
