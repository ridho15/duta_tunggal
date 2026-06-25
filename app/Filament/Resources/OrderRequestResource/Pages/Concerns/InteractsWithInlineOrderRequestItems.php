<?php

namespace App\Filament\Resources\OrderRequestResource\Pages\Concerns;

use App\Filament\Resources\OrderRequestResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\CurrencyConversionResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait InteractsWithInlineOrderRequestItems
{
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
            'currency_id',
            'quantity',
            'unit_price',
            'discount',
            'tipe_pajak',
            'note',
        ];

        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        $item = $this->applyInlineOrderRequestItemFieldUpdate(
            is_array($items[$itemKey]) ? $items[$itemKey] : [],
            $field,
            $value
        );

        if ($item === null) {
            return;
        }

        $items[$itemKey] = $item;
        $this->data['orderRequestItem'] = $items;
    }

    protected function applyInlineOrderRequestItemFieldUpdate(array $item, string $field, mixed $value): ?array
    {
        $allowedFields = [
            'product_id',
            'supplier_id',
            'cabang_id',
            'currency_id',
            'quantity',
            'unit_price',
            'discount',
            'tipe_pajak',
            'note',
        ];

        if (! in_array($field, $allowedFields, true)) {
            return null;
        }

        if (OrderRequestItem::normalizeApprovalStatus($item['status'] ?? null) !== OrderRequestItem::STATUS_DRAFT) {
            Notification::make()
                ->warning()
                ->title('Item tidak dapat diedit')
                ->body('Item yang sudah approved atau rejected harus dikembalikan ke Draft sebelum diedit.')
                ->send();

            return null;
        }

        $normalizedValue = is_string($value) ? trim($value) : $value;

        if (in_array($field, ['product_id', 'supplier_id', 'cabang_id', 'currency_id'], true)) {
            $normalizedValue = filled($normalizedValue) && is_numeric($normalizedValue) ? (int) $normalizedValue : null;
        }

        if (in_array($field, ['quantity', 'discount'], true)) {
            $normalizedValue = is_numeric($normalizedValue) ? (float) $normalizedValue : 0;
        }

        if ($field === 'tipe_pajak') {
            $normalizedValue = OrderRequestResource::normalizeItemTaxType((string) $normalizedValue);
        }

        $oldCurrencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
        $item[$field] = $normalizedValue;

        if ($field === 'product_id' && $normalizedValue) {
            $product = Product::withoutGlobalScope('product_cabang')->with('uom')->find($normalizedValue);

            if ($product) {
                $supplierId = OrderRequestResource::resolveProductSupplierId((int) $normalizedValue);

                $supplierProduct = $supplierId
                    ? $product->suppliers()->where('suppliers.id', $supplierId)->first()
                    : null;

                $rawIdrPrice = (float) ($supplierProduct?->pivot->supplier_price ?? $product->cost_price ?? 0);
                $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
                $displayPrice = OrderRequestResource::convertIdrToCurrency($rawIdrPrice, $currencyId, false);

                $item['supplier_id'] = $supplierId;
                $item['unit'] = $product->uom?->abbreviation ?? $product->uom?->name ?? '-';
                $item['cabang_id'] = $product->cabang_id ?? Supplier::find($supplierId)?->cabang_id ?? Auth::user()?->cabang_id;
                $item['original_price'] = OrderRequestResource::formatMoneyInputStateForCurrency($displayPrice, $currencyId);
                $item['unit_price'] = OrderRequestResource::formatMoneyInputStateForCurrency($displayPrice, $currencyId);
                $item['original_price_idr'] = number_format($rawIdrPrice, 2, '.', '');
                $item['unit_price_idr'] = number_format($rawIdrPrice, 2, '.', '');
                $item['tax'] = OrderRequestResource::resolveItemTaxRate(
                    (int) $normalizedValue,
                    $item['tipe_pajak'] ?? 'eklusif'
                );
            }
        }

        if ($field === 'supplier_id' && filled($normalizedValue) && filled($item['product_id'] ?? null)) {
            $product = Product::withoutGlobalScope('product_cabang')->find($item['product_id']);
            $supplierProduct = $product?->suppliers()->where('suppliers.id', $normalizedValue)->first();
            $rawIdrPrice = (float) ($supplierProduct?->pivot->supplier_price ?? $product?->cost_price ?? 0);

            if ($rawIdrPrice > 0) {
                $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
                $displayPrice = OrderRequestResource::convertIdrToCurrency($rawIdrPrice, $currencyId, false);
                $item['original_price'] = OrderRequestResource::formatMoneyInputStateForCurrency($displayPrice, $currencyId);
                $item['unit_price'] = OrderRequestResource::formatMoneyInputStateForCurrency($displayPrice, $currencyId);
                $item['original_price_idr'] = number_format($rawIdrPrice, 2, '.', '');
                $item['unit_price_idr'] = number_format($rawIdrPrice, 2, '.', '');
            }
        }

        if ($field === 'currency_id') {
            $newCurrencyId = is_numeric($normalizedValue) ? (int) $normalizedValue : null;
            $unitPriceIdr = (float) ($item['unit_price_idr'] ?? 0);
            $originalPriceIdr = (float) ($item['original_price_idr'] ?? 0);

            if ($unitPriceIdr <= 0) {
                $unitPriceIdr = CurrencyConversionResolver::convertToIdr(
                    OrderRequestResource::parseCurrencyState($item['unit_price'] ?? 0),
                    $oldCurrencyId,
                    false
                );
            }

            if ($originalPriceIdr <= 0) {
                $originalPriceIdr = CurrencyConversionResolver::convertToIdr(
                    OrderRequestResource::parseCurrencyState($item['original_price'] ?? 0),
                    $oldCurrencyId,
                    false
                );
            }

            $item['unit_price_idr'] = number_format($unitPriceIdr, 2, '.', '');
            $item['original_price_idr'] = number_format($originalPriceIdr, 2, '.', '');
            $item['unit_price'] = OrderRequestResource::formatMoneyInputStateForCurrency(
                OrderRequestResource::convertIdrToCurrency($unitPriceIdr, $newCurrencyId, false),
                $newCurrencyId
            );
            $item['original_price'] = OrderRequestResource::formatMoneyInputStateForCurrency(
                OrderRequestResource::convertIdrToCurrency($originalPriceIdr, $newCurrencyId, false),
                $newCurrencyId
            );
        }

        if ($field === 'unit_price') {
            $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
            $item['unit_price_idr'] = OrderRequestResource::resolveOverrideAnchorFromInput($normalizedValue, $currencyId);
        }

        if ($field === 'tipe_pajak') {
            $item['tax'] = OrderRequestResource::resolveItemTaxRate(
                is_numeric($item['product_id'] ?? null) ? (int) $item['product_id'] : null,
                (string) $normalizedValue
            );
        }

        $item = OrderRequestResource::recalculateOrderRequestItemPreviewState($item);

        return $item;
    }

    public function searchInlineOrderRequestProducts(string $search = ''): array
    {
        $this->skipRender();

        return collect(OrderRequestResource::resolveProductOptions($search, 50))
            ->map(fn (string $text, int|string $id) => ['id' => (string) $id, 'text' => $text])
            ->values()
            ->all();
    }

    public function searchInlineOrderRequestSuppliers(
        ?int $productId = null,
        ?int $currencyId = null,
        string $search = ''
    ): array {
        $this->skipRender();

        return collect(OrderRequestResource::resolveSupplierOptions($productId, $search, 50, $currencyId))
            ->map(fn (string $text, int|string $id) => ['id' => (string) $id, 'text' => $text])
            ->values()
            ->all();
    }

    public function searchInlineOrderRequestCabangs(string $search = ''): array
    {
        $this->skipRender();

        $user = Auth::user();
        $manageType = $user?->manage_type ?? [];
        $query = Cabang::query();

        if (! (is_array($manageType) && in_array('all', $manageType, true))) {
            $query->whereKey($user?->cabang_id);
        }

        if ($search !== '') {
            $query->where(function ($cabangQuery) use ($search) {
                $cabangQuery->where('kode', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('kode')
            ->limit(50)
            ->get(['id', 'kode', 'nama'])
            ->map(fn (Cabang $cabang) => [
                'id' => (string) $cabang->id,
                'text' => "({$cabang->kode}) {$cabang->nama}",
            ])
            ->values()
            ->all();
    }

    public function searchInlineOrderRequestCurrencies(string $search = ''): array
    {
        $this->skipRender();

        return Currency::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($currencyQuery) use ($search) {
                    $currencyQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                });
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
            'status' => OrderRequestItem::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
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

    public function bulkUpdateInlineOrderRequestItemStatus(array $itemKeys, string $status, ?string $rejectionNote = null): void
    {
        $items = data_get($this->data, 'orderRequestItem', []);

        if (! is_array($items)) {
            return;
        }

        $status = OrderRequestItem::normalizeApprovalStatus($status);
        $itemKeys = collect($itemKeys)
            ->map(fn ($key) => (string) $key)
            ->filter(fn (string $key) => array_key_exists($key, $items))
            ->unique()
            ->values();

        if ($itemKeys->isEmpty()) {
            return;
        }

        $note = trim((string) $rejectionNote);
        if ($status === OrderRequestItem::STATUS_REJECTED && $note === '') {
            Notification::make()
                ->danger()
                ->title('Alasan reject wajib diisi')
                ->body('Isi alasan reject sebelum menolak item yang dipilih.')
                ->send();

            return;
        }

        $now = now()->toDateTimeString();
        $userId = Auth::id();

        foreach ($itemKeys as $itemKey) {
            $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];

            if ($status === OrderRequestItem::STATUS_APPROVED) {
                $item['status'] = OrderRequestItem::STATUS_APPROVED;
                $item['approved_by'] = $userId;
                $item['approved_at'] = $now;
                $item['rejected_by'] = null;
                $item['rejected_at'] = null;
                $item['rejection_note'] = null;
            } elseif ($status === OrderRequestItem::STATUS_REJECTED) {
                $item['status'] = OrderRequestItem::STATUS_REJECTED;
                $item['approved_by'] = null;
                $item['approved_at'] = null;
                $item['rejected_by'] = $userId;
                $item['rejected_at'] = $now;
                $item['rejection_note'] = $note;
            } else {
                $item['status'] = OrderRequestItem::STATUS_DRAFT;
                $item['approved_by'] = null;
                $item['approved_at'] = null;
                $item['rejected_by'] = null;
                $item['rejected_at'] = null;
                $item['rejection_note'] = null;
            }

            $items[$itemKey] = $item;
        }

        $this->data['orderRequestItem'] = $items;

        Notification::make()
            ->success()
            ->title('Status item diperbarui')
            ->body(number_format($itemKeys->count(), 0, ',', '.') . ' item berhasil diperbarui.')
            ->send();
    }

    public function updateInlineOrderRequestItemStatus(string $itemKey, string $status, ?string $rejectionNote = null): void
    {
        $this->bulkUpdateInlineOrderRequestItemStatus([$itemKey], $status, $rejectionNote);
    }

    public function bulkUpdateInlineOrderRequestItemSupplier(array $itemKeys, mixed $supplierId): void
    {
        $items = data_get($this->data, 'orderRequestItem', []);

        if (! is_array($items)) {
            return;
        }

        $supplierId = filled($supplierId) && is_numeric($supplierId) ? (int) $supplierId : null;
        if (! $supplierId || ! Supplier::query()->whereKey($supplierId)->exists()) {
            Notification::make()
                ->warning()
                ->title('Supplier belum dipilih')
                ->body('Pilih supplier target sebelum menjalankan bulk set supplier.')
                ->send();

            return;
        }

        $itemKeys = $this->normalizeInlineOrderRequestItemKeys($itemKeys, $items);
        if ($itemKeys->isEmpty()) {
            return;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($itemKeys as $itemKey) {
            $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];

            if (
                OrderRequestItem::normalizeApprovalStatus($item['status'] ?? null) !== OrderRequestItem::STATUS_DRAFT
                || ! filled($item['product_id'] ?? null)
            ) {
                $skipped++;
                continue;
            }

            $updatedItem = $this->applyInlineOrderRequestItemFieldUpdate($item, 'supplier_id', $supplierId);
            if ($updatedItem === null) {
                $skipped++;
                continue;
            }

            $items[$itemKey] = $updatedItem;
            $updated++;
        }

        $this->data['orderRequestItem'] = $items;
        $this->notifyInlineBulkFieldUpdate('Supplier item diperbarui', $updated, $skipped);
    }

    public function bulkUpdateInlineOrderRequestItemCabang(array $itemKeys, mixed $cabangId): void
    {
        $items = data_get($this->data, 'orderRequestItem', []);

        if (! is_array($items)) {
            return;
        }

        $cabangId = filled($cabangId) && is_numeric($cabangId) ? (int) $cabangId : null;
        if (! $cabangId || ! $this->inlineOrderRequestCabangIsAllowed($cabangId)) {
            Notification::make()
                ->warning()
                ->title('Cabang tidak dapat dipakai')
                ->body('Pilih cabang target yang tersedia untuk user ini.')
                ->send();

            return;
        }

        $itemKeys = $this->normalizeInlineOrderRequestItemKeys($itemKeys, $items);
        if ($itemKeys->isEmpty()) {
            return;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($itemKeys as $itemKey) {
            $item = is_array($items[$itemKey]) ? $items[$itemKey] : [];

            if (OrderRequestItem::normalizeApprovalStatus($item['status'] ?? null) !== OrderRequestItem::STATUS_DRAFT) {
                $skipped++;
                continue;
            }

            $updatedItem = $this->applyInlineOrderRequestItemFieldUpdate($item, 'cabang_id', $cabangId);
            if ($updatedItem === null) {
                $skipped++;
                continue;
            }

            $items[$itemKey] = $updatedItem;
            $updated++;
        }

        $this->data['orderRequestItem'] = $items;
        $this->notifyInlineBulkFieldUpdate('Cabang item diperbarui', $updated, $skipped);
    }

    protected function normalizeInlineOrderRequestItemKeys(array $itemKeys, array $items): \Illuminate\Support\Collection
    {
        return collect($itemKeys)
            ->map(fn ($key) => (string) $key)
            ->filter(fn (string $key) => array_key_exists($key, $items))
            ->unique()
            ->values();
    }

    protected function inlineOrderRequestCabangIsAllowed(int $cabangId): bool
    {
        $user = Auth::user();
        $manageType = $user?->manage_type ?? [];
        $query = Cabang::query()->whereKey($cabangId);

        if (! (is_array($manageType) && in_array('all', $manageType, true))) {
            $query->whereKey($user?->cabang_id);
        }

        return $query->exists();
    }

    protected function notifyInlineBulkFieldUpdate(string $title, int $updated, int $skipped): void
    {
        $body = number_format($updated, 0, ',', '.') . ' item draft berhasil diperbarui.';

        if ($skipped > 0) {
            $body .= ' ' . number_format($skipped, 0, ',', '.') . ' item dilewati karena terkunci atau belum punya product.';
        }

        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->send();
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
            ->body($this->inlineOrderRequestItemDeletedNotificationBody())
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

    protected function inlineOrderRequestItemDeletedNotificationBody(): string
    {
        if (method_exists($this, 'getRecord') && $this->getRecord()?->exists) {
            return 'Klik Simpan untuk menyimpan perubahan Order Request.';
        }

        return 'Klik Simpan untuk menyimpan Order Request.';
    }
}

