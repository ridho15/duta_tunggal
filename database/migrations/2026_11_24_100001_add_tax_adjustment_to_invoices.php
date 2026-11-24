<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan Ko Handy 24 Nov 2026: DPP/PPN Purchase Invoice boleh disamakan dengan Faktur Pajak supplier (selisih pembulatan
 * maks. Rp500, hanya Finance / Finance Manager). Jejak perubahan: angka sistem, angka faktur, selisih, siapa & kapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'tax_adjustment')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->json('tax_adjustment')->nullable()->after('price_variance_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'tax_adjustment')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('tax_adjustment');
            });
        }
    }
};
