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
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->integer('purchase_order_item_id')->nullable();
            $table->integer('rak_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            //
        });
    }
};
