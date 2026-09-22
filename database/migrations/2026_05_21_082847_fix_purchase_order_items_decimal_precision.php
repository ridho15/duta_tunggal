<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standardize decimal precision for money/price columns.
 *
 * Policy (see docs/CONTEXT.md §8 & §9):
 *   - All monetary amounts → decimal(15, 2)  [standard transactions]
 *   - Journal entries debit/credit → decimal(20, 2)  [exception: must accommodate very large ledger values]
 *   - Asset values → decimal(20, 2)            [exception: fixed-asset book values]
 *   - Exchange rates → decimal(18, 8)           [exception: high precision for FX]
 *   - Original-currency amount → decimal(20, 4) [exception: intermediate calculation precision]
 *
 * This migration fixes:
 *   - purchase_order_items.discount  decimal(10,2) → decimal(15,2)
 *   - purchase_order_items.tax       decimal(10,2) → decimal(15,2)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('discount', 15, 2)->default(0)->change();
            $table->decimal('tax', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->change();
            $table->decimal('tax', 10, 2)->default(0)->change();
        });
    }
};
