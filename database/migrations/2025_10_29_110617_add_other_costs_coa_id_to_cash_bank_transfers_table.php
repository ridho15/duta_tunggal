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
        Schema::table('cash_bank_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('other_costs_coa_id')->nullable()->after('other_costs');
            $table->foreign('other_costs_coa_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->onDelete('set null');
            
            $table->index('other_costs_coa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_bank_transfers', function (Blueprint $table) {
            $table->dropForeign(['other_costs_coa_id']);
            $table->dropIndex(['other_costs_coa_id']);
            $table->dropColumn('other_costs_coa_id');
        });
    }
};
