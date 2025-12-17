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
        // Add allocation basis to overhead items
        Schema::table('report_hpp_overhead_items', function (Blueprint $table) {
            $table->enum('allocation_basis', ['direct_labor', 'machine_hours', 'direct_material', 'production_volume', 'fixed_amount'])
                  ->default('direct_labor')
                  ->after('sort_order');
            $table->decimal('allocation_rate', 10, 4)->default(1.0)->after('allocation_basis');
        });

        // Standard cost table for products
        Schema::create('product_standard_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('standard_material_cost', 15, 2)->default(0);
            $table->decimal('standard_labor_cost', 15, 2)->default(0);
            $table->decimal('standard_overhead_cost', 15, 2)->default(0);
            $table->decimal('total_standard_cost', 15, 2)->default(0);
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['product_id', 'effective_date']);
        });

        // Production cost tracking
        Schema::create('production_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_produced');
            $table->decimal('actual_material_cost', 15, 2)->default(0);
            $table->decimal('actual_labor_cost', 15, 2)->default(0);
            $table->decimal('actual_overhead_cost', 15, 2)->default(0);
            $table->decimal('total_actual_cost', 15, 2)->default(0);
            $table->date('production_date');
            $table->timestamps();
        });

        // Cost variance tracking
        Schema::create('cost_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_cost_entry_id')->constrained()->onDelete('cascade');
            $table->enum('variance_type', ['material', 'labor', 'overhead', 'volume']);
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->decimal('variance_amount', 15, 2)->default(0);
            $table->decimal('variance_percentage', 8, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_variances');
        Schema::dropIfExists('production_cost_entries');
        Schema::dropIfExists('product_standard_costs');

        Schema::table('report_hpp_overhead_items', function (Blueprint $table) {
            $table->dropColumn(['allocation_basis', 'allocation_rate']);
        });
    }
};
