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
        // Add COA fields to material_issues table
        Schema::table('material_issues', function (Blueprint $table) {
            $table->foreignId('wip_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('inventory_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
        });

        // Add COA field to material_issue_items table
        Schema::table('material_issue_items', function (Blueprint $table) {
            $table->foreignId('inventory_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop COA fields from material_issue_items table
        Schema::table('material_issue_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_coa_id']);
            $table->dropColumn('inventory_coa_id');
        });

        // Drop COA fields from material_issues table
        Schema::table('material_issues', function (Blueprint $table) {
            $table->dropForeign(['wip_coa_id']);
            $table->dropForeign(['inventory_coa_id']);
            $table->dropColumn(['wip_coa_id', 'inventory_coa_id']);
        });
    }
};
