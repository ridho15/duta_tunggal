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
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('description');
            $table->decimal('debit', 15, 2)->default(0)->after('opening_balance');
            $table->decimal('credit', 15, 2)->default(0)->after('debit');
            $table->decimal('ending_balance', 15, 2)->default(0)->after('credit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'debit', 'credit', 'ending_balance']);
        });
    }
};
