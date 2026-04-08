<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_confirmations')) {
            return;
        }

        if (! Schema::hasColumn('warehouse_confirmations', 'delivery_order_id')) {
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_order_id')->nullable()->after('sale_order_id');
            });
        }

        if (Schema::hasColumn('warehouse_confirmations', 'confirm_from') && Schema::hasColumn('warehouse_confirmations', 'confirm_id')) {
            return;
        }

        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_confirmations', 'confirm_from')) {
                $table->string('confirm_from')->nullable()->after('delivery_order_id')->comment('Source of confirmation, e.g. delivery_order, stock_movement');
            }
            if (! Schema::hasColumn('warehouse_confirmations', 'confirm_id')) {
                $table->unsignedBigInteger('confirm_id')->nullable()->after('confirm_from')->comment('ID of the source record');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('warehouse_confirmations')) {
            return;
        }

        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_confirmations', 'confirm_from')) {
                $table->dropColumn('confirm_from');
            }
            if (Schema::hasColumn('warehouse_confirmations', 'confirm_id')) {
                $table->dropColumn('confirm_id');
            }
        });
    }
};
