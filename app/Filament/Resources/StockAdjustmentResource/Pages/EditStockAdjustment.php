<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => in_array($this->record->status, ['draft', 'rejected'], true)),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->status === 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Stock adjustment yang sudah disetujui tidak dapat diubah.',
            ]);
        }
    }
}
