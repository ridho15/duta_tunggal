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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_raw_material')->default(false)->after('is_manufacture');
            $table->foreignId('inventory_coa_id')->nullable()->after('is_raw_material')->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['inventory_coa_id']);
            $table->dropColumn(['is_raw_material', 'inventory_coa_id']);
        });
    }
};
