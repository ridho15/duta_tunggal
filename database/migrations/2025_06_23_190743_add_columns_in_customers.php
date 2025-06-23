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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('code');
            $table->string('perusahaan');
            $table->enum('tipe', ['PKP', 'PRI']);
            $table->string('fax');
            $table->boolean('isSpecial')->default(false);
            $table->integer('tempo_kredit')->default(0)->comment('Hitungan Hari');
            $table->bigInteger('kredit_limit')->default(0);
            $table->enum('tipe_pembayaran', ['Bebas', 'COD (Bayar Lunas)', 'Kredit'])->default('Bebas');
            $table->string('nik_npwp')->comment('NIK / NPWP');
            $table->text('keterangan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            //
        });
    }
};
