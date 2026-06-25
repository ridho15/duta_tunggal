<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages\Concerns;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Currency;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Support\CurrencyConversionResolver;
use App\Support\TaxTypeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait InteractsWithInlinePurchaseOrderItems
{
    public function updateInlinePurchaseOrderItemField(string $itemKey, string $field, mixed $value): void
    {
        $items = data_get($this->data, 'purchaseOrderItem', []);

        if (! is_array($items) || ! array_key_exists($itemKey, $items)) {
            return;
        }

        $allowedFields = ['product_id', 'currency_id', 'quantity', 'unit_price', 'discount', 'tipe_pajak'];

        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];
        $oldCurrencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
        $isOrderRequestBacked = ($item['refer_item_model_type'] ?? null) === OrderRequestItem::class
            || filled($item['refer_item_model_id'] ?? null);

        if ($isOrderRequestBacked && in_array($field, ['product_id', 'currency_id', 'unit_price', 'discount', 'tipe_pajak'], true)) {
            return;
        }

        $normalizedValue = is_string($value) ? trim($value) : $value;

        if (in_array($field, ['product_id', 'currency_id'], true)) {
            $normalizedValue = filled($normalizedValue) && is_numeric($normalizedValue) ? (int) $normalizedValue : null;
        }

        if (in_array($field, ['quantity', 'discount'], true)) {
            $normalizedValue = is_numeric($normalizedValue) ? (float) $normalizedValue : 0.0;
        }

        if ($field === 'unit_price') {
            $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
            $normalizedValue = PurchaseOrderResource::formatPurchaseOrderCurrencyInputState(
                PurchaseOrderResource::parsePurchaseOrderCurrencyState($normalizedValue),
                $currencyId
            );
        }

        if ($field === 'tipe_pajak') {
            $normalizedValue = PurchaseOrderResource::normalizeTaxTypeValue((string) $normalizedValue);
        }

        if ($field === 'quantity' && $isOrderRequestBacked && is_numeric($item['refer_item_model_id'] ?? null)) {
            $limit = PurchaseOrderResource::orderRequestItemResourceLimit(
                (int) $item['refer_item_model_id'],
                is_numeric($item['id'] ?? null) ? (int) $item['id'] : null
            );
            $normalizedValue = min((float) $normalizedValue, (float) ($limit['remaining_for_po_resource'] ?? $normalizedValue));
        }

        $item[$field] = $normalizedValue;

        if ($field === 'product_id' && $normalizedValue) {
            $this->hydrateInlinePurchaseOrderProductFields($item, (int) $normalizedValue);
        }

        if ($field === 'currency_id') {
            $this->convertInlinePurchaseOrderItemCurrency($item, $oldCurrencyId, (int) ($normalizedValue ?: 0));
        }

        if ($field === 'tipe_pajak') {
            $item['tax'] = $normalizedValue === 'none' ? 0 : \App\Models\TaxSetting::activeRate('PPN');
        }

        $item = PurchaseOrderResource::recalculatePurchaseOrderItemPreviewState($item);
        $items[$itemKey] = $item;

        $this->data['purchaseOrderItem'] = $items;
        $this->syncInlinePurchaseOrderTotalsAndCurrencies();
    }

    public function addInlinePurchaseOrderItem(): string
    {
        $items = data_get($this->data, 'purchaseOrderItem', []);
        $items = is_array($items) ? $items : [];

        do {
            $itemKey = 'inline-' . (string) Str::uuid();
        } while (array_key_exists($itemKey, $items));

        $currencyId = Currency::query()->where('code', 'IDR')->value('id') ?? Currency::query()->value('id');
        $tax = \App\Models\TaxSetting::activeRate('PPN');

        $newItem = PurchaseOrderResource::recalculatePurchaseOrderItemPreviewState([
            'refer_item_model_type' => null,
            'refer_item_model_id' => null,
            'product_id' => null,
            'quantity' => 1,
            'unit' => '-',
            'currency_id' => $currencyId,
            'unit_price' => '0,00',
            'discount' => 0,
            'tax' => $tax,
            'tipe_pajak' => TaxTypeHelper::normalize('inklusif'),
        ]);

        $this->data['purchaseOrderItem'] = [$itemKey => $newItem] + $items;
        $this->data['_purchase_order_item_search'] = null;
        $this->data['_purchase_order_item_tax_filter'] = null;
        $this->data['_purchase_order_item_source_filter'] = null;
        $this->data['_purchase_order_item_cabang_filter'] = null;

        $this->syncInlinePurchaseOrderTotalsAndCurrencies();

        return $itemKey;
    }

    public function removeInlinePurchaseOrderItem(string $itemKey): bool
    {
        $items = data_get($this->data, 'purchaseOrderItem', []);

        if (! is_array($items) || ! array_key_exists($itemKey, $items)) {
            return false;
        }

        $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];
        $isOrderRequestBacked = ($item['refer_item_model_type'] ?? null) === OrderRequestItem::class
            || filled($item['refer_item_model_id'] ?? null);

        if ($isOrderRequestBacked) {
            return false;
        }

        unset($items[$itemKey]);
        $this->data['purchaseOrderItem'] = $items;
        $this->syncInlinePurchaseOrderTotalsAndCurrencies();

        return true;
    }

    public function searchInlinePurchaseOrderProducts(string $search = ''): array
    {
        $this->skipRender();

        return Product::withoutGlobalScope('product_cabang')
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'sku', 'name'])
            ->map(fn (Product $product) => [
                'id' => (string) $product->id,
                'text' => "({$product->sku}) {$product->name}",
            ])
            ->values()
            ->all();
    }

    public function searchInlinePurchaseOrderCurrencies(string $search = ''): array
    {
        $this->skipRender();

        return Currency::query()
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'symbol'])
            ->map(fn (Currency $currency) => [
                'id' => (string) $currency->id,
                'text' => trim("{$currency->name} ({$currency->code} / {$currency->symbol})"),
            ])
            ->values()
            ->all();
    }

    protected function hydrateInlinePurchaseOrderProductFields(array &$item, int $productId): void
    {
        $product = Product::withoutGlobalScope('product_cabang')->with('uom', 'suppliers')->find($productId);

        if (! $product) {
            return;
        }

        $supplierId = data_get($this->data, 'supplier_id');
        $rawUnitPrice = (float) ($product->cost_price ?? 0);

        if ($supplierId) {
            $supplierProduct = $product->suppliers()->where('suppliers.id', $supplierId)->first();
            if ($supplierProduct && (float) ($supplierProduct->pivot->supplier_price ?? 0) > 0) {
                $rawUnitPrice = (float) $supplierProduct->pivot->supplier_price;
            }
        }

        $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
        $displayPrice = CurrencyConversionResolver::convertFromIdr($rawUnitPrice, $currencyId, false);

        $item['unit'] = $product->uom?->abbreviation ?? $product->uom?->name ?? '-';
        $item['unit_price'] = PurchaseOrderResource::formatPurchaseOrderCurrencyInputState($displayPrice, $currencyId);
        $item['refer_item_model_type'] = null;
        $item['refer_item_model_id'] = null;
    }

    protected function convertInlinePurchaseOrderItemCurrency(array &$item, ?int $oldCurrencyId, int $newCurrencyId): void
    {
        $currentUnitPrice = PurchaseOrderResource::parsePurchaseOrderCurrencyState($item['unit_price'] ?? 0);
        $converted = CurrencyConversionResolver::convertBetweenCurrencies(
            $currentUnitPrice,
            $oldCurrencyId,
            $newCurrencyId ?: null,
            false
        );

        $item['unit_price'] = PurchaseOrderResource::formatPurchaseOrderCurrencyInputState($converted, $newCurrencyId ?: null);
    }

    protected function syncInlinePurchaseOrderTotalsAndCurrencies(): void
    {
        $data = PurchaseOrderResource::syncPurchaseOrderCurrencyData($this->data);

        $this->data['purchaseOrderCurrency'] = $data['purchaseOrderCurrency'] ?? [];
        $this->data['total_amount'] = PurchaseOrderResource::calculateInlinePurchaseOrderTotalAmount(
            $this->data['purchaseOrderItem'] ?? [],
            $this->data['purchaseOrderCurrency'] ?? []
        );

        if (blank(data_get($this->data, 'cabang_id'))) {
            $this->data['cabang_id'] = Auth::user()?->cabang_id;
        }
    }
}
