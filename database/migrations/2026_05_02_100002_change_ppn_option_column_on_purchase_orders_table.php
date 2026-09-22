<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if column doesn't exist (will be removed in later migration)
        if (!Schema::hasColumn('purchase_orders', 'ppn_option')) {
            return;
        }

        // First change the column type to varchar to allow any value
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('ppn_option', 20)->default('eklusif')->change();
        });

        // Then backfill old 'standard' values → 'eklusif'
        DB::statement("UPDATE purchase_orders SET ppn_option = 'eklusif' WHERE ppn_option = 'standard'");
    }

    public function down(): void
    {
        if (!Schema::hasColumn('purchase_orders', 'ppn_option')) {
            return;
        }

        DB::statement("UPDATE purchase_orders SET ppn_option = 'standard' WHERE ppn_option IN ('eklusif','inklusif')");

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('ppn_option', ['standard', 'non_ppn'])->default('standard')->change();
        });
    }
};
