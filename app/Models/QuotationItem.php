<?php

namespace App\Models;

use App\Helpers\MoneyHelper;
use App\Support\CurrencyConversionResolver;
use App\Traits\LogsGlobalActivity;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'quotation_items';
    protected $fillable = [
        'quotation_id',
        'product_id',
        'notes',
        'quantity',
        'unit_price',
        'unit_price_idr',
        'total_price',
        'discount',
        'tax',
        'tax_type',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    protected static function booted(): void
    {
        static::saving(function (QuotationItem $item): void {
            $item->loadMissing('quotation');

            // Use quotation header currency as source of truth (do not persist per-item currency column)
            $unitPrice = MoneyHelper::parseHighPrecision($item->unit_price ?? 0);
            $currencyId = is_numeric($item->quotation?->currency_id) ? (int) $item->quotation->currency_id : (is_numeric($item->currency_id) ? (int) $item->currency_id : null);
            $item->unit_price_idr = (float) CurrencyConversionResolver::convertToIdrHighPrecision(
                $unitPrice,
                $currencyId
            );

            $quantity = (float) ($item->quantity ?? 0);
            $discount = (float) ($item->discount ?? 0);
            $tax = (float) ($item->tax ?? 0);
            $taxType = strtolower(trim((string) ($item->tax_type ?? 'None')));

            $base = $quantity * (float) $unitPrice;
            $afterDiscount = $base - ($base * ($discount / 100));
            $taxNominal = round($afterDiscount * ($tax / 100), 2);

            $item->total_price = match ($taxType) {
                'none', 'ppn included', 'inclusive', 'inklusif' => round($afterDiscount, 2),
                default => round($afterDiscount + $taxNominal, 2),
            };
        });

    }

    /**
     * Prepare computed fields without persisting. Useful for tests.
     */
    public function prepareForSave(): void
    {
        $this->loadMissing('quotation');

        $unitPrice = MoneyHelper::parseHighPrecision($this->unit_price ?? 0);
        $currencyId = is_numeric($this->quotation?->currency_id) ? (int) $this->quotation->currency_id : (is_numeric($this->currency_id) ? (int) $this->currency_id : null);

        $this->unit_price_idr = (float) CurrencyConversionResolver::convertToIdrHighPrecision(
            $unitPrice,
            $currencyId
        );

        $quantity = (float) ($this->quantity ?? 0);
        $discount = (float) ($this->discount ?? 0);
        $tax = (float) ($this->tax ?? 0);
        $taxType = strtolower(trim((string) ($this->tax_type ?? 'None')));

        $base = $quantity * (float) $unitPrice;
        $afterDiscount = $base - ($base * ($discount / 100));
        $taxNominal = round($afterDiscount * ($tax / 100), 2);

        $this->total_price = match ($taxType) {
            'none', 'ppn included', 'inclusive', 'inklusif' => round($afterDiscount, 2),
            default => round($afterDiscount + $taxNominal, 2),
        };
    }
}
