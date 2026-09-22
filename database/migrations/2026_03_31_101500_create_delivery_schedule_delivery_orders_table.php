<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_schedule_delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_schedule_id');
            $table->unsignedBigInteger('delivery_order_id');
            $table->timestamps();

            $table->foreign('delivery_schedule_id')->references('id')->on('delivery_schedules')->cascadeOnDelete();
            $table->foreign('delivery_order_id')->references('id')->on('delivery_orders')->cascadeOnDelete();
            $table->unique(['delivery_schedule_id', 'delivery_order_id'], 'ds_do_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_schedule_delivery_orders');
    }
};