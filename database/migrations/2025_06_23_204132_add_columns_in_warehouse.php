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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('kode');
            $table->integer('cabang_id');
            $table->enum('tipe', ['Kecil', 'Besar'])->default('Kecil');
            $table->string('telepon', 20)->nullable();
            $table->boolean('status')->default(false);
            $table->string('warna_background', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            //
        });
    }
};
