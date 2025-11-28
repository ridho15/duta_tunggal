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
        Schema::create('warehouse_confirmation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_confirmation_id');
            $table->unsignedBigInteger('sale_order_item_id');
            $table->decimal('confirmed_qty', 15, 2);
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('rak_id');
            $table->enum('status', ['confirmed', 'partial_confirmed', 'rejected']);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('warehouse_confirmation_id')->references('id')->on('warehouse_confirmations');
            $table->foreign('sale_order_item_id')->references('id')->on('sale_order_items');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('rak_id')->references('id')->on('raks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_confirmation_items');
    }
};
