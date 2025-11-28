<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('cash_bank_transactions') && ! Schema::hasColumn('cash_bank_transactions', 'cash_bank_account_id')) {
            Schema::table('cash_bank_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('cash_bank_account_id')->nullable()->after('type')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cash_bank_transactions') && Schema::hasColumn('cash_bank_transactions', 'cash_bank_account_id')) {
            Schema::table('cash_bank_transactions', function (Blueprint $table) {
                $table->dropColumn('cash_bank_account_id');
            });
        }
    }
};
