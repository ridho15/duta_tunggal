<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatalan invoice pembelian yang sudah diposting (jurnal balik, bukan hapus).
 * Enum status ditambah 'cancelled' (kode overdue, izin faktur pajak, dan filter invoice penjualan sudah
 * mengecualikan 'cancelled'/'canceled', tetapi kolomnya belum bisa menyimpannya) dan dicatat siapa, kapan, dan mengapa.
 */
return new class extends Migration
{
    private const STATUSES_BEFORE = "'draft','sent','paid','partially_paid','overdue','unpaid'";

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY status ENUM(' . self::STATUSES_BEFORE . ",'cancelled') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['cancel_reason', 'cancelled_by', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Enum hanya dikembalikan bila tidak ada baris 'cancelled' yang akan kehilangan nilainya.
        if (DB::getDriverName() === 'mysql' && ! DB::table('invoices')->where('status', 'cancelled')->exists()) {
            DB::statement('ALTER TABLE invoices MODIFY status ENUM(' . self::STATUSES_BEFORE . ") NOT NULL DEFAULT 'draft'");
        }
    }
};
