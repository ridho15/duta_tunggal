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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->dateTime('delivery_date');
            $table->integer('driver_id');
            $table->integer('vehicle_id');
            $table->enum('status', ['draft', 'sent', 'confirmed', 'received', 'supplier', 'completed']); // draft -> sent -> confirmed -> received -> supplier -> completed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
