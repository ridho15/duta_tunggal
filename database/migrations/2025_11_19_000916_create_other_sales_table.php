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
        Schema::create('other_sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->date('transaction_date');
            $table->string('type'); // 'building_rental', 'other_income', etc.
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('coa_id'); // Revenue COA
            $table->unsignedBigInteger('cash_bank_account_id')->nullable(); // If paid to cash/bank
            $table->unsignedBigInteger('customer_id')->nullable(); // If there's a customer
            $table->unsignedBigInteger('cabang_id');
            $table->string('status')->default('draft'); // draft, posted, cancelled
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coa_id')->references('id')->on('chart_of_accounts');
            $table->foreign('cash_bank_account_id')->references('id')->on('cash_bank_accounts');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('cabang_id')->references('id')->on('cabangs');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_sales');
    }
};
