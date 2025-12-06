<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('cabang_id')->nullable()->after('supplier_id');
            $table->foreign('cabang_id')->references('id')->on('cabangs');
        });

        // Set default cabang_id based on warehouse's cabang_id
        DB::statement('UPDATE order_requests SET cabang_id = (SELECT cabang_id FROM warehouses WHERE warehouses.id = order_requests.warehouse_id) WHERE cabang_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropColumn('cabang_id');
        });
    }
};
