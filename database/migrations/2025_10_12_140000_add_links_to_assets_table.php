<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'product_id')) {
                $table->foreignId('product_id')->nullable()->constrained('products')->after('name');
            }
            if (!Schema::hasColumn('assets', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->after('product_id');
            }
            if (!Schema::hasColumn('assets', 'purchase_order_item_id')) {
                $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->after('purchase_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'purchase_order_item_id')) {
                $table->dropConstrainedForeignId('purchase_order_item_id');
            }
            if (Schema::hasColumn('assets', 'purchase_order_id')) {
                $table->dropConstrainedForeignId('purchase_order_id');
            }
            if (Schema::hasColumn('assets', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }
        });
    }
};
