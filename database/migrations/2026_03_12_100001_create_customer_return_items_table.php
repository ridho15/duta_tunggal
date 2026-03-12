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
        Schema::create('customer_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_return_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('invoice_item_id')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->text('problem_description')->nullable();
            $table->string('qc_result')->nullable(); // pass / fail
            $table->text('qc_notes')->nullable();
            $table->enum('decision', ['repair', 'replace', 'reject'])->nullable();
            $table->timestamps();

            $table->foreign('customer_return_id')->references('id')->on('customer_returns')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_return_items');
    }
};
