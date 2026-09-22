<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add IDR anchor columns to order_request_items.
 *
 * Problem (IDR Anchor Strategy):
 *   When user changes currency IDR→USD→IDR, price drifts due to rounding:
 *   1.000.000 ÷ 15.000 = 66.6666... → rounded to 66.67 → × 15.000 = 1.000.050 (wrong!)
 *
 * Solution:
 *   Always store the original IDR value in these anchor columns.
 *   When currency changes in the form, convert ALWAYS from the IDR anchor,
 *   not from the currently displayed (already-rounded) foreign currency value.
 *
 * Columns added:
 *   - unit_price_idr    decimal(15,2)  — unit_price converted to IDR (anchor)
 *   - original_price_idr decimal(15,2) — original_price converted to IDR (anchor)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            $table->decimal('unit_price_idr', 15, 2)->default(0)
                ->after('unit_price')
                ->comment('unit_price in IDR — anchor for lossless currency re-conversion');

            $table->decimal('original_price_idr', 15, 2)->default(0)
                ->after('original_price')
                ->comment('original_price in IDR — anchor for lossless currency re-conversion');
        });

        // Backfill existing rows:
        // For rows with currency_id, multiply by to_rupiah rate.
        // For rows without currency_id (assumed IDR), copy as-is.
        DB::statement("
            UPDATE order_request_items ori
            LEFT JOIN currencies c ON c.id = ori.currency_id
            SET
                ori.unit_price_idr = CASE
                    WHEN c.id IS NOT NULL AND c.to_rupiah > 0
                        THEN ROUND(ori.unit_price * c.to_rupiah, 2)
                    ELSE ori.unit_price
                END,
                ori.original_price_idr = CASE
                    WHEN c.id IS NOT NULL AND c.to_rupiah > 0
                        THEN ROUND(ori.original_price * c.to_rupiah, 2)
                    ELSE ori.original_price
                END
            WHERE ori.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price_idr', 'original_price_idr']);
        });
    }
};
