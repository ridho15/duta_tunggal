<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian baris invoice penjualan (Fase 5B): jumlah kotor (qty × harga satuan gross) dan nominal diskon (Rp).
     * `price` didefinisikan baku sebagai HARGA SATUAN GROSS; `discount` tetap persen; `subtotal` = DPP;
     * `tax_amount` = PPN; `total` = total baris. Nullable: invoice lama diisi lewat
     * `php artisan invoices:backfill-line-breakdown` (nilai yang sudah diposting tidak diubah).
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'gross_amount')) {
                $table->decimal('gross_amount', 18, 2)->nullable()->after('discount');
            }
            if (! Schema::hasColumn('invoice_items', 'discount_amount')) {
                $table->decimal('discount_amount', 18, 2)->nullable()->after('gross_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['discount_amount', 'gross_amount'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
