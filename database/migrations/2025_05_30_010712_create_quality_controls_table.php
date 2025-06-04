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
        Schema::create('quality_controls', function (Blueprint $table) {
            $table->id();
            $table->integer('warehouse_id');
            $table->integer('purchase_receipt_item_id');
            $table->integer('inspected_by')->nullable()->comment('user quality control');
            $table->integer('passed_quantity')->default(0);
            $table->integer('rejected_quantity')->default(0);
            $table->text('notes')->nullable();
            $table->text('reason_reject')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_controls');
    }
};
