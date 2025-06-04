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
        Schema::create('delivery_order_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('delivery_order_id');
            $table->enum('status', ['draft', 'sent', 'received', 'supplier', 'completed']);
            $table->integer('confirmed_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_order_logs');
    }
};
