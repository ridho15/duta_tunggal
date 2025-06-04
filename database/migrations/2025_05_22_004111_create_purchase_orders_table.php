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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('supplier_id');
            $table->string('po_number');
            $table->dateTime('order_date');
            $table->enum('status', ['draft', 'approved', 'partially_received', 'completed', 'closed']);
            $table->datetime('expected_date')->nullable();
            $table->bigInteger('total_amount')->default(0);
            $table->enum('tax_type', ['include', 'exclude', 'non']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
