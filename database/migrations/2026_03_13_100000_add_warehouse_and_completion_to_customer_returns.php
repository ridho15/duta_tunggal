<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds:
     *  - warehouse_id       : warehouse where returned goods are physically received / stored
     *  - stock_restored_at  : timestamp set when inventory is restored (prevents double-processing)
     *  - completed_at       : timestamp set when status transitions to 'completed'
     */
    public function up(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('cabang_id');
            $table->timestamp('stock_restored_at')->nullable()->after('rejected_at');
            $table->timestamp('completed_at')->nullable()->after('stock_restored_at');

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'stock_restored_at', 'completed_at']);
        });
    }
};
