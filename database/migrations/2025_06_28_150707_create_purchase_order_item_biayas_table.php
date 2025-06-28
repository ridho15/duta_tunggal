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
        Schema::create('purchase_order_biayas', function (Blueprint $table) {
            $table->id();
            $table->integer('purchase_order_id');
            $table->string('nama_biaya');
            $table->integer('currency_id');
            $table->bigInteger('total')->default(0);
            $table->boolean('untuk_pembelian')->default(false)->comment('Untuk pembelian');
            $table->boolean('masuk_invoice')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_biayas');
    }
};
