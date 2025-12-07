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
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_po_id')->nullable();
            $table->date('replacement_date')->nullable();
            $table->text('replacement_notes')->nullable();
            $table->string('supplier_response')->nullable();
            $table->boolean('credit_note_received')->default(false);
            $table->date('case_closed_date')->nullable();
            $table->text('tracking_notes')->nullable();
            $table->string('delivery_note')->nullable();
            $table->text('shipping_details')->nullable();
            $table->date('physical_return_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn([
                'replacement_po_id',
                'replacement_date',
                'replacement_notes',
                'supplier_response',
                'credit_note_received',
                'case_closed_date',
                'tracking_notes',
                'delivery_note',
                'shipping_details',
                'physical_return_date',
            ]);
        });
    }
};
