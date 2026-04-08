<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'manufacturing_labor_coa_id')) {
                    $table->foreignId('manufacturing_labor_coa_id')
                        ->nullable()
                        ->after('temporary_procurement_coa_id')
                        ->constrained('chart_of_accounts')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('products', 'manufacturing_overhead_coa_id')) {
                    $table->foreignId('manufacturing_overhead_coa_id')
                        ->nullable()
                        ->after('manufacturing_labor_coa_id')
                        ->constrained('chart_of_accounts')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('bill_of_materials')) {
            Schema::table('bill_of_materials', function (Blueprint $table) {
                if (! Schema::hasColumn('bill_of_materials', 'labor_coa_id')) {
                    $table->foreignId('labor_coa_id')
                        ->nullable()
                        ->after('work_in_progress_coa_id')
                        ->constrained('chart_of_accounts')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('bill_of_materials', 'overhead_coa_id')) {
                    $table->foreignId('overhead_coa_id')
                        ->nullable()
                        ->after('labor_coa_id')
                        ->constrained('chart_of_accounts')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bill_of_materials')) {
            Schema::table('bill_of_materials', function (Blueprint $table) {
                if (Schema::hasColumn('bill_of_materials', 'overhead_coa_id')) {
                    $table->dropConstrainedForeignId('overhead_coa_id');
                }

                if (Schema::hasColumn('bill_of_materials', 'labor_coa_id')) {
                    $table->dropConstrainedForeignId('labor_coa_id');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'manufacturing_overhead_coa_id')) {
                    $table->dropConstrainedForeignId('manufacturing_overhead_coa_id');
                }

                if (Schema::hasColumn('products', 'manufacturing_labor_coa_id')) {
                    $table->dropConstrainedForeignId('manufacturing_labor_coa_id');
                }
            });
        }
    }
};