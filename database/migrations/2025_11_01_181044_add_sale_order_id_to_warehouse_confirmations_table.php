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
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_order_id')->nullable()->after('manufacturing_order_id');
            $table->foreign('sale_order_id')->references('id')->on('sale_orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            $table->dropForeign(['sale_order_id']);
            $table->dropColumn('sale_order_id');
        });
    }
};
