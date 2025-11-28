<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('statement_ending_balance', 18, 2)->default(0);
            $table->decimal('book_balance', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coa_id')->references('id')->on('chart_of_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
