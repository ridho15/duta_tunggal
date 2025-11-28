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
            $table->foreignId('finished_goods_coa_id')->nullable()->constrained('chart_of_accounts')->after('total_cost');
            $table->foreignId('work_in_progress_coa_id')->nullable()->constrained('chart_of_accounts')->after('finished_goods_coa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->dropForeign(['finished_goods_coa_id']);
            $table->dropForeign(['work_in_progress_coa_id']);
            $table->dropColumn(['finished_goods_coa_id', 'work_in_progress_coa_id']);
        });
    }
};
