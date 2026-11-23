<?php

namespace App\Support;

use App\Helpers\MoneyHelper;
use App\Models\Currency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptItem;

class JournalCurrencyAmountResolver
{
    /**
     * @return array{amount_original_currency: float, currency_id: ?int, currency_code: ?string, exchange_rate: float, amount_idr: float}
     */
    public static function resolve(mixed $amount, ?int $currencyId = null, ?float $historicalRate = null): array
    {
        $amount = (float) MoneyHelper::parseHighPrecision($amount);
        $exchangeRate = (float) ($historicalRate ?? 0);
        if ($exchangeRate <= 0) {
            $exchangeRate = CurrencyConversionResolver::resolveRate($currencyId);
        }
        if ($exchangeRate <= 0) {
            $exchangeRate = 1.0;
        }

        $currency = $currencyId ? Currency::find($currencyId) : null;

        return [
            'amount_original_currency' => round($amount, 4),
            'currency_id' => $currencyId,
            'currency_code' => $currency?->code,
            'exchange_rate' => $exchangeRate,
            'amount_idr' => round($amount * $exchangeRate, 2),
        ];
    }

    /**
     * Resolve a purchase receipt item's unit cost in IDR for ledger and stock valuation.
     *
     * @return array{raw_unit_price: float, currency_id: ?int, currency_code: ?string, exchange_rate: float, unit_price_idr: float}
     */
    public static function resolvePurchaseReceiptItemUnitCost(PurchaseReceiptItem $item): array
    {
        $item->loadMissing([
            'purchaseOrderItem.currency',
            'product',
            'purchaseReceipt.currency',
            'purchaseReceipt.purchaseOrder.purchaseOrderCurrency.currency',
        ]);

        $poItem = $item->purchaseOrderItem
            ?? $item->purchaseReceipt?->purchaseOrder?->purchaseOrderItem?->firstWhere('product_id', $item->product_id);
        // Biaya perolehan (PSAK 14) = harga PO setelah diskon, tanpa PPN (DPP) — sama dengan baris faktur
        // (PurchaseInvoiceAccountingService::buildReceiptBackedInvoiceItem), sehingga 2100.10 bersih setelah faktur.
        $rawUnitPrice = $poItem
            ? self::purchaseOrderItemDppUnitPrice($poItem)
            : (float) ($item->product?->cost_price ?? $item->product?->purchase_price ?? 0);
        $currencyId = is_numeric($poItem?->currency_id ?? null)
            ? (int) $poItem->currency_id
            : null;

        if (! $currencyId && is_numeric($item->purchaseReceipt?->currency_id ?? null)) {
            $currencyId = (int) $item->purchaseReceipt->currency_id;
        }

        $poCurrency = $currencyId
            ? $item->purchaseReceipt?->purchaseOrder?->purchaseOrderCurrency?->firstWhere('currency_id', $currencyId)
            : null;

        $resolved = self::resolve(
            $rawUnitPrice,
            $currencyId,
            is_numeric($poCurrency?->nominal) ? (float) $poCurrency->nominal : null
        );

        return [
            'raw_unit_price' => $rawUnitPrice,
            'currency_id' => $currencyId,
            'currency_code' => $resolved['currency_code'] ?? $poCurrency?->currency?->code ?? $item->purchaseReceipt?->currency?->code,
            'exchange_rate' => $resolved['exchange_rate'],
            // Presisi satuan dipertahankan (bukan 2 desimal) agar qty × harga = total baris faktur tanpa sisa sen di 2100.10.
            'unit_price_idr' => round($rawUnitPrice * $resolved['exchange_rate'], 6),
        ];
    }

    /**
     * Harga satuan DPP baris PO dalam mata uang PO: harga × (1 − diskon%), lalu dikeluarkan PPN-nya bila tipe pajak Inklusif.
     */
    public static function purchaseOrderItemDppUnitPrice(PurchaseOrderItem $poItem): float
    {
        $afterDiscount = (float) ($poItem->unit_price ?? 0) * (1 - ((float) ($poItem->discount ?? 0) / 100));
        $taxRate = (float) ($poItem->tax ?? 0);
        $taxType = strtolower(trim((string) ($poItem->tipe_pajak ?? 'eklusif')));

        if ($taxRate > 0 && in_array($taxType, ['inklusif', 'inclusive'], true)) {
            return $afterDiscount / (1 + ($taxRate / 100));
        }

        return $afterDiscount;
    }
}
