<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T2.1 — buku besar reservasi:
     *  - stock_reservations.sale_order_item_id : kunci mapping reservasi ke ITEM SO (reservasi SO maupun DO milik SO itu)
     *  - stock_reservation_events              : riwayat append-only "kenapa stok tertahan / terlepas / terkonsumsi"
     *    (baris reservasi tetap DIHAPUS saat lepas sehingga kode Material Issue tidak terpengaruh)
     */
    public function up(): void
    {
        if (! Schema::hasColumn('stock_reservations', 'sale_order_item_id')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('sale_order_item_id')->nullable()->after('sale_order_id')->index();
            });
        }

        if (! Schema::hasTable('stock_reservation_events')) {
            Schema::create('stock_reservation_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_reservation_id')->nullable()->index();
                $table->unsignedBigInteger('sale_order_id')->nullable()->index();
                $table->unsignedBigInteger('sale_order_item_id')->nullable();
                $table->unsignedBigInteger('delivery_order_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->decimal('quantity', 15, 2);
                // reserved | adjusted | consumed | released | reconciled
                $table->string('event', 20)->index();
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservation_events');

        if (Schema::hasColumn('stock_reservations', 'sale_order_item_id')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->dropColumn('sale_order_item_id');
            });
        }
    }
};
