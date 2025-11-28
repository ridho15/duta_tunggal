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
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreignId('payment_coa_id')
                  ->nullable()
                  ->constrained('chart_of_accounts')
                  ->onDelete('set null')
                  ->after('coa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign(['payment_coa_id']);
            $table->dropColumn('payment_coa_id');
        });
    }
};
