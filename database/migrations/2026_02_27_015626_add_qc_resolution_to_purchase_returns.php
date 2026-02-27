<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds QC-based purchase return support:
     * - Makes purchase_receipt_id nullable (returns can now originate from QC before a receipt exists)
     * - Adds quality_control_id FK to link the return back to the failing QC record
     * - Adds failed_qc_action to record how the rejected items are resolved
     * - Makes purchase_return_items.purchase_receipt_item_id nullable for the same reason
     */
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            // Make receipt link optional - QC-based returns exist before receipt is created
            $table->unsignedBigInteger('purchase_receipt_id')->nullable()->change();

            // Link to the QC record that generated this return
            $table->unsignedBigInteger('quality_control_id')->nullable()->after('purchase_receipt_id');
            $table->foreign('quality_control_id')->references('id')->on('quality_controls')->nullOnDelete();

            // How the rejected items should be resolved:
            //   reduce_stock      – shrink the PO item qty, absorb the loss
            //   wait_next_delivery – supplier will resend; PO remains open
            //   merge_next_order  – carry qty+original price into a future PO
            $table->string('failed_qc_action')->nullable()->after('quality_control_id');
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            // Make receipt-item link optional – QC items exist before receipt items
            $table->unsignedBigInteger('purchase_receipt_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['quality_control_id']);
            $table->dropColumn(['quality_control_id', 'failed_qc_action']);
            $table->unsignedBigInteger('purchase_receipt_id')->nullable(false)->change();
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_receipt_item_id')->nullable(false)->change();
        });
    }
};
