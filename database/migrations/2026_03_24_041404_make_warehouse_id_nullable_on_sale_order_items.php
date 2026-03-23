<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // warehouse_id dibuat nullable untuk mendukung mode multi-gudang
        // (warehouseAllocations). Ketika allocations diisi, warehouse_id tidak wajib
        // karena SaleOrderObserver menggunakan tabel allocations sebagai prioritas.
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->integer('warehouse_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->integer('warehouse_id')->nullable(false)->change();
        });
    }
};
