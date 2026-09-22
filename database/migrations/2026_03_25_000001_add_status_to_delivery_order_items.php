<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G-09: Add status column to delivery_order_items.
 *
 * Status tracks the warehousing/delivery state of each line item:
 *   pending    — default, DO not yet requested to warehouse
 *   requested  — DO is at request_stock status (WC sent to warehouse)
 *   confirmed  — WC approved for this item (DO at approved)
 *   partial    — WC mixed outcome
 *   rejected   — WC rejected / DO rejected
 *   sent       — DO marked as sent
 *   received   — DO received by customer
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_order_items', 'status')) {
            Schema::table('delivery_order_items', function (Blueprint $table) {
                $table->string('status')->default('pending')->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_order_items', 'status')) {
            Schema::table('delivery_order_items', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
