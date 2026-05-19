<?php

use App\Support\CurrencyConversionResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_order_items') || Schema::hasColumn('sale_order_items', 'currency_id')) {
            return;
        }

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('tipe_pajak');
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
        });

        $idrCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');

        DB::table('sale_order_items')
            ->leftJoin('sale_orders', 'sale_orders.id', '=', 'sale_order_items.sale_order_id')
            ->update([
                'sale_order_items.currency_id' => DB::raw('COALESCE(sale_orders.currency_id, ' . (int) $idrCurrencyId . ')'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_order_items') || ! Schema::hasColumn('sale_order_items', 'currency_id')) {
            return;
        }

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
