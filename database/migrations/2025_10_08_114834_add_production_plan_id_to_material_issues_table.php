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
        Schema::table('material_issues', function (Blueprint $table) {
            $table->foreignId('production_plan_id')->nullable()->after('id')->constrained('production_plans')->nullOnDelete();
            $table->foreignId('manufacturing_order_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_issues', function (Blueprint $table) {
            $table->dropForeign(['production_plan_id']);
            $table->dropColumn('production_plan_id');
            $table->foreignId('manufacturing_order_id')->nullable(false)->change();
        });
    }
};
