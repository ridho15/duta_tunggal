<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Filament\Resources\StockOpnameResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => $this->record->status !== 'approved'),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->status === 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Stock opname yang sudah disetujui tidak dapat diubah.',
            ]);
        }
    }
}
