<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan Bayar non-barang (asuransi, ATK, perbaikan kendaraan, dll) + tanda tangan digital dokumen
 * (permintaan Ko Handy 28 Nov 2026). Tanda tangan disalin saat menandatangani, jadi dokumen lama tidak
 * berubah walau user mengganti gambar tanda tangannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_signatures')) {
            Schema::create('document_signatures', function (Blueprint $table) {
                $table->id();
                $table->string('signable_type');
                $table->unsignedBigInteger('signable_id');
                $table->string('role', 40);              // requested | approved | paid | created | ...
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('signer_name');
                $table->string('signer_title')->nullable();
                $table->string('signature_path')->nullable();
                $table->timestamp('signed_at');
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index(['signable_type', 'signable_id']);
                $table->unique(['signable_type', 'signable_id', 'role']);
            });
        }

        if (! Schema::hasTable('expense_requests')) {
            Schema::create('expense_requests', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique();
                $table->date('request_date');
                $table->unsignedBigInteger('cabang_id')->nullable()->index();
                $table->unsignedBigInteger('requested_by')->index();
                $table->string('payee');                  // dibayar kepada
                $table->string('description', 1000);
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('approval_notes', 1000)->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('payment_coa_id')->nullable();
                $table->unsignedBigInteger('cash_bank_transaction_id')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('expense_request_lines')) {
            Schema::create('expense_request_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expense_request_id')->index();
                $table->unsignedBigInteger('coa_id');
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 18, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_request_lines');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('document_signatures');
    }
};
