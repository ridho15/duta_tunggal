<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename po_number on soft-deleted duplicate rows so the unique constraint
        // can be applied without removing historical data.
        DB::statement("
            UPDATE purchase_orders po
            JOIN (
                SELECT id, po_number,
                       ROW_NUMBER() OVER (PARTITION BY po_number ORDER BY id ASC) AS rn
                FROM purchase_orders
            ) ranked ON po.id = ranked.id
            SET po.po_number = CONCAT(po.po_number, '_DELETED_', po.id)
            WHERE ranked.rn > 1
              AND po.deleted_at IS NOT NULL
        ");

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Unique index on po_number enforces DB-level uniqueness.
            // Application-level validation also exists, but this prevents race conditions.
            $table->unique('po_number', 'purchase_orders_po_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_po_number_unique');
        });
    }
};
