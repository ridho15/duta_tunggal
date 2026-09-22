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
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (Schema::hasColumn('invoices', 'purchase_order_ids')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            // Support for multiple Purchase Orders per invoice (Task 14)
            $table->json('purchase_order_ids')->nullable()->after('purchase_receipts')
                  ->comment('Array of PO IDs linked to this invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'purchase_order_ids')) {
                $table->dropColumn('purchase_order_ids');
            }
        });
    }
};
