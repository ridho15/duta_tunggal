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
        Schema::create('warehouse_confirmations', function (Blueprint $table) {
            $table->id();
            $table->integer('manufacturing_order_id');
            $table->enum('status', ['Confirmed', 'Rejected', 'Request'])->default('Confirmed');
            $table->text('note')->nullable();
            $table->integer('confirmed_by');
            $table->dateTime('confirmed_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_confirmations');
    }
};
