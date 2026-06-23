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
        if (! Schema::hasTable('purchase_receipt_items') || ! Schema::hasColumn('purchase_receipt_items', 'is_sent')) {
            return;
        }

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropColumn('is_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('purchase_receipt_items')) {
            return;
        }

        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_receipt_items', 'is_sent')) {
                $table->boolean('is_sent')->default(false)->after('warehouse_id');
            }
        });
    }
};
