<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Helpers\MoneyHelper;
use App\Filament\Resources\SalesInvoiceResource;
use App\Models\DeliveryOrder;
use App\Support\CurrencyConversionResolver;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    /**
     * FIX #2: Blokir akses halaman edit jika invoice sudah final.
     * Mencegah bypass via URL langsung (mis. /admin/sales-invoices/1/edit).
     */
    public function authorizeAccess(): void
    {
        parent::authorizeAccess();

        $record = $this->getRecord();
        $lockedStatuses = [
            \App\Models\Invoice::STATUS_PAID,
            \App\Models\Invoice::STATUS_PARTIALLY_PAID,
            \App\Models\Invoice::STATUS_OVERDUE,
            \App\Models\Invoice::STATUS_CANCELLED,
        ];

        if (in_array($record->status, $lockedStatuses)) {
            \Filament\Notifications\Notification::make()
                ->title('Invoice tidak dapat diedit')
                ->body('Invoice dengan status "' . (\App\Models\Invoice::STATUS_LABELS[$record->status] ?? $record->status) . '" tidak dapat diubah. Gunakan Nota Kredit atau Pembatalan.')
                ->danger()
                ->send();

            $this->redirect(SalesInvoiceResource::getUrl('view', ['record' => $record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            // FIX #2: Hapus hanya untuk invoice yang belum final
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => !in_array($this->record->status, [
                    \App\Models\Invoice::STATUS_PAID,
                    \App\Models\Invoice::STATUS_PARTIALLY_PAID,
                    \App\Models\Invoice::STATUS_OVERDUE,
                    \App\Models\Invoice::STATUS_CANCELLED,
                ])),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['tipe_pajak'] = \App\Filament\Resources\SalesInvoiceResource::normalizeInvoiceTaxTypeValue($data['tipe_pajak'] ?? null);

        // Load related data for form
        if ($this->record->from_model_type === 'App\Models\SaleOrder') {
            $data['selected_customer'] = $this->record->fromModel->customer_id ?? null;
            $data['selected_sale_order'] = $this->record->from_model_id ?? null;
            $data['selected_delivery_orders'] = $this->record->delivery_orders ?? [];
        }

        $data['currency_id'] = $this->record->currency_id ?? $this->record->fromModel?->currency_id;
        $data['exchange_rate'] = (float) ($this->record->exchange_rate ?? CurrencyConversionResolver::resolveRate(is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null));

        // Load invoice items
        $this->record->load('invoiceItem.product');
        $data['invoiceItem'] = $this->record->invoiceItem->map(function ($item) {
            $item->setRelation('invoice', $this->record);
            $b = $item->breakdown();

            return array_merge($item->toArray(), [
                'bd_gross' => \App\Support\LineAmounts::money($b['gross']),
                'bd_discount' => number_format($b['discount_pct'], 2, ',', '.') . '% = ' . \App\Support\LineAmounts::money($b['discount_amount']),
                'bd_dpp' => \App\Support\LineAmounts::money($b['dpp']),
                'bd_ppn' => number_format($b['tax_rate'], 2, ',', '.') . '% = ' . \App\Support\LineAmounts::money($b['ppn']),
            ]);
        })->all();

        // Load other_fees from the other_fee column (always ensure it's an array)
        $rawOtherFee = $this->record->getAttributes()['other_fee'] ?? null;
        $decoded = ($rawOtherFee !== null && $rawOtherFee !== '') ? @json_decode($rawOtherFee, true) : null;
        $data['other_fees'] = is_array($decoded) ? $decoded : [];

        // Ensure delivery_order_items defaults to empty array
        $data['delivery_order_items'] = [];

        // Set delivery_order_items from invoice items and delivery orders
        if (isset($data['delivery_orders']) && is_array($data['delivery_orders']) && isset($data['invoiceItem'])) {
            $deliveryOrders = DeliveryOrder::with('deliveryOrderItem.product', 'deliveryOrderItem.saleOrderItem')
                ->whereIn('id', $data['delivery_orders'])
                ->get();
            
            $deliveryOrderItems = [];
            foreach ($deliveryOrders as $do) {
                foreach ($do->deliveryOrderItem as $item) {
                    if ($item->product && $item->saleOrderItem) {
                        $originalPrice = $item->saleOrderItem->unit_price - $item->saleOrderItem->discount + $item->saleOrderItem->tax;
                        
                        // Find matching invoice item
                        $invoiceItem = collect($data['invoiceItem'])->first(function ($invItem) use ($item) {
                            return $invItem['product_id'] == $item->product_id;
                        });
                        
                        $deliveryOrderItems[] = [
                            'do_number' => $do->do_number,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name . ' (' . $item->product->sku . ')',
                            'original_quantity' => $item->quantity,
                            'invoice_quantity' => $invoiceItem['quantity'] ?? $item->quantity,
                            'original_price' => $originalPrice,
                            'unit_price' => $invoiceItem['price'] ?? $originalPrice,
                            'total_price' => ((float) ($invoiceItem['quantity'] ?? $item->quantity)) * ((float) ($invoiceItem['price'] ?? $originalPrice)),
                            'coa_id' => $invoiceItem['coa_id'] ?? $item->product->sales_coa_id,
                        ];
                    }
                }
            }
            
            if (!empty($deliveryOrderItems)) {
                $data['delivery_order_items'] = $deliveryOrderItems;
            }
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['tipe_pajak'] = \App\Filament\Resources\SalesInvoiceResource::normalizeInvoiceTaxTypeValue($data['tipe_pajak'] ?? null);

        // Remove temporary fields
        unset($data['selected_customer']);
        unset($data['selected_sale_order']);
        unset($data['selected_delivery_orders']);
        unset($data['delivery_order_items']);

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
        $data['exchange_rate'] = (float) ($data['exchange_rate'] ?? 1.0);
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            // Soft-delete existing items before recreating
            $this->record->invoiceItem()->delete();

            // Rincian baku (harga gross, diskon, DPP, PPN, total) — sama dengan jalur otomatis.
            $built = app(\App\Services\SalesInvoiceLineBuilder::class)->fromFormItems($this->record, $this->data['invoiceItem']);

            foreach ($built['items'] as $itemData) {
                $this->record->invoiceItem()->create($itemData);
            }

            if ($built['matched'] && $built['items'] !== []) {
                $items = collect($built['items']);
                $this->record->update([
                    'subtotal' => round((float) $items->sum('subtotal'), 2),
                    'dpp' => round((float) $items->sum('subtotal'), 2),
                    'total' => round((float) $items->sum('total') + app(\App\Services\SalesInvoiceLineBuilder::class)->otherFeeTotal($this->record), 2),
                ]);
            }
        }
    }
}
