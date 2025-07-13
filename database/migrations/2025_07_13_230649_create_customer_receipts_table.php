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
        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->integer('invoice_id');
            $table->integer('customer_id');
            $table->date('payment_date');
            $table->string('ntpn')->nullable();
            $table->decimal('total_payment', 18, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['Draft', 'Partial', 'Paid'])->default('Draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_receipts');
    }
};
