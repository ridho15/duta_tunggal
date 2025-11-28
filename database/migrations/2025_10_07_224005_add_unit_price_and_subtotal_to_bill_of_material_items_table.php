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
        Schema::table('bill_of_material_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->after('quantity')->comment('Harga per Satuan');
            $table->decimal('subtotal', 15, 2)->default(0)->after('unit_price')->comment('Subtotal (unit_price * quantity)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_of_material_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'subtotal']);
        });
    }
};
