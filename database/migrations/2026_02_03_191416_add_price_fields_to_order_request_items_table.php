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
        Schema::table('order_request_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->after('quantity');
            $table->decimal('discount', 15, 2)->default(0)->after('unit_price');
            $table->decimal('tax', 15, 2)->default(0)->after('discount');
            $table->decimal('subtotal', 15, 2)->default(0)->after('tax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'discount', 'tax', 'subtotal']);
        });
    }
};
