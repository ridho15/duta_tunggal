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
        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->decimal('labor_cost', 15, 2)->default(0)->after('quantity')->comment('Biaya Tenaga Kerja Langsung');
            $table->decimal('overhead_cost', 15, 2)->default(0)->after('labor_cost')->comment('Biaya Overhead Pabrik');
            $table->decimal('total_cost', 15, 2)->default(0)->after('overhead_cost')->comment('Total Biaya Produksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->dropColumn(['labor_cost', 'overhead_cost', 'total_cost']);
        });
    }
};
