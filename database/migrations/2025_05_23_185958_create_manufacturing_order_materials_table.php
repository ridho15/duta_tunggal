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
        Schema::create('manufacturing_order_materials', function (Blueprint $table) {
            $table->id();
            $table->integer('manufacturing_order_id');
            $table->integer('material_id');
            $table->integer('qty_required')->default(0);
            $table->integer('qty_used')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_order_materials');
    }
};
