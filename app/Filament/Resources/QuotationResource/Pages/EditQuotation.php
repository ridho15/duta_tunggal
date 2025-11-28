<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\QuotationService;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuotation extends EditRecord
{
    protected static string $resource = QuotationResource::class;
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
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
            $grand += $item['total_price'] ?? 0;
        }
        $data['total_amount'] = $grand;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
        
        // Log data quotation yang dikirim ke backend saat update
        \Illuminate\Support\Facades\Log::info('Quotation Data Before Update:', $data);
        
        // Log khusus untuk quotation items
        if (isset($data['quotationItem'])) {
            \Illuminate\Support\Facades\Log::info('Quotation Items Data Before Update:', $data['quotationItem']);
        }
        
        return $data;
    }
}
