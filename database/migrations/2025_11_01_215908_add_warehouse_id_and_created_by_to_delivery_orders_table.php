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
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('vehicle_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('notes');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['warehouse_id', 'created_by']);
        });
    }
};
