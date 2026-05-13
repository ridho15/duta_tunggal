<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_requests', 'cabang_id')) {
                try {
                    $table->dropForeign(['cabang_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key does not exist
                }

                try {
                    $table->dropColumn('cabang_id');
                } catch (\Throwable $e) {
                    // ignore if already removed by another migration
                }
            }

            if (Schema::hasColumn('order_requests', 'warehouse_id')) {
                try {
                    $table->dropForeign(['warehouse_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key does not exist
                }

                try {
                    $table->dropColumn('warehouse_id');
                } catch (\Throwable $e) {
                    // ignore if already removed by another migration
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
