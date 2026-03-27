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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('project_id');
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
            $table->decimal('amount_original_currency', 20, 4)->nullable()->after('exchange_rate');

            $table->index('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['currency_id']);
            $table->dropColumn([
                'currency_id',
                'exchange_rate',
                'amount_original_currency',
            ]);
        });
    }
};
