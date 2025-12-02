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
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('sale_order_item_id');
            $table->decimal('requested_qty', 15, 2)->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'requested_qty']);
        });
    }
};
