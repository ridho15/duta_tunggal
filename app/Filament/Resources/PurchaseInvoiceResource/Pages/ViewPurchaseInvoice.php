<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('print_invoice')
                ->label('Cetak Invoice')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->action(function ($record) {
                    $pdf = Pdf::loadView('pdf.purchase-order-invoice-2', [
                        'invoice' => $record
                    ])->setPaper('A4', 'portrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Invoice_PO_' . $record->invoice_number . '.pdf');
                })
        ];
    }
}
