<?php

namespace App\Filament\Resources\BankReconciliationResource\Pages;

use App\Filament\Resources\BankReconciliationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankReconciliations extends ListRecords
{
    protected static string $resource = BankReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Rekonsiliasi'),
        ];
    }

    protected function getEmptyStateHeading(): ?string
    {
        return 'Belum ada Rekonsiliasi Bank';
    }

    protected function getEmptyStateDescription(): ?string
    {
        return 'Klik "Buat Rekonsiliasi" untuk membuat periode rekonsiliasi dan memilih transaksi bank yang akan dicocokkan.';
    }

    protected function getEmptyStateActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Rekonsiliasi'),
        ];
    }
}
