<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HPP rata-rata bergerak disimpan 4 desimal: pembulatan 2 desimal meninggalkan sisa saldo Persediaan di Buku Besar
     * saat stok habis (mis. 3@100 + 4@101 → HPP 100,57 × 7 = 703,99 ≠ 704). Nilai lama tidak berubah (hanya presisi bertambah).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 18, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 18, 2)->default(0)->change();
        });
    }
};
