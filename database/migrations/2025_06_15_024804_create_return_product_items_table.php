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
        Schema::create('return_product_items', function (Blueprint $table) {
            $table->id();
            $table->integer('return_product_id');
            $table->integer('from_item_id');
            $table->string('from_item_model_type');
            $table->integer('from_item_model_id');
            $table->integer('product_id');
            $table->integer('quantity')->default(0);
            $table->integer('rak_id');
            $table->enum('condition', ['good', 'damage', 'repair']);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Custom index dengan nama pendek
            $table->index(['from_item_model_type', 'from_item_model_id'], 'from_item_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_product_items');
    }
};
