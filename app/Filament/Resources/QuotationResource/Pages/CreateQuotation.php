<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\QuotationService;
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
        // Normalisasi harga & kalkulasi total_price jika perlu
        $items = $data['quotationItem'] ?? [];
        $grand = 0;
        foreach ($items as $uuid => $item) {
            if (!is_array($item)) {
                continue;
            }
            $rawUnit = $item['unit_price'] ?? 0;
            // Parse formatted Indonesian number to numeric
            $numericUnit = \App\Http\Controllers\HelperController::parseIndonesianMoney($rawUnit);
            $qty = (int)($item['quantity'] ?? 0);
            $disc = (int)($item['discount'] ?? 0);
            $tax = (int)($item['tax'] ?? 0);
            $tipe = $item['tipe_pajak'] ?? null;
            $total = \App\Http\Controllers\HelperController::hitungSubtotal($qty, $numericUnit, $disc, $tax, $tipe);
            $grand += $total;
            // Replace with normalized numeric values (stored as integer Rupiah)
            $item['unit_price'] = (int)$numericUnit;
            $item['total_price'] = (int)$total;
            $items[$uuid] = $item;
        }
        $data['quotationItem'] = $items;
        $data['total_amount'] = (int)$grand;
        \Illuminate\Support\Facades\Log::info('Quotation normalized before create', $data);
        return $data;
    }
}
