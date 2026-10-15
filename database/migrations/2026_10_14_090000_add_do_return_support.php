<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UAT 13 Okt 2026, gelombang 3 (Retur by DO):
     *  - delivery_orders.returned_at            → DO ditandai "sudah diretur" (B14)
     *  - delivery_orders.replacement_for_delivery_order_id → DO pengganti (DO…C) menunjuk DO asal (B14, dipakai B20)
     *  - warehouses.is_quarantine               → gudang karantina/rusak: stoknya bukan stok jual (B16)
     *  - stock_movements.type += 'do_return'    → jenis mutasi sendiri untuk retur DO (B17)
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_orders', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('received_by_name');
            }
            if (! Schema::hasColumn('delivery_orders', 'replacement_for_delivery_order_id')) {
                $table->unsignedBigInteger('replacement_for_delivery_order_id')->nullable()->after('returned_at')->index();
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'is_quarantine')) {
                $table->boolean('is_quarantine')->default(false)->after('status');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN `type` ENUM('purchase_in','purchase_return','sales','customer_return','transfer_in','transfer_out','manufacture_in','manufacture_out','adjustment_in','adjustment_out','do_return') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && ! DB::table('stock_movements')->where('type', 'do_return')->exists()) {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN `type` ENUM('purchase_in','purchase_return','sales','customer_return','transfer_in','transfer_out','manufacture_in','manufacture_out','adjustment_in','adjustment_out') NOT NULL");
        }

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'is_quarantine')) {
                $table->dropColumn('is_quarantine');
            }
        });
        Schema::table('delivery_orders', function (Blueprint $table) {
            foreach (['replacement_for_delivery_order_id', 'returned_at'] as $column) {
                if (Schema::hasColumn('delivery_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
