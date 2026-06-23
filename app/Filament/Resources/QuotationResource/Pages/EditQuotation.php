<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Http\Controllers\HelperController;
use App\Services\QuotationService;
use App\Support\CurrencyConversionResolver;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditQuotation extends EditRecord
{
    protected static string $resource = QuotationResource::class;
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }
    
            public function getTitle(): string
            {
                return 'Edit Quotation - ' . QuotationResource::quotationStatusLabel($this->record?->status);
            }


    protected function afterSave()
    {
        $quotationService = app(QuotationService::class);
        $quotationService->updateTotalAmount($this->getRecord());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Calculate total_amount from quotation items for display
        $items = $data['quotationItem'] ?? [];
        $grand = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $grand += (float) 
                
                \App\Http\Controllers\HelperController::hitungSubtotal(
                    (float) ($item['quantity'] ?? 0),
                    (float) \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                    (float) ($item['discount'] ?? 0),
                    (float) ($item['tax'] ?? 0),
                    $item['tax_type'] ?? 'None'
                );
        }
        $data['total_amount'] = QuotationResource::formatCurrencyPreviewState($grand, is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null);
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR')
            ?? \App\Models\Currency::query()->orderBy('id')->value('id');
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
        
        // Log data quotation yang dikirim ke backend saat update
        \Illuminate\Support\Facades\Log::info('Quotation Data Before Update:', $data);
        
        // Log khusus untuk quotation items
        if (isset($data['quotationItem'])) {
            \Illuminate\Support\Facades\Log::info('Quotation Items Data Before Update:', $data['quotationItem']);
        }
        
        return $data;
    }
}
