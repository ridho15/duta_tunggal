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
        Schema::table('vendor_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_payments', 'payment_number')) {
                $table->string('payment_number', 50)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('vendor_payments', 'cabang_id')) {
                $table->unsignedBigInteger('cabang_id')->nullable()->after('payment_request_id');
                $table->foreign('cabang_id')->references('id')->on('cabangs')->nullOnDelete();
            }
            if (!Schema::hasColumn('vendor_payments', 'target_bank_account')) {
                $table->string('target_bank_account', 150)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('vendor_payments', 'transfer_reference_number')) {
                $table->string('transfer_reference_number', 100)->nullable()->after('target_bank_account');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payments', 'cabang_id')) {
                $table->dropForeign(['cabang_id']);
                $table->dropColumn('cabang_id');
            }
            if (Schema::hasColumn('vendor_payments', 'payment_number')) {
                $table->dropColumn('payment_number');
            }
            if (Schema::hasColumn('vendor_payments', 'target_bank_account')) {
                $table->dropColumn('target_bank_account');
            }
            if (Schema::hasColumn('vendor_payments', 'transfer_reference_number')) {
                $table->dropColumn('transfer_reference_number');
            }
        });
    }
};
