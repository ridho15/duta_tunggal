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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('cabang_id');
            $table->integer('harga_batas')->default(0)->comment('%');
            $table->bigInteger('item_value')->default(0)->comment('Rp.');
            $table->enum('tipe_pajak', ['Non Pajak', 'Inklusif', 'Eksklusif'])->default('Non Pajak');
            $table->decimal('pajak', 5, 2)->default(0)->comment('%');
            $table->integer('jumlah_kelipatan_gudang_besar')->default(0);
            $table->integer('jumlah_jual_kategori_banyak')->default(0);
            $table->string('kode_merk', 50);
            $table->dropColumn(['usefull_life_years', 'residual_value', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
