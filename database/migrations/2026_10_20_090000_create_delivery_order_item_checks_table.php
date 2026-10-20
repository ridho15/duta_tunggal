<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 3 Spesifikasi UI/UX (6.5): centang barang oleh checker di HP sebelum KIRIM.
     * Tabel terpisah (bukan kolom di delivery_order_items) agar mencentang tidak memicu
     * hook sinkron stok/jurnal milik DeliveryOrderItem.
     */
    public function up(): void
    {
        Schema::create('delivery_order_item_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_item_id')->unique()->constrained('delivery_order_items')->cascadeOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_item_checks');
    }
};
