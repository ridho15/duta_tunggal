<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Helpers\MoneyHelper;
use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SaleOrder;
use App\Services\SalesInvoiceTaxResolver;
use App\Support\CurrencyConversionResolver;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Enforce branch inheritance from Sale Order
        $saleOrderId = $data['selected_sale_order'] ?? $data['from_model_id'] ?? null;
        if (!empty($saleOrderId)) {
            $saleOrder = SaleOrder::find($saleOrderId);
            if ($saleOrder && !empty($saleOrder->cabang_id)) {
                $data['cabang_id'] = $saleOrder->cabang_id;
            }

            if ($saleOrder) {
                $data['currency_id'] = $saleOrder->currency_id ?? null;
                $data['exchange_rate'] = (float) ($saleOrder->exchange_rate ?? CurrencyConversionResolver::resolveRate(is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null));
            }

            if ($saleOrder) {
                $taxData = app(SalesInvoiceTaxResolver::class)->resolveFromSaleOrder($saleOrder);
                $data['tax'] = $taxData['tax'];
                $data['ppn_rate'] = $taxData['ppn_rate'];
                $data['tipe_pajak'] = $taxData['tipe_pajak'];
            }
        }

        // Remove temporary fields
        unset($data['selected_customer']);
        unset($data['selected_sale_order']);
        unset($data['selected_delivery_orders']);

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
        $data['exchange_rate'] = (float) ($data['exchange_rate'] ?? 1.0);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Create invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            foreach ($this->data['invoiceItem'] as $item) {
                // Calculate subtotal if not provided
                $quantity = (float) ($item['quantity'] ?? 0);
                $price = (float) MoneyHelper::safeParse($item['price'] ?? 0);
                $subtotal = $quantity * $price;
                
                $itemData = array_merge($item, [
                    'price' => $price,
                    'subtotal' => (float) MoneyHelper::safeParse($item['subtotal'] ?? $subtotal),
                    'discount' => (float) MoneyHelper::safeParse($item['discount'] ?? 0),
                    'tax_rate' => (float) MoneyHelper::safeParse($item['tax_rate'] ?? 0),
                    'tax_amount' => (float) MoneyHelper::safeParse($item['tax_amount'] ?? 0),
                    'total' => (float) MoneyHelper::safeParse($item['total'] ?? $subtotal),
                ]);
                
                $this->record->invoiceItem()->create($itemData);
            }
        }

        // Post journal entries for sales invoice
        if ($this->record->from_model_type === 'App\Models\SaleOrder') {
            $invoiceObserver = new \App\Observers\InvoiceObserver();
            $invoiceObserver->postSalesInvoice($this->record);
        }
    }
}
