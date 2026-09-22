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
        if (! Schema::hasTable('quotation_items')) {
            return;
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_items', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('tax_type');
                $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotation_items', 'unit_price_idr')) {
                $table->decimal('unit_price_idr', 20, 10)->nullable()->after('unit_price');
            }
        });

        $idrCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');

        if ($idrCurrencyId) {
            DB::table('quotation_items')
                ->leftJoin('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
                ->update([
                    'quotation_items.currency_id' => DB::raw('COALESCE(quotations.currency_id, ' . (int) $idrCurrencyId . ')'),
                ]);
        }

        DB::table('quotation_items')
            ->leftJoin('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->select('quotation_items.id', 'quotation_items.unit_price', 'quotation_items.currency_id')
            ->orderBy('quotation_items.id')
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $currencyId = is_numeric($item->currency_id) ? (int) $item->currency_id : null;
                    $idrValue = CurrencyConversionResolver::convertToIdrHighPrecision((string) ($item->unit_price ?? 0), $currencyId);

                    DB::table('quotation_items')
                        ->where('id', $item->id)
                        ->update([
                            'unit_price_idr' => $idrValue,
                        ]);
                }
            }, 'quotation_items.id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('quotation_items')) {
            return;
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_items', 'unit_price_idr')) {
                $table->dropColumn('unit_price_idr');
            }
            if (Schema::hasColumn('quotation_items', 'currency_id')) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
            }
        });
    }
};
