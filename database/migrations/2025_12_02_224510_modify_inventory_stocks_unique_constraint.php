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
        Schema::table('inventory_stocks', function (Blueprint $table) {
            // Drop the existing unique constraint
            $table->dropUnique('inventory_stocks_product_id_warehouse_id_unique');
            
            // Add new unique constraint that includes rak_id
            $table->unique(['product_id', 'warehouse_id', 'rak_id'], 'inventory_stocks_product_id_warehouse_id_rak_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('inventory_stocks_product_id_warehouse_id_rak_id_unique');
            
            // Restore the original unique constraint
            $table->unique(['product_id', 'warehouse_id'], 'inventory_stocks_product_id_warehouse_id_unique');
        });
    }
};
