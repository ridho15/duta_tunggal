<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan Ko Handy 24 Nov 2026 — Mutasi 4 tahap: Buat → Disetujui (stok asal dicadangkan) → Dikirim gudang asal
 * (boleh sebagian item & sebagian qty, boleh beberapa kali) → Diterima gudang tujuan.
 *
 *  - stock_transfer_shipments / _items : tiap pengiriman (qty yang benar-benar naik kendaraan) + tanda terima gudang tujuan.
 *  - stock_reservations.stock_transfer_item_id : cadangan stok asal sejak disetujui (qty_reserved dijaga StockReservationObserver).
 *  - warehouses.is_transit : gudang transit per cabang tujuan untuk barang dalam perjalanan (bukan stok jual).
 *  - stock_transfers.status + 'Shipped'; kolom tutup sisa (sisa qty yang batal dikirim).
 *  - Mutasi lama berstatus Approved yang stoknya SUDAH dipindah (aturan lama) → 'Completed' agar tidak bisa dikirim ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfers MODIFY status ENUM('Pending','Completed','Cancelled','Draft','Approved','Request','Reject','Shipped') NOT NULL DEFAULT 'Draft'");
        }

        Schema::table('stock_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_transfers', 'remaining_closed_at')) {
                $table->timestamp('remaining_closed_at')->nullable();
                $table->unsignedBigInteger('remaining_closed_by')->nullable();
                $table->string('remaining_close_reason', 500)->nullable();
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'is_transit')) {
                $table->boolean('is_transit')->default(false);
            }
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_reservations', 'stock_transfer_item_id')) {
                $table->unsignedBigInteger('stock_transfer_item_id')->nullable()->index();
            }
        });

        if (! Schema::hasTable('stock_transfer_shipments')) {
            Schema::create('stock_transfer_shipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_id')->index();
                $table->string('shipment_number')->unique();
                $table->unsignedBigInteger('transit_warehouse_id');
                $table->timestamp('shipped_at');
                $table->unsignedBigInteger('shipped_by')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_transfer_shipment_items')) {
            Schema::create('stock_transfer_shipment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_shipment_id')->index();
                $table->unsignedBigInteger('stock_transfer_item_id')->index();
                $table->decimal('quantity', 15, 2);
                $table->timestamps();
            });
        }

        // Mutasi Approved lama: stok sudah dipindah saat disetujui (aturan lama) → tandai selesai
        DB::table('stock_transfers')
            ->where('status', 'Approved')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('stock_movements')
                ->whereColumn('stock_movements.from_model_id', 'stock_transfers.id')
                ->where('stock_movements.from_model_type', 'App\\Models\\StockTransfer')
                ->whereNull('stock_movements.deleted_at'))
            ->update(['status' => 'Completed']);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_shipment_items');
        Schema::dropIfExists('stock_transfer_shipments');
        Schema::table('stock_reservations', fn (Blueprint $t) => $t->dropColumn('stock_transfer_item_id'));
        Schema::table('warehouses', fn (Blueprint $t) => $t->dropColumn('is_transit'));
        Schema::table('stock_transfers', fn (Blueprint $t) => $t->dropColumn(['remaining_closed_at', 'remaining_closed_by', 'remaining_close_reason']));
    }
};
