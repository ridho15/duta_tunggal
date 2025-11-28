<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('date');
            $table->enum('type', ['cash_in', 'cash_out', 'bank_in', 'bank_out']);
            $table->unsignedBigInteger('account_coa_id'); // Kas/Bank account
            $table->unsignedBigInteger('offset_coa_id');  // Lawan akun
            $table->decimal('amount', 18, 2);
            $table->string('counterparty')->nullable();
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_coa_id')->references('id')->on('chart_of_accounts');
            $table->foreign('offset_coa_id')->references('id')->on('chart_of_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bank_transactions');
    }
};
