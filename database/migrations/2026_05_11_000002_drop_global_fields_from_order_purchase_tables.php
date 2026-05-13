<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop global header fields: tax_type from order_requests, ppn_option/cabang_id/warehouse_id from purchase_orders.
     * All configuration now resides at item level (order_request_items, purchase_order_items).
     */
    public function up(): void
    {
        // Disable foreign key checks to allow column drops
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Drop global tax_type from order_requests table
            if (Schema::hasColumn('order_requests', 'tax_type')) {
                DB::statement('ALTER TABLE `order_requests` DROP COLUMN `tax_type`');
            }

            // Drop global fields from purchase_orders table
            if (Schema::hasColumn('purchase_orders', 'ppn_option')) {
                DB::statement('ALTER TABLE `purchase_orders` DROP COLUMN `ppn_option`');
            }
            if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
                DB::statement('ALTER TABLE `purchase_orders` DROP COLUMN `cabang_id`');
            }
            if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
                DB::statement('ALTER TABLE `purchase_orders` DROP COLUMN `warehouse_id`');
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore tax_type to order_requests
        Schema::table('order_requests', function (Blueprint $table) {
            $table->string('tax_type')->nullable()->after('note');
        });

        // Restore global fields to purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('ppn_option')->nullable()->after('is_import');
            $table->unsignedBigInteger('cabang_id')->nullable()->after('ppn_option');
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('is_asset');
        });
    }
};
