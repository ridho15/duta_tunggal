<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('cetak_invoice')
                ->label('Preview Invoice')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->url(fn($record) => route('pdf-stream', ['type' => 'purchase-invoice', 'id' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }
}
