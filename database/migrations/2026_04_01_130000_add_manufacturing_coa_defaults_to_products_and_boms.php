<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('manufacturing_labor_coa_id')
                ->nullable()
                ->after('temporary_procurement_coa_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->foreignId('manufacturing_overhead_coa_id')
                ->nullable()
                ->after('manufacturing_labor_coa_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });

        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->foreignId('labor_coa_id')
                ->nullable()
                ->after('work_in_progress_coa_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->foreignId('overhead_coa_id')
                ->nullable()
                ->after('labor_coa_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overhead_coa_id');
            $table->dropConstrainedForeignId('labor_coa_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manufacturing_overhead_coa_id');
            $table->dropConstrainedForeignId('manufacturing_labor_coa_id');
        });
    }
};