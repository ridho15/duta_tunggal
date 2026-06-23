<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_orders', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->after('total_amount')->constrained('currencies')->nullOnDelete();
            }

            if (! Schema::hasColumn('sale_orders', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sale_orders', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }

            if (Schema::hasColumn('sale_orders', 'currency_id')) {
                $table->dropConstrainedForeignId('currency_id');
            }
        });
    }
};