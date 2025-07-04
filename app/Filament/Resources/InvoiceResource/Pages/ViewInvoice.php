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
                ->label('Cetak Invoice')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->action(function ($record) {
                    if ($record->from_model_type == 'App\Models\PurchaseOrder') {
                        $pdf = Pdf::loadView('pdf.purchase-order-invoice-2', [
                            'invoice' => $record
                        ])->setPaper('A4', 'potrait');

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, 'Invoice_PO_' . $record->invoice_number . '.pdf');
                    }
                })
        ];
    }
}
