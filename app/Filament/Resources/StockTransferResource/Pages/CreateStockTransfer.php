<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected static bool $canUseDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['stockTransferItem']) || ! is_array($data['stockTransferItem']) || count($data['stockTransferItem']) === 0) {
            throw ValidationException::withMessages([
                'stockTransferItem' => 'Minimal harus menambahkan 1 item untuk transfer stok.',
            ]);
        }

        foreach ($data['stockTransferItem'] as $idx => &$item) {
            if (empty($item['from_rak_id'])) {
                $item['from_rak_id'] = null;
            }
            if (empty($item['to_rak_id'])) {
                $item['to_rak_id'] = null;
            }
        }
        unset($item);

        return $data;
    }
}
