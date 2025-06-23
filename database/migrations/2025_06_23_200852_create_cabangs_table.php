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
        Schema::create('cabangs', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->text('alamat');
            $table->string('telepon', 20)->nullable();
            $table->decimal('kenaikan_harga', 5, 2)->default(0);
            $table->enum('status', ['Aktif', 'Tidak Aktif'])->default('Aktif');
            $table->string('warna_background', 20)->nullable();
            $table->enum('tipe_penjualan', ['Semua', 'Pajak', 'Non Pajak'])->default('Semua');
            $table->string('kode_invoice_pajak', 50)->nullable();
            $table->string('kode_invoice_non_pajak', 50)->nullable();
            $table->string('kode_invoice_pajak_walkin', 50)->nullable();
            $table->string('nama_kwitansi', 100)->nullable();
            $table->string('label_invoice_pajak', 100)->nullable();
            $table->string('label_invoice_non_pajak', 100)->nullable();
            $table->string('logo_invoice_non_pajak', 255)->nullable();
            $table->boolean('lihat_stok_cabang_lain')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabangs');
    }
};
