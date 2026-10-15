<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ongkos kirim pada Sales Order (UAT 13 Okt 2026, B1). Nilai ini TIDAK bagian dari total_amount (jumlah barang):
     * ia mengalir ke DO pertama (additional_cost) lalu ke invoice sebagai biaya lain "delivery_cost".
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sale_orders', 'shipping_cost')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                $table->decimal('shipping_cost', 18, 2)->default(0)->after('total_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_orders', 'shipping_cost')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                $table->dropColumn('shipping_cost');
            });
        }
    }
};
