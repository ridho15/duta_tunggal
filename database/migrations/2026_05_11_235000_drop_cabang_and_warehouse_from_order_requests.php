<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration
{
    public function up(): void
    {
        // First, try to drop foreign keys safely
        try {
            DB::statement('ALTER TABLE `order_requests` DROP FOREIGN KEY `order_requests_cabang_id_foreign`');
        } catch (\Throwable $e) {
            // Key doesn't exist, continue
        }
        
        try {
            DB::statement('ALTER TABLE `order_requests` DROP FOREIGN KEY `order_requests_warehouse_id_foreign`');
        } catch (\Throwable $e) {
            // Key doesn't exist, continue
        }

        // Then drop the columns
        Schema::table('order_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_requests', 'cabang_id')) {
                try {
                    $table->dropColumn('cabang_id');
                } catch (\Throwable $e) {
                    // Column might already be dropped
                }
            }

            if (Schema::hasColumn('order_requests', 'warehouse_id')) {
                try {
                    $table->dropColumn('warehouse_id');
                } catch (\Throwable $e) {
                    // Column might already be dropped
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('order_requests', 'cabang_id')) {
                $table->unsignedBigInteger('cabang_id')->nullable()->after('currency_id');
            }

            if (! Schema::hasColumn('order_requests', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('cabang_id');
            }
        });
    }
};
