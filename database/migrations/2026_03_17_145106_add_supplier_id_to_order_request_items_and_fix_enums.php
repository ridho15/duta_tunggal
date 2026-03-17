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
        // 1. Add supplier_id to order_request_items (supports per-item supplier selection)
        Schema::table('order_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_request_items', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('order_request_id');
            }
        });

        // 2. Extend order_requests.status enum to include all workflow statuses
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE `order_requests` MODIFY COLUMN `status`
             ENUM('draft','request_approve','approved','partial','complete','closed','rejected')
             NOT NULL DEFAULT 'draft'"
        );

        // 3. Extend order_requests.tax_type enum to include 'None'
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE `order_requests` MODIFY COLUMN `tax_type`
             ENUM('None','PPN Included','PPN Excluded')
             NOT NULL DEFAULT 'PPN Excluded'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_request_items', 'supplier_id')) {
                $table->dropColumn('supplier_id');
            }
        });

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE `order_requests` MODIFY COLUMN `status`
             ENUM('draft','approved','rejected','closed')
             NOT NULL DEFAULT 'draft'"
        );

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE `order_requests` MODIFY COLUMN `tax_type`
             ENUM('PPN Included','PPN Excluded')
             NOT NULL DEFAULT 'PPN Excluded'"
        );
    }
};
