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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('dpp');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->string('supplier_name')->nullable()->after('customer_phone');
            $table->string('supplier_phone')->nullable()->after('supplier_name');
            $table->json('delivery_orders')->nullable()->after('supplier_phone');
            $table->json('purchase_receipts')->nullable()->after('delivery_orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_phone', 
                'supplier_name',
                'supplier_phone',
                'delivery_orders',
                'purchase_receipts'
            ]);
        });
    }
};
