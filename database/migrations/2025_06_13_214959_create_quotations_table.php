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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number');
            $table->integer('customer_id');
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->bigInteger('total_amount')->default(0);
            $table->enum('status_payment', ['Sudah Bayar', 'Belum Bayar'])->default('Belum Bayar');
            $table->string('po_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'request_approve', 'approve', 'reject'])->default('draft');
            $table->integer('created_by')->nullable();
            $table->integer('request_approve_by')->nullable();
            $table->dateTime('request_approve_at')->nullable();
            $table->integer('reject_by')->nullable();
            $table->dateTime('reject_at')->nullable();
            $table->integer('approve_by')->nullable();
            $table->dateTime('approve_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
