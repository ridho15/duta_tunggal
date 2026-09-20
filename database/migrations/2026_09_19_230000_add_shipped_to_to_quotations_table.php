<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alamat kirim yang disepakati pada penawaran. Disalin ke Sales Order saat
     * quotation dijadikan SO; bila kosong, SO memakai alamat master customer.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'shipped_to')) {
                $table->string('shipped_to', 255)
                    ->nullable()
                    ->after('tempo_pembayaran')
                    ->comment('Alamat kirim yang disepakati pada penawaran');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'shipped_to')) {
                $table->dropColumn('shipped_to');
            }
        });
    }
};
