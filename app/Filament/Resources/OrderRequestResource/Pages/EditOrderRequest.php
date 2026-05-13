<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

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
                
                // Step 1: Convert prices from DB storage (IDR) to item currency
                $itemCurrencyId = $item['currency_id'] ?? $data['currency_id'] ?? null;
                if ($itemCurrencyId) {
                    // Convert from IDR to item currency using item currency rate
                    $item['original_price'] = OrderRequestResource::convertIdrToCurrency(
                        (float) ($item['original_price'] ?? 0),
                        (int) $itemCurrencyId
                    );
                    $item['unit_price'] = OrderRequestResource::convertIdrToCurrency(
                        (float) ($item['unit_price'] ?? 0),
                        (int) $itemCurrencyId
                    );
                    $item['total_cost'] = OrderRequestResource::convertIdrToCurrency(
                        (float) ($item['total_cost'] ?? 0),
                        (int) $itemCurrencyId
                    );
                    $item['tax_nominal'] = OrderRequestResource::convertIdrToCurrency(
                        (float) ($item['tax_nominal'] ?? 0),
                        (int) $itemCurrencyId
                    );
                    $item['subtotal'] = OrderRequestResource::convertIdrToCurrency(
                        (float) ($item['subtotal'] ?? 0),
                        (int) $itemCurrencyId
                    );
                }
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

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
