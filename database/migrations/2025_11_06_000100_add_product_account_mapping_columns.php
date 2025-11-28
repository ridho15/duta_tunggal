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
            if (! Schema::hasColumn('products', 'sales_coa_id')) {
                $table->foreignId('sales_coa_id')->nullable()->after('inventory_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'sales_return_coa_id')) {
                $table->foreignId('sales_return_coa_id')->nullable()->after('sales_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'sales_discount_coa_id')) {
                $table->foreignId('sales_discount_coa_id')->nullable()->after('sales_return_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'goods_delivery_coa_id')) {
                $table->foreignId('goods_delivery_coa_id')->nullable()->after('sales_discount_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'cogs_coa_id')) {
                $table->foreignId('cogs_coa_id')->nullable()->after('goods_delivery_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'purchase_return_coa_id')) {
                $table->foreignId('purchase_return_coa_id')->nullable()->after('cogs_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'unbilled_purchase_coa_id')) {
                $table->foreignId('unbilled_purchase_coa_id')->nullable()->after('purchase_return_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'unbilled_purchase_coa_id',
                'purchase_return_coa_id',
                'cogs_coa_id',
                'goods_delivery_coa_id',
                'sales_discount_coa_id',
                'sales_return_coa_id',
                'sales_coa_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
