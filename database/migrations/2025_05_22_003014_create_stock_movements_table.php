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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('warehouse_id');
            $table->float('quantity')->default(0);
            $table->enum('type', ['purchase, sales, transfer_in, transfer_out, manufacture_in, manufacture_out, adjustment']);
            $table->string('reference_id')->nullable()->comment('ID Dokument terkait');
            $table->dateTime('date');
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
        Schema::dropIfExists('stock_movements');
    }
};
