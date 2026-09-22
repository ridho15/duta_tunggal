<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new polymorphic columns (idempotent: skip if already present)
        if (!Schema::hasColumn('warehouse_confirmations', 'confirmable_type')) {
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->string('confirmable_type')->nullable()->after('delivery_order_id')->comment('Polymorphic type, e.g. App\\Models\\SaleOrder');
                $table->unsignedBigInteger('confirmable_id')->nullable()->after('confirmable_type')->comment('Polymorphic ID of the source record');
                $table->index(['confirmable_type', 'confirmable_id'], 'wc_confirmable_index');
            });
        }

        // 2. Migrate existing data to polymorphic columns
        //    Priority: delivery_order_id > manufacturing_order_id > sale_order_id
        //    Only run each UPDATE if the source column still exists
        $hasDO  = Schema::hasColumn('warehouse_confirmations', 'delivery_order_id');
        $hasMO  = Schema::hasColumn('warehouse_confirmations', 'manufacturing_order_id');
        $hasSO  = Schema::hasColumn('warehouse_confirmations', 'sale_order_id');

        if ($hasDO) {
            DB::statement("
                UPDATE warehouse_confirmations
                SET confirmable_type = 'App\\\\Models\\\\DeliveryOrder',
                    confirmable_id   = delivery_order_id
                WHERE delivery_order_id IS NOT NULL
                  AND confirmable_type IS NULL
            ");
        }

        if ($hasMO) {
            $andDO = $hasDO ? 'AND delivery_order_id IS NULL' : 'AND 1=1';
            DB::statement("
                UPDATE warehouse_confirmations
                SET confirmable_type = 'App\\\\Models\\\\ManufacturingOrder',
                    confirmable_id   = manufacturing_order_id
                WHERE manufacturing_order_id IS NOT NULL
                  {$andDO}
                  AND confirmable_type IS NULL
            ");
        }

        if ($hasSO) {
            $andDO = $hasDO ? 'AND delivery_order_id IS NULL' : 'AND 1=1';
            $andMO = $hasMO ? 'AND manufacturing_order_id IS NULL' : 'AND 1=1';
            DB::statement("
                UPDATE warehouse_confirmations
                SET confirmable_type = 'App\\\\Models\\\\SaleOrder',
                    confirmable_id   = sale_order_id
                WHERE sale_order_id IS NOT NULL
                  {$andDO}
                  {$andMO}
                  AND confirmable_type IS NULL
            ");
        }

        // 3. Drop old FK columns one by one, each in own Schema::table() call
        //    so a missing FK/column on one doesn't abort the others.
        $cols = Schema::getColumnListing('warehouse_confirmations');

        if (in_array('sale_order_id', $cols)) {
            try {
                Schema::table('warehouse_confirmations', function (Blueprint $table) {
                    $table->dropForeign(['sale_order_id']);
                });
            } catch (\Throwable) {}
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->dropColumn('sale_order_id');
            });
        }

        if (in_array('manufacturing_order_id', $cols)) {
            try {
                Schema::table('warehouse_confirmations', function (Blueprint $table) {
                    $table->dropForeign(['manufacturing_order_id']);
                });
            } catch (\Throwable) {}
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->dropColumn('manufacturing_order_id');
            });
        }

        if (in_array('delivery_order_id', $cols)) {
            try {
                Schema::table('warehouse_confirmations', function (Blueprint $table) {
                    $table->dropForeign(['delivery_order_id']);
                });
            } catch (\Throwable) {}
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->dropColumn('delivery_order_id');
            });
        }

        // Drop confirm_from / confirm_id from previous migration if they exist
        if (in_array('confirm_from', $cols)) {
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->dropColumn('confirm_from');
            });
        }
        if (in_array('confirm_id', $cols)) {
            Schema::table('warehouse_confirmations', function (Blueprint $table) {
                $table->dropColumn('confirm_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            // Restore old FK columns
            $table->unsignedBigInteger('sale_order_id')->nullable()->after('id');
            $table->unsignedBigInteger('manufacturing_order_id')->nullable()->after('sale_order_id');
            $table->unsignedBigInteger('delivery_order_id')->nullable()->after('manufacturing_order_id');
            $table->string('confirm_from')->nullable()->after('delivery_order_id');
            $table->unsignedBigInteger('confirm_id')->nullable()->after('confirm_from');
        });

        // Restore data from polymorphic columns
        DB::statement("
            UPDATE warehouse_confirmations
            SET sale_order_id = confirmable_id
            WHERE confirmable_type = 'App\\\\Models\\\\SaleOrder'
        ");
        DB::statement("
            UPDATE warehouse_confirmations
            SET manufacturing_order_id = confirmable_id
            WHERE confirmable_type = 'App\\\\Models\\\\ManufacturingOrder'
        ");
        DB::statement("
            UPDATE warehouse_confirmations
            SET delivery_order_id = confirmable_id
            WHERE confirmable_type = 'App\\\\Models\\\\DeliveryOrder'
        ");

        // Drop new columns
        Schema::table('warehouse_confirmations', function (Blueprint $table) {
            $table->dropIndex('wc_confirmable_index');
            $table->dropColumn(['confirmable_type', 'confirmable_id']);
        });
    }
};
