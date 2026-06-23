<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\CurrencyConversionResolver;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EditOrderRequest extends EditRecord
{
    protected static string $resource = OrderRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['orderRequestItem']) && is_array($data['orderRequestItem'])) {
            foreach ($data['orderRequestItem'] as &$item) {
                $item['tipe_pajak'] = OrderRequestResource::normalizeItemTaxType($item['tipe_pajak'] ?? null);
            }
            unset($item);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->created_by == null) {
            $data['created_by'] = Auth::user()->id;
        }

        return OrderRequestResource::mutateFormDataBeforeSave($data);
    }

    public function updateInlineOrderRequestItemField(string $itemKey, string $field, mixed $value): void
    {
        $items = data_get($this->data, 'orderRequestItem', []);

        if (! is_array($items) || ! array_key_exists($itemKey, $items)) {
            return;
        }

        $allowedFields = [
            'product_id',
            'supplier_id',
            'cabang_id',
            'quantity',
            'unit',
            'original_price',
            'unit_price',
            'discount',
            'tipe_pajak',
            'tax',
            'note',
            'required_date',
        ];

        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];
        $normalizedValue = is_string($value) ? trim($value) : $value;

        if (in_array($field, ['product_id', 'supplier_id', 'cabang_id'], true)) {
            $normalizedValue = filled($normalizedValue) && is_numeric($normalizedValue) ? (int) $normalizedValue : null;
        }

        if (in_array($field, ['quantity', 'discount', 'tax'], true)) {
            $normalizedValue = is_numeric($normalizedValue) ? (float) $normalizedValue : 0;
        }

        if ($field === 'tipe_pajak') {
            $normalizedValue = OrderRequestResource::normalizeItemTaxType((string) $normalizedValue);
        }

        $item[$field] = $normalizedValue;

        if ($field === 'product_id' && $normalizedValue) {
            $product = Product::withoutGlobalScope('product_cabang')->find($normalizedValue);

            if ($product) {
                $supplierId = $item['supplier_id'] ?? null;

                if ($supplierId && ! $product->suppliers()->where('suppliers.id', $supplierId)->exists()) {
                    $supplierId = null;
                }

                if (! $supplierId) {
                    $supplierId = OrderRequestResource::resolveProductSupplierId((int) $normalizedValue);
                }

                $supplierProduct = $supplierId
                    ? $product->suppliers()->where('suppliers.id', $supplierId)->first()
                    : null;

                $unitPrice = (float) ($supplierProduct?->pivot->supplier_price ?? $product->cost_price ?? 0);

                $item['supplier_id'] = $supplierId;
                $item['unit'] = $product->uom?->abbreviation ?? $product->uom?->name ?? '-';
                $item['cabang_id'] = $product->cabang_id ?? Supplier::find($supplierId)?->cabang_id ?? Auth::user()?->cabang_id;
                $item['original_price'] = OrderRequestResource::formatMoneyInputState($unitPrice);
                $item['unit_price'] = OrderRequestResource::formatMoneyInputState($unitPrice);
                $item['original_price_idr'] = number_format($unitPrice, 2, '.', '');
                $item['unit_price_idr'] = number_format($unitPrice, 2, '.', '');
            }
        }

        if ($field === 'supplier_id' && filled($normalizedValue) && filled($item['product_id'] ?? null)) {
            $product = Product::withoutGlobalScope('product_cabang')->find($item['product_id']);
            $supplierProduct = $product?->suppliers()->where('suppliers.id', $normalizedValue)->first();
            $unitPrice = (float) ($supplierProduct?->pivot->supplier_price ?? $product?->cost_price ?? 0);

            if ($unitPrice > 0) {
                $item['original_price'] = OrderRequestResource::formatMoneyInputState($unitPrice);
                $item['unit_price'] = OrderRequestResource::formatMoneyInputState($unitPrice);
                $item['original_price_idr'] = number_format($unitPrice, 2, '.', '');
                $item['unit_price_idr'] = number_format($unitPrice, 2, '.', '');
            }
        }

        if ($field === 'unit_price') {
            $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
            $item['unit_price_idr'] = OrderRequestResource::resolveOverrideAnchorFromInput($normalizedValue, $currencyId);
        }

        if ($field === 'original_price') {
            $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
            $item['original_price_idr'] = OrderRequestResource::resolveOverrideAnchorFromInput($normalizedValue, $currencyId);
        }

        if ($field === 'tax') {
            $item['tax'] = (float) $normalizedValue;
        }

        $item = OrderRequestResource::recalculateOrderRequestItemPreviewState($item);

        $items[$itemKey] = $item;
        $this->data['orderRequestItem'] = $items;
    }

    public function addInlineOrderRequestItem(): string
    {
        $items = data_get($this->data, 'orderRequestItem', []);
        $items = is_array($items) ? $items : [];

        do {
            $itemKey = 'inline-' . (string) Str::uuid();
        } while (array_key_exists($itemKey, $items));

        $currencyId = is_numeric(data_get($this->data, 'currency_id'))
            ? (int) data_get($this->data, 'currency_id')
            : CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');

        $newItem = [
            'product_id' => null,
            'supplier_id' => null,
            'cabang_id' => null,
            'quantity' => 1,
            'fulfilled_quantity' => 0,
            'unit' => '-',
            'original_price' => '0,00',
            'unit_price' => '0,00',
            'original_price_idr' => 0,
            'unit_price_idr' => 0,
            'discount' => 0,
            'discount_nominal' => '0,00',
            'tax' => 11,
            'tax_nominal' => '0,00',
            'tipe_pajak' => 'eklusif',
            'total' => '0,00',
            'total_cost' => '0,00',
            'subtotal' => '0,00',
            'note' => '',
            'required_date' => null,
            'currency_id' => $currencyId,
        ];

        $this->data['orderRequestItem'] = [$itemKey => $newItem] + $items;
        $this->data['_order_request_item_search'] = null;
        $this->data['_order_request_item_supplier_filter'] = null;
        $this->data['_order_request_item_cabang_filter'] = null;
        $this->data['_order_request_item_tax_filter'] = null;
        $this->data['_order_request_item_page'] = 1;

        return $itemKey;
    }

    public function removeInlineOrderRequestItem(string $itemKey): bool
    {
        $items = data_get($this->data, 'orderRequestItem', []);

        if (! is_array($items) || ! array_key_exists($itemKey, $items)) {
            return false;
        }

        if (count($items) <= 1) {
            Notification::make()
                ->warning()
                ->title('Item tidak dapat dihapus')
                ->body('Order Request harus memiliki setidaknya satu item.')
                ->send();

            return false;
        }

        $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];
        $itemId = filled($item['id'] ?? null) && is_numeric($item['id']) ? (int) $item['id'] : null;

        if ($itemId && $this->inlineOrderRequestItemIsLocked($itemId, $item)) {
            Notification::make()
                ->warning()
                ->title('Item tidak dapat dihapus')
                ->body('Item sudah dipakai pada proses pembelian.')
                ->send();

            return false;
        }

        unset($items[$itemKey]);

        $pageSize = (int) data_get($this->data, '_order_request_item_page_size', 10);
        $pageSize = in_array($pageSize, [10, 25, 50, 100], true) ? $pageSize : 10;
        $lastPage = max(1, (int) ceil(count($items) / $pageSize));
        $currentPage = max(1, (int) data_get($this->data, '_order_request_item_page', 1));

        if ($currentPage > $lastPage) {
            $this->data['_order_request_item_page'] = $lastPage;
        }

        $this->data['orderRequestItem'] = $items;

        Notification::make()
            ->success()
            ->title('Item dihapus dari form')
            ->body('Klik Simpan untuk menyimpan perubahan Order Request.')
            ->send();

        return true;
    }

    protected function inlineOrderRequestItemIsLocked(int $itemId, array $item): bool
    {
        if ((float) ($item['fulfilled_quantity'] ?? 0) > 0) {
            return true;
        }

        $orderRequestItem = OrderRequestItem::withoutGlobalScopes()->find($itemId);

        if ($orderRequestItem && (float) ($orderRequestItem->fulfilled_quantity ?? 0) > 0) {
            return true;
        }

        return PurchaseOrderItem::withoutGlobalScopes()
            ->where('refer_item_model_type', OrderRequestItem::class)
            ->where('refer_item_model_id', $itemId)
            ->exists();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
