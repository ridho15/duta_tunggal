<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titip dana customer dicetak sebagai Voucher Penerimaan Kas/Bank (Ko Handy 27 Nov 2026): simpan tanggal terima,
 * metode, referensi, dan cabang penerima. Data lama: tanggal diambil dari jurnalnya, metode/referensi dari catatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            if (! Schema::hasColumn('deposits', 'payment_date')) {
                $table->date('payment_date')->nullable();
            }
            if (! Schema::hasColumn('deposits', 'payment_method')) {
                $table->string('payment_method', 20)->nullable();
            }
            if (! Schema::hasColumn('deposits', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable();
            }
            if (! Schema::hasColumn('deposits', 'cabang_id')) {
                $table->unsignedBigInteger('cabang_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['payment_date', 'payment_method', 'payment_reference', 'cabang_id']);
        });
    }
};
