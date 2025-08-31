<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

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
                    $pdf = Pdf::loadView('pdf.sale-order-invoice', [
                        'invoice' => $record
                    ])->setPaper('A4', 'portrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Invoice_SO_' . $record->invoice_number . '.pdf');
                })
        ];
    }
}
