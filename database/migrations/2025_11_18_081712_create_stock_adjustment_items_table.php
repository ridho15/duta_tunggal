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
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('rak_id')->nullable()->constrained('raks')->onDelete('set null');
            $table->decimal('current_qty', 15, 2)->default(0); // qty saat ini
            $table->decimal('adjusted_qty', 15, 2)->default(0); // qty setelah adjustment
            $table->decimal('difference_qty', 15, 2)->default(0); // selisih
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('difference_value', 15, 2)->default(0); // nilai selisih
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
