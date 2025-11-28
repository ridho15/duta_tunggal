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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('accounts_payable_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('ppn_masukan_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('inventory_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->foreignId('expense_coa_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['accounts_payable_coa_id']);
            $table->dropForeign(['ppn_masukan_coa_id']);
            $table->dropForeign(['inventory_coa_id']);
            $table->dropForeign(['expense_coa_id']);
            $table->dropColumn(['accounts_payable_coa_id', 'ppn_masukan_coa_id', 'inventory_coa_id', 'expense_coa_id']);
        });
    }
};
