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
        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->integer('purchase_receipt_id');
            $table->integer('product_id');
            $table->float('qty_received')->default(0)->comment('total yang diterima');
            $table->float('qty_accepted')->default(0)->comment('total yang diambil');
            $table->float('qty_rejected')->default(0)->comment('total yang ditolak');
            $table->text('reason_rejected')->nullable();
            $table->string('photo_url');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
    }
};
