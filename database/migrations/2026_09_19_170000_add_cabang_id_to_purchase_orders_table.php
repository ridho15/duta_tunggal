<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restore cabang_id to purchase_orders table to support header-level accounting branch lock to Pusat.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'cabang_id')) {
                $table->unsignedBigInteger('cabang_id')->nullable()->after('supplier_id');
                $table->foreign('cabang_id')->references('id')->on('cabangs')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
                $table->dropForeign(['cabang_id']);
                $table->dropColumn('cabang_id');
            }
        });
    }
};
