<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            // Tanggal kirim (jadwal pengiriman). Stok terpotong pada tanggal ini (keputusan Ko Handy 10 Nov 2026).
            // Kosong = tanggal SJ dibuat (issued_at).
            $table->date('delivery_date')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->dropColumn('delivery_date');
        });
    }
};
