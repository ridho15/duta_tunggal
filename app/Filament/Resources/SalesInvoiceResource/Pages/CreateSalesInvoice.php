<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Helpers\MoneyHelper;
use App\Filament\Resources\SalesInvoiceResource;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Services\SalesInvoiceTaxResolver;
use App\Support\CurrencyConversionResolver;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $selectedSjIds = $data['selected_surat_jalans'] ?? $data['surat_jalan_ids'] ?? [];
        if (! is_array($selectedSjIds)) {
            $selectedSjIds = array_filter([(int) $selectedSjIds]);
        }
        $selectedSjIds = array_values(array_filter($selectedSjIds));

        if (! empty($selectedSjIds)) {
            // Tahap 5: Invoice Refer to Surat Jalan (Boleh > 1 SJ)
            $sjs = SuratJalan::with([
                'deliveryOrder.salesOrders.customer',
                'deliveryOrder.deliveryOrderItem',
                'cabang',
            ])->whereIn('id', $selectedSjIds)->get();

            if ($sjs->count() !== count($selectedSjIds)) {
                throw ValidationException::withMessages([
                    'data.selected_surat_jalans' => 'Satu atau lebih Surat Jalan yang dipilih tidak ditemukan.',
                    'selected_surat_jalans' => 'Satu atau lebih Surat Jalan yang dipilih tidak ditemukan.',
                ]);
            }

            // 1. Validasi status Surat Jalan wajib Terbit (STATUS_ISSUED = 1)
            foreach ($sjs as $sj) {
                if ($sj->status !== SuratJalan::STATUS_ISSUED) {
                    $msg = "Surat Jalan {$sj->sj_number} berstatus " . ($sj->status_label ?? 'Draft/Batal') . ". Hanya Surat Jalan berstatus Terbit yang dapat dibuatkan invoice.";
                    throw ValidationException::withMessages([
                        'data.selected_surat_jalans' => $msg,
                        'selected_surat_jalans' => $msg,
                    ]);
                }
            }

            // 2. Validasi keseragaman Cabang
            $cabangIds = $sjs->pluck('cabang_id')->unique()->values();
            if ($cabangIds->count() > 1) {
                throw ValidationException::withMessages([
                    'data.selected_surat_jalans' => 'Penggabungan Surat Jalan hanya diizinkan untuk cabang yang sama.',
                    'selected_surat_jalans' => 'Penggabungan Surat Jalan hanya diizinkan untuk cabang yang sama.',
                ]);
            }

            // 3. Validasi keseragaman Customer
            $customerIds = $sjs->flatMap(function ($sj) {
                return $sj->deliveryOrder->flatMap(function ($do) {
                    return $do->salesOrders->pluck('customer_id');
                });
            })->filter()->unique()->values();

            if ($customerIds->count() > 1) {
                throw ValidationException::withMessages([
                    'data.selected_surat_jalans' => 'Penggabungan Surat Jalan hanya diizinkan untuk customer yang sama.',
                    'selected_surat_jalans' => 'Penggabungan Surat Jalan hanya diizinkan untuk customer yang sama.',
                ]);
            }

            // 3b. B24: SJ yang digabung dalam satu invoice harus dari Sales Order yang SAMA
            $saleOrderSets = $sjs->map(fn ($sj) => $sj->deliveryOrder->flatMap->salesOrders->pluck('id')->unique()->sort()->values()->implode(','))->unique();
            if ($saleOrderSets->count() > 1) {
                $msg = 'Surat Jalan dalam satu invoice harus berasal dari Sales Order yang sama.';
                throw ValidationException::withMessages([
                    'data.selected_surat_jalans' => $msg,
                    'selected_surat_jalans' => $msg,
                ]);
            }

            // 4. Validasi agar SJ yang sudah ditagih tidak dapat ditagih kembali
            foreach ($sjs as $sj) {
                if ($sj->isInvoiced()) {
                    $msg = "Surat Jalan {$sj->sj_number} sudah pernah difakturkan pada invoice aktif lain.";
                    throw ValidationException::withMessages([
                        'data.selected_surat_jalans' => $msg,
                        'selected_surat_jalans' => $msg,
                    ]);
                }
            }

            // Set atribut header invoice
            $firstSj = $sjs->first();
            $data['cabang_id'] = $firstSj->cabang_id;
            $data['from_model_type'] = SuratJalan::class;
            $data['from_model_id'] = $firstSj->id;
            $data['surat_jalan_ids'] = $selectedSjIds;

            // Kumpulkan seluruh DO yang tercakup dalam SJ-SJ tersebut
            $allDoIds = $sjs->flatMap(fn ($sj) => $sj->deliveryOrder->pluck('id'))->unique()->values()->all();
            $data['delivery_orders'] = $allDoIds;

            // Wariskan currency dan pajak dari SO pertama jika ada
            $allSos = $sjs->flatMap(fn ($sj) => $sj->deliveryOrder)->flatMap(fn ($do) => $do->salesOrders)->unique('id')->values();
            $firstSo = $allSos->first();
            if ($firstSo) {
                $data['currency_id'] = $firstSo->currency_id ?? null;
                $data['exchange_rate'] = (float) ($firstSo->exchange_rate ?? CurrencyConversionResolver::resolveRate(is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null));

                if (empty($data['tipe_pajak']) || $data['tipe_pajak'] === 'None') {
                    $taxData = app(SalesInvoiceTaxResolver::class)->resolveFromSaleOrder($firstSo);
                    $data['tax'] = $taxData['tax'];
                    $data['ppn_rate'] = $taxData['ppn_rate'];
                    $data['tipe_pajak'] = $taxData['tipe_pajak'];
                }
            }
        } else {
            // Jalur legacy: SO langsung tanpa Surat Jalan
            $saleOrderId = $data['selected_sale_order'] ?? $data['from_model_id'] ?? null;
            if (! empty($saleOrderId)) {
                $saleOrder = SaleOrder::find($saleOrderId);
                if ($saleOrder && ! empty($saleOrder->cabang_id)) {
                    $data['cabang_id'] = $saleOrder->cabang_id;
                }

                if ($saleOrder) {
                    $data['currency_id'] = $saleOrder->currency_id ?? null;
                    $data['exchange_rate'] = (float) ($saleOrder->exchange_rate ?? CurrencyConversionResolver::resolveRate(is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null));
                    $taxData = app(SalesInvoiceTaxResolver::class)->resolveFromSaleOrder($saleOrder);
                    $data['tax'] = $taxData['tax'];
                    $data['ppn_rate'] = $taxData['ppn_rate'];
                    $data['tipe_pajak'] = $taxData['tipe_pajak'];
                }
            }
        }

        // Validasi agar DO yang sudah ditagih tidak dapat ditagih kembali
        $selectedDos = $data['selected_delivery_orders'] ?? $data['delivery_orders'] ?? [];
        if (! empty($selectedDos) && empty($selectedSjIds)) {
            $alreadyInvoicedDos = Invoice::whereNotIn('status', ['canceled', 'cancelled'])
                ->where(function ($q) use ($selectedDos) {
                    foreach ((array) $selectedDos as $doId) {
                        $q->orWhereJsonContains('delivery_orders', (int) $doId);
                    }
                })
                ->get();

            if ($alreadyInvoicedDos->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'selected_delivery_orders' => 'Delivery Order yang dipilih sudah pernah ditagih pada invoice aktif: ' . $alreadyInvoicedDos->pluck('invoice_number')->implode(', '),
                ]);
            }
        }

        // Validasi agar invoice bernilai Rp 0 tidak dapat diterbitkan
        $totalAmount = (float) ($data['total'] ?? 0);
        $subtotalAmount = (float) ($data['subtotal'] ?? 0);
        if ($totalAmount <= 0 && $subtotalAmount <= 0) {
            throw ValidationException::withMessages([
                'data.selected_surat_jalans' => 'Invoice bernilai Rp 0 tidak dapat diterbitkan. Pastikan harga satuan dan kuantitas valid.',
                'selected_surat_jalans' => 'Invoice bernilai Rp 0 tidak dapat diterbitkan. Pastikan harga satuan dan kuantitas valid.',
            ]);
        }

        // Bersihkan field-field form temporer
        unset($data['selected_customer']);
        unset($data['selected_surat_jalans']);
        unset($data['selected_sale_order']);
        unset($data['selected_delivery_orders']);
        unset($data['delivery_order_items']);

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
        $data['exchange_rate'] = (float) ($data['exchange_rate'] ?? 1.0);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Simpan baris item invoice
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            $built = app(\App\Services\SalesInvoiceLineBuilder::class)->fromFormItems($this->record, $this->data['invoiceItem']);

            foreach ($built['items'] as $itemData) {
                $this->record->invoiceItem()->create($itemData);
            }

            $this->syncHeaderFromLines($built);
        }

        // Post journal entries untuk sales invoice jika bukan draft
        if (in_array($this->record->from_model_type, [SaleOrder::class, SuratJalan::class], true)
            && strtolower((string) $this->record->status) !== Invoice::STATUS_DRAFT) {
            $invoiceObserver = new \App\Observers\InvoiceObserver();
            $invoiceObserver->postSalesInvoice($this->record);
        }
    }

    /**
     * Header dihitung ulang dari baris bila SEMUA baris berpasangan dengan item SO, supaya Σ baris = header
     * (tidak ada selisih sen antara footer/PDF dan total invoice).
     *
     * @param  array{items: array<int, array<string, mixed>>, matched: bool}  $built
     */
    protected function syncHeaderFromLines(array $built): void
    {
        if (! $built['matched'] || $built['items'] === []) {
            return;
        }

        $items = collect($built['items']);
        $otherFees = app(\App\Services\SalesInvoiceLineBuilder::class)->otherFeeTotal($this->record);

        $this->record->update([
            'subtotal' => round((float) $items->sum('subtotal'), 2),
            'dpp' => round((float) $items->sum('subtotal'), 2),
            'total' => round((float) $items->sum('total') + $otherFees, 2),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}

