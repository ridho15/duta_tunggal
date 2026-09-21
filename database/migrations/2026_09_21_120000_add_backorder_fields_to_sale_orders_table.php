<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** T2.5 (D2) — SO yang disetujui walau stok kurang harus tercatat sebagai Backorder: alasan, penyetuju, waktu. */
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_orders', 'is_backorder')) {
                $table->boolean('is_backorder')->default(false);
            }
            if (! Schema::hasColumn('sale_orders', 'backorder_reason')) {
                $table->text('backorder_reason')->nullable();
            }
            if (! Schema::hasColumn('sale_orders', 'backorder_approved_by')) {
                $table->unsignedBigInteger('backorder_approved_by')->nullable();
            }
            if (! Schema::hasColumn('sale_orders', 'backorder_approved_at')) {
                $table->timestamp('backorder_approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            foreach (['backorder_approved_at', 'backorder_approved_by', 'backorder_reason', 'is_backorder'] as $column) {
                if (Schema::hasColumn('sale_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
