<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom warehouse_id ke tabel purchase_orders.
     * Kolom ini berfungsi sebagai gudang tujuan penerimaan default
     * untuk seluruh item dalam PO. Nullable untuk kompatibilitas mundur.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')
                ->nullable()
                ->comment('Gudang tujuan penerimaan default untuk seluruh item PO');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
