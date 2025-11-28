<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('date');
            $table->unsignedBigInteger('from_coa_id');
            $table->unsignedBigInteger('to_coa_id');
            $table->unsignedBigInteger('clearing_coa_id')->nullable(); // Ayat Silang
            $table->decimal('amount', 18, 2);
            $table->decimal('other_costs', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['draft', 'posted', 'reconciled'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_coa_id')->references('id')->on('chart_of_accounts');
            $table->foreign('to_coa_id')->references('id')->on('chart_of_accounts');
            $table->foreign('clearing_coa_id')->references('id')->on('chart_of_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bank_transfers');
    }
};
