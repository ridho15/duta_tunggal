<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H4/I1/I2: Add delivery_order_id + rejection_reason to warehouse_confirmations,
 * extend ENUMs for both tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add delivery_order_id and rejection_reason to warehouse_confirmations (idempotent)
        if (! Schema::hasColumn('warehouse_confirmations', 'delivery_order_id')) {
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_order_id')->nullable()->after('sale_order_id');
                $table->foreign('delivery_order_id')
                      ->references('id')
                      ->on('delivery_orders')
                      ->onDelete('set null');
            });
        }
        if (! Schema::hasColumn('warehouse_confirmations', 'rejection_reason')) {
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('note');
            });
        }

        // 2. Migrate any capitalised status values to lowercase (no-op if table is empty)
        DB::statement("UPDATE warehouse_confirmations SET status = LOWER(status)
                        WHERE status IN ('Confirmed','Rejected','Request')");

        // 3. Change WC status ENUM to lowercase-only + partial_confirmed
        DB::statement("ALTER TABLE warehouse_confirmations MODIFY COLUMN status ENUM(
            'confirmed','rejected','request','partial_confirmed'
        ) NOT NULL DEFAULT 'request'");

        // 4. Extend delivery_orders.status ENUM to add request_stock and partial
        DB::statement("ALTER TABLE delivery_orders MODIFY COLUMN status ENUM(
            'draft','sent','confirmed','received','supplier','completed',
            'request_approve','approved','request_close','closed','reject','delivery_failed',
            'request_stock','partial'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_confirmations', 'delivery_order_id')) {
                $table->dropForeign(['delivery_order_id']);
                $table->dropColumn('delivery_order_id');
            }
            if (Schema::hasColumn('warehouse_confirmations', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });

        DB::statement("ALTER TABLE warehouse_confirmations MODIFY COLUMN status ENUM(
            'Confirmed','Rejected','Request'
        ) NOT NULL DEFAULT 'Request'");

        DB::statement("ALTER TABLE delivery_orders MODIFY COLUMN status ENUM(
            'draft','sent','confirmed','received','supplier','completed',
            'request_approve','approved','request_close','closed','reject','delivery_failed'
        ) NOT NULL DEFAULT 'draft'");
    }
};
