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
        Schema::table('stock_opname_items', function (Blueprint $table) {
            // NULL now means "belum dihitung fisik" (not yet counted), distinct from 0 (counted, zero on hand).
            // Without this, startPhysicalCount() had no way to represent "uncounted" and defaulted physical_qty
            // to the system quantity, which let an opname reach "Setujui" without a single real count.
            $table->decimal('physical_qty', 15, 2)->nullable()->default(null)->change();
            $table->decimal('difference_qty', 15, 2)->nullable()->default(null)->change();
            $table->decimal('difference_value', 15, 2)->nullable()->default(null)->change();
            $table->decimal('total_value', 15, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('physical_qty', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('difference_qty', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('difference_value', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('total_value', 15, 2)->nullable(false)->default(0)->change();
        });
    }
};
