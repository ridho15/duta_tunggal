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
     * Izinkan akses edit hanya untuk status draft, ATAU Super Admin untuk koreksi darurat.
     * Mencegah bypass via URL langsung (mis. /admin/sales-invoices/1/edit).
     */
    public function authorizeAccess(): void
    {
        $record = $this->getRecord();
        $isDraft = strtolower((string) $record->status) === \App\Models\Invoice::STATUS_DRAFT;
        $isSuperAdmin = (bool) auth()->user()?->hasRole('Super Admin');

        if (! $isDraft && ! $isSuperAdmin) {
            \Filament\Notifications\Notification::make()
                ->title('Invoice tidak dapat diedit')
                ->body('Invoice dengan status "' . (\App\Models\Invoice::STATUS_LABELS[$record->status] ?? $record->status) . '" telah diposting dan terkunci. Hanya Super Admin yang berhak melakukan koreksi darurat.')
                ->danger()
                ->send();

            $this->redirect(SalesInvoiceResource::getUrl('view', ['record' => $record]));
            return;
        }

        parent::authorizeAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => strtolower((string) $this->record->status) === \App\Models\Invoice::STATUS_DRAFT),
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
        $isDraft = strtolower((string) $this->record->status) === \App\Models\Invoice::STATUS_DRAFT;
        $isSuperAdmin = (bool) auth()->user()?->hasRole('Super Admin');

        if (! $isDraft && ! $isSuperAdmin) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Invoice yang sudah diposting hanya dapat diedit oleh Super Admin.');
        }

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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
            $isNonDraft = strtolower((string) $record->status) !== \App\Models\Invoice::STATUS_DRAFT;
            $oldOriginal = $record->getOriginal();

            // Rebuild item baris lebih dulu sebelum header disimpan agar saat observer update terpicu,
            // baris item di DB sudah selaras dengan header dan balance check jurnal langsung valid
            if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
                $record->invoiceItem()->delete();

                // Set atribut form sementara di model memory agar builder menggunakan tax_rate & tipe terbaru
                $record->fill($data);

                $built = app(\App\Services\SalesInvoiceLineBuilder::class)->fromFormItems($record, $this->data['invoiceItem']);
                foreach ($built['items'] as $itemData) {
                    $record->invoiceItem()->create($itemData);
                }

                if ($built['matched'] && $built['items'] !== []) {
                    $items = collect($built['items']);
                    $calculatedSubtotal = round((float) $items->sum('subtotal'), 2);
                    $data['subtotal'] = $calculatedSubtotal;
                    $data['dpp'] = $calculatedSubtotal;
                    $data['total'] = round((float) $items->sum('total') + app(\App\Services\SalesInvoiceLineBuilder::class)->otherFeeTotal($record), 2);
                }
            }

            // Simpan header dengan nilai total & PPN yang sudah seimbang dengan line items
            $record->update($data);

            // Audit trail darurat untuk Super Admin saat mengedit invoice non-draft
            if ($isNonDraft && auth()->user()?->hasRole('Super Admin')) {
                activity('emergency_invoice_override')
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'invoice_number' => $record->invoice_number,
                        'status' => $record->status,
                        'old' => array_intersect_key($oldOriginal, $record->getChanges()),
                        'attributes' => $record->getChanges(),
                    ])
                    ->log('Super Admin melakukan koreksi darurat pada invoice terposting ' . $record->invoice_number);
            }

            return $record;
        });
    }
}
