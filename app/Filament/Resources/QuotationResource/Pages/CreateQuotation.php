<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\QuotationService;
use App\Models\PurchaseOrder;
use App\Models\OrderRequest;
use App\Support\CurrencyConversionResolver;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected $quotationService;
    public function boot()
    {
        $this->quotationService = app(QuotationService::class);
    }
    protected function afterCreate()
    {
        $this->quotationService->updateTotalAmount($this->getRecord());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR')
            ?? \App\Models\Currency::query()->orderBy('id')->value('id');

        // If quotation is created in the context of a Purchase Order or Order Request,
        // inherit currency from those records so Quotation follows OR/PO currency.
        $poId = $data['purchase_order_id'] ?? request()->query('purchase_order_id');
        $orId = $data['order_request_id'] ?? request()->query('order_request_id');

        if (!empty($poId)) {
            $po = PurchaseOrder::find($poId);
            if ($po) {
                $data['currency_id'] = $po->currency_id ?? $data['currency_id'] ?? null;
                $data['exchange_rate'] = CurrencyConversionResolver::resolveRate($data['currency_id'] ?? null);
            }
        } elseif (!empty($orId)) {
            $or = OrderRequest::find($orId);
            if ($or) {
                $data['currency_id'] = $or->currency_id ?? $data['currency_id'] ?? null;
                $data['exchange_rate'] = CurrencyConversionResolver::resolveRate($data['currency_id'] ?? null);
            }
        }

        $data['currency_id'] = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : $defaultCurrencyId;
        $data['exchange_rate'] = CurrencyConversionResolver::resolveRate($data['currency_id'] ?? null);

        // Normalisasi harga & kalkulasi total_price jika perlu
        $items = $data['quotationItem'] ?? [];
        $grand = 0;
        foreach ($items as $uuid => $item) {
            if (!is_array($item)) {
                continue;
            }
            $rawUnit = $item['unit_price'] ?? 0;
            // Parse formatted Indonesian number to numeric
            $numericUnit = QuotationResource::parseCurrencyState($rawUnit);
            // Use quotation header currency for anchor (header-only policy)
            $headerCurrencyId = $data['currency_id'] ?? null;
            $item['unit_price_idr'] = CurrencyConversionResolver::convertToIdrHighPrecision(
                (string) $numericUnit,
                is_numeric($headerCurrencyId) ? (int) $headerCurrencyId : null
            );
            $qty = (int)($item['quantity'] ?? 0);
            $disc = (int)($item['discount'] ?? 0);
            $tipe = $item['tipe_pajak'] ?? $item['tax_type'] ?? null;
            $normalizedTipe = \App\Support\TaxTypeHelper::normalize($tipe, \App\Support\TaxTypeHelper::NONE);
            $tax = $normalizedTipe === \App\Support\TaxTypeHelper::NONE ? 0 : (int)($item['tax'] ?? 0);
            $item['tax'] = $tax;
            $item['tax_type'] = $normalizedTipe;
            $total = \App\Http\Controllers\HelperController::hitungSubtotal($qty, $numericUnit, $disc, $tax, $normalizedTipe);
            $grand += $total;
            // Replace with normalized numeric values in quotation header currency.
            $item['unit_price'] = $numericUnit;
            $item['total_price'] = $total;
            $items[$uuid] = $item;
        }
        $data['quotationItem'] = $items;
        $data['total_amount'] = $grand;
        \Illuminate\Support\Facades\Log::info('Quotation normalized before create', $data);

        return $data;
    }
}
