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
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            $table->integer('invoice_id');
            $table->integer('supplier_id');
            $table->decimal('total', 18, 2);
            $table->decimal('paid', 18, 2)->default(0);
            $table->decimal('remaining', 18, 2);
            $table->enum('status', ['Lunas', 'Belum Lunas'])->default('Belum Lunas');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
