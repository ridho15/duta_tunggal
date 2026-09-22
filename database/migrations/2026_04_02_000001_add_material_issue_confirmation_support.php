<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_confirmations')) {
            DB::statement("ALTER TABLE warehouse_confirmations MODIFY COLUMN confirmation_type ENUM('sales_order','manufacturing_order','delivery_order','material_issue') NOT NULL");
        }

        if (Schema::hasTable('warehouse_confirmation_items')) {
            DB::statement('ALTER TABLE warehouse_confirmation_items MODIFY COLUMN sale_order_item_id BIGINT UNSIGNED NULL');
        }

        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_confirmation_items', 'material_issue_item_id')) {
                $table->foreignId('material_issue_item_id')
                    ->nullable()
                    ->after('sale_order_item_id')
                    ->constrained('material_issue_items')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('warehouse_confirmation_items', 'product_id')) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('material_issue_item_id')
                    ->constrained('products')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_confirmation_items', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }

            if (Schema::hasColumn('warehouse_confirmation_items', 'material_issue_item_id')) {
                $table->dropConstrainedForeignId('material_issue_item_id');
            }
        });

        if (Schema::hasTable('warehouse_confirmation_items')) {
            DB::statement('ALTER TABLE warehouse_confirmation_items MODIFY COLUMN sale_order_item_id BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasTable('warehouse_confirmations')) {
            DB::statement("ALTER TABLE warehouse_confirmations MODIFY COLUMN confirmation_type ENUM('sales_order','manufacturing_order','delivery_order') NOT NULL");
        }
    }
};
