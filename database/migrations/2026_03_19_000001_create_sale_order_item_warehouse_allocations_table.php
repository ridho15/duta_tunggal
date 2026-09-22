<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_order_item_warehouse_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_item_id')->constrained('sale_order_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('quantity', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sale_order_item_id', 'warehouse_id'], 'so_item_warehouse_alloc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_order_item_warehouse_allocations');
    }
};
