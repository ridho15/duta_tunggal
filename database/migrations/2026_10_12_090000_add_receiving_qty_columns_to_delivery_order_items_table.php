<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_order_items', 'qty_received')) {
                $table->decimal('qty_received', 15, 2)->nullable()->after('status');
            }
            if (! Schema::hasColumn('delivery_order_items', 'qty_shortage')) {
                $table->decimal('qty_shortage', 15, 2)->nullable()->after('qty_received');
            }
            if (! Schema::hasColumn('delivery_order_items', 'qty_rejected')) {
                $table->decimal('qty_rejected', 15, 2)->nullable()->after('qty_shortage');
            }
            if (! Schema::hasColumn('delivery_order_items', 'receiving_notes')) {
                $table->text('receiving_notes')->nullable()->after('qty_rejected');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            foreach (['qty_received', 'qty_shortage', 'qty_rejected', 'receiving_notes'] as $column) {
                if (Schema::hasColumn('delivery_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
