<?php

namespace App\Models;

use App\Helpers\MoneyHelper;
use App\Support\CurrencyConversionResolver;
use App\Support\TaxDefaultResolver;
use App\Support\TaxTypeHelper;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRequestItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'order_request_items';
    protected $fillable = [
        'order_request_id',
        'supplier_id',
        'cabang_id',
        'product_id',
        'quantity',
        'fulfilled_quantity',
        'unit_price',
        'unit_price_idr',       // IDR anchor — always stored in IDR for lossless re-conversion
        'original_price',
        'original_price_idr',   // IDR anchor — always stored in IDR for lossless re-conversion
        'discount',
        'tax',
        'tipe_pajak',
        'subtotal',
        'note',
        'currency_id',
    ];

    public function orderRequest()
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->morphOne(PurchaseOrderItem::class, 'refer_item_model')->withDefault();
    }

    protected static function booted()
    {
        static::saving(function (OrderRequestItem $item) {
            $item->loadMissing('orderRequest');

            $quantity    = (float) ($item->quantity ?? 0);
            $unitPrice   = MoneyHelper::safeParse($item->unit_price ?? 0);
            $discount    = (float) ($item->discount ?? 0);
            $itemTaxType = static::normalizeItemTaxType($item->tipe_pajak ?? null);
            $taxType     = static::taxServiceTypeFromItemTaxType($itemTaxType);
            $item->tipe_pajak = $itemTaxType;
            $tax = $taxType === 'None'
                ? 0.0
                : TaxDefaultResolver::resolveForProductId((int) ($item->product_id ?? 0), $taxType);
            $item->tax = $tax;

            // ── IDR Anchor ─────────────────────────────────────────────────────────
            // Preserve existing anchors when present. Form code updates these anchors
            // when a user explicitly edits a price, so recalculating here from a
            // rounded foreign-currency display would reintroduce drift.
            $currencyId = $item->currency_id ?? null;

            if ((float) ($item->unit_price_idr ?? 0) <= 0) {
                $item->unit_price_idr = (float) CurrencyConversionResolver::convertToIdrHighPrecision(
                    MoneyHelper::parseHighPrecision($item->unit_price ?? 0),
                    $currencyId ? (int) $currencyId : null
                );
            }

            if ((float) ($item->original_price_idr ?? 0) <= 0) {
                $item->original_price_idr = (float) CurrencyConversionResolver::convertToIdrHighPrecision(
                    MoneyHelper::parseHighPrecision($item->original_price ?? 0),
                    $currencyId ? (int) $currencyId : null
                );
            }
            // ───────────────────────────────────────────────────────────────────────

            $base      = $quantity * $unitPrice;
            $afterDisc = $base - ($base * ($discount / 100));

            $taxNominal = round($afterDisc * ($tax / 100), 2);
            $item->subtotal = match ($itemTaxType) {
                'none', 'inklusif' => round($afterDisc, 2),
                default            => round($afterDisc + $taxNominal, 2),
            };
        });

        // Do not sync product_supplier on OR item save.
        // Pivot synchronization is intentionally handled during OR approval / PO creation flow.
    }

    public static function normalizeItemTaxType(?string $value): string
    {
        return TaxTypeHelper::normalize($value);
    }

    public static function taxServiceTypeFromItemTaxType(?string $itemTaxType): string
    {
        return TaxTypeHelper::serviceType($itemTaxType);
    }

    /**
     * Update fulfilled quantity by adding the given amount
     */
    public function addFulfilledQuantity($quantity)
    {
        $this->fulfilled_quantity = ($this->fulfilled_quantity ?? 0) + $quantity;
        $this->save();
    }

    /**
     * Update fulfilled quantity by subtracting the given amount
     */
    public function reduceFulfilledQuantity($quantity)
    {
        $this->fulfilled_quantity = max(0, ($this->fulfilled_quantity ?? 0) - $quantity);
        $this->save();
    }

    /**
     * Get remaining quantity (not yet fulfilled)
     */
    public function getRemainingQuantityAttribute()
    {
        return max(0, $this->quantity - ($this->fulfilled_quantity ?? 0));
    }
}
