<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T1.3 — referensi & bukti pada penerimaan customer non-tunai:
     *  - payment_reference : nomor referensi transfer / nomor giro / nomor cek (wajib untuk Transfer, Giro, Cheque — di form)
     *  - bank_name         : nama bank (opsional)
     *  - proof_path        : berkas bukti (disk privat `local`, diunduh lewat rute berotorisasi)
     * Semua nullable → data lama tidak berubah.
     */
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_receipts', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method')->index();
            }
            if (! Schema::hasColumn('customer_receipts', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('payment_reference');
            }
            if (! Schema::hasColumn('customer_receipts', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('bank_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            foreach (['proof_path', 'bank_name', 'payment_reference'] as $column) {
                if (Schema::hasColumn('customer_receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
