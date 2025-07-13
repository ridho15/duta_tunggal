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
        Schema::create('customer_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_receipt_id');
            $table->string('method'); // Cash, Bank, Credit, Deposit
            $table->decimal('amount', 18, 2);
            $table->integer('coa_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_receipt_items');
    }
};
