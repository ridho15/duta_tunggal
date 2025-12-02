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
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            $table->foreignId('warehouse_confirmation_warehouse_id')->nullable()->after('warehouse_confirmation_id');
            $table->foreign('warehouse_confirmation_warehouse_id', 'wci_wcw_id_fk')->references('id')->on('warehouse_confirmation_warehouses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_confirmation_warehouse_id']);
            $table->dropColumn('warehouse_confirmation_warehouse_id');
        });
    }
};
