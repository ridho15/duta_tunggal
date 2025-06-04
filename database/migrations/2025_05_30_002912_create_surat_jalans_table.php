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
        Schema::create('surat_jalans', function (Blueprint $table) {
            $table->id();
            $table->integer('delivery_order_id');
            $table->string('sj_number')->comment('Nomor surat jalan');
            $table->dateTime('issued_at')->comment('Tanggal surat jalan dibuat');
            $table->integer('signed_by');
            $table->boolean('status')->comment('terbit / tidak');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_jalans');
    }
};
