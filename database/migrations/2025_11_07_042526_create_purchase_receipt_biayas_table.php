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
        Schema::create('purchase_receipt_biayas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->onDelete('cascade');
            $table->string('nama_biaya');
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('cascade');
            $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('cascade');
            $table->bigInteger('total')->default(0);
            $table->boolean('untuk_pembelian')->default(false)->comment('Untuk pembelian');
            $table->boolean('masuk_invoice')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_biayas');
    }
};
