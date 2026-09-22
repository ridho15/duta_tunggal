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
        // Drop foreign key constraints first using raw SQL with error handling
        try {
            DB::statement('ALTER TABLE `purchase_orders` DROP FOREIGN KEY `purchase_orders_cabang_id_foreign`');
        } catch (\Exception $e) {
            // Foreign key might not exist, continue
        }

        try {
            // Drop global tax_type from order_requests table
            if (Schema::hasColumn('order_requests', 'tax_type')) {
                Schema::table('order_requests', function (Blueprint $table) {
                    $table->dropColumn('tax_type');
                });
            }

            // Drop global fields from purchase_orders table
            if (Schema::hasColumn('purchase_orders', 'ppn_option')) {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->dropColumn('ppn_option');
                });
            }
            if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->dropColumn('cabang_id');
                });
            }
            if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
                Schema::table('purchase_orders', function (Blueprint $table) {
                    $table->dropColumn('warehouse_id');
                });
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Migration error: ' . $e->getMessage());
            throw $e;
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
