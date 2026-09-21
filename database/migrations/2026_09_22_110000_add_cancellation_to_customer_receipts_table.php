<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T3.3 (D29) — Batalkan Penerimaan: status `Cancelled` + siapa/kapan/mengapa. Penerimaan yang sudah berjurnal tidak lagi
     * diubah/dihapus langsung; koreksinya = pembatalan dengan jurnal balik (dokumen asli tetap tersimpan sebagai jejak).
     */
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_receipts', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('customer_receipts', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable();
            }
            if (! Schema::hasColumn('customer_receipts', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE customer_receipts MODIFY status ENUM('Draft','Partial','Paid','Cancelled') NOT NULL DEFAULT 'Draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('customer_receipts')->where('status', 'Cancelled')->update(['status' => 'Draft']);
            DB::statement("ALTER TABLE customer_receipts MODIFY status ENUM('Draft','Partial','Paid') NOT NULL DEFAULT 'Draft'");
        }

        Schema::table('customer_receipts', function (Blueprint $table) {
            foreach (['cancel_reason', 'cancelled_by', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('customer_receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
