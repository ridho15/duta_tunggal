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
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->string('so_number');
            $table->dateTime('order_date');
            $table->enum('status', ['draft', 'confirmed', 'received', 'cancelled']);
            $table->dateTime('delivery_date')->nullable();
            $table->bigInteger('total_amount')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
    }
};
