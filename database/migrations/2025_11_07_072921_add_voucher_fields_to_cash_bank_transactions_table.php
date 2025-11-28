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
        Schema::table('cash_bank_transactions', function (Blueprint $table) {
            $table->foreignId('voucher_request_id')->nullable()->constrained('voucher_requests')->nullOnDelete()->after('cash_bank_account_id');
            $table->string('voucher_number')->nullable()->after('voucher_request_id');
            $table->enum('voucher_usage_type', ['single_use', 'multi_use'])->nullable()->after('voucher_number');
            $table->decimal('voucher_amount_used', 15, 2)->nullable()->after('voucher_usage_type');
            $table->index(['voucher_request_id', 'voucher_usage_type'], 'cbt_voucher_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['voucher_request_id']);
            $table->dropColumn(['voucher_request_id', 'voucher_number', 'voucher_usage_type', 'voucher_amount_used']);
        });
    }
};
