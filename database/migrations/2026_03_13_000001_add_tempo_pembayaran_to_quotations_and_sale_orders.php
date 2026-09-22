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
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('tempo_pembayaran')->nullable()->after('valid_until')
                ->comment('Tempo pembayaran khusus untuk quotation ini (hari), jika berbeda dari default customer');
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            $table->unsignedInteger('tempo_pembayaran')->nullable()->after('delivery_date')
                ->comment('Tempo pembayaran yang disetujui (hari), diwarisi dari quotation yang approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('tempo_pembayaran');
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn('tempo_pembayaran');
        });
    }
};
