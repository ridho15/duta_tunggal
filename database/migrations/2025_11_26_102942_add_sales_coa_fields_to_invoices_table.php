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
            $table->foreignId('revenue_coa_id')->nullable()->after('expense_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('ar_coa_id')->nullable()->after('revenue_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('ppn_keluaran_coa_id')->nullable()->after('ar_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('biaya_pengiriman_coa_id')->nullable()->after('ppn_keluaran_coa_id')->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['revenue_coa_id']);
            $table->dropForeign(['ar_coa_id']);
            $table->dropForeign(['ppn_keluaran_coa_id']);
            $table->dropForeign(['biaya_pengiriman_coa_id']);
            $table->dropColumn(['revenue_coa_id', 'ar_coa_id', 'ppn_keluaran_coa_id', 'biaya_pengiriman_coa_id']);
        });
    }
};
