<?php

use App\Models\ChartOfAccount;
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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'original_unit_price')) {
                $table->decimal('original_unit_price', 20, 10)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('purchase_order_items', 'price_change_reason')) {
                $table->text('price_change_reason')->nullable()->after('original_unit_price');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'po_price')) {
                $table->decimal('po_price', 20, 10)->nullable()->after('price');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'price_variance_amount')) {
                $table->decimal('price_variance_amount', 18, 2)->default(0)->after('total');
            }
        });

        // Ensure Chart of Account for Purchase Price Variance (5160) exists
        if (! ChartOfAccount::where('code', '5160')->exists()) {
            $parent = ChartOfAccount::where('code', '5000')->first();
            ChartOfAccount::create([
                'code' => '5160',
                'name' => 'Selisih Pembelian',
                'type' => 'Expense',
                'parent_id' => $parent?->id ?? 50,
                'is_active' => true,
                'is_current' => false,
                'opening_balance' => 0,
                'debit' => 0,
                'credit' => 0,
                'ending_balance' => 0,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'price_change_reason')) {
                $table->dropColumn('price_change_reason');
            }
            if (Schema::hasColumn('purchase_order_items', 'original_unit_price')) {
                $table->dropColumn('original_unit_price');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'po_price')) {
                $table->dropColumn('po_price');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'price_variance_amount')) {
                $table->dropColumn('price_variance_amount');
            }
        });
    }
};
