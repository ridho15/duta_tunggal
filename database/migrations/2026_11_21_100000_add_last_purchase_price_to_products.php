<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Keputusan Ko Handy 21 Nov: "harga beli terakhir" adalah REFERENSI pembelian, terpisah dari HPP (rata-rata).
            $table->decimal('last_purchase_price', 18, 2)->nullable()->after('cost_price');
            $table->date('last_purchase_at')->nullable()->after('last_purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['last_purchase_price', 'last_purchase_at']);
        });
    }
};
