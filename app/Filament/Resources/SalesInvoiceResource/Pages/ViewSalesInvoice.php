<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ViewEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Invoice Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label('Invoice Number'),
                                TextEntry::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->date(),
                                TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date(),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'unpaid' => 'gray',
                                        'sent' => 'warning',
                                        'paid' => 'success',
                                        'partially_paid' => 'primary',
                                        'overdue' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Section::make('Customer Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('customer_name_display')
                                    ->label('Customer Name'),
                                TextEntry::make('customer_phone_display')
                                    ->label('Customer Phone'),
                            ]),
                    ]),

                Section::make('Financial Information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('dpp')
                                    ->label('DPP')
                                    ->money('IDR'),
                                TextEntry::make('other_fee_total')
                                    ->label('Other Fee')
                                    ->money('IDR'),
                                TextEntry::make('tax')
                                    ->label('PPN Amount')
                                    ->money('IDR'),
                                TextEntry::make('ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->suffix('%'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('IDR'),
                                TextEntry::make('total')
                                    ->label('Grand Total')
                                    ->money('IDR')
                                    ->weight('bold')
                                    ->size('lg'),
                            ]),
                    ]),

                Section::make('Source Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('from_model_type')
                                    ->label('Source Type')
                                    ->formatStateUsing(fn (string $state): string => 
                                        str_replace('App\\Models\\', '', $state)),
                                TextEntry::make('fromModel.so_number')
                                    ->label('SO Number')
                                    ->visible(fn ($record) => $record->from_model_type === 'App\Models\SaleOrder'),
                            ]),
                        TextEntry::make('delivery_orders_display')
                            ->label('Delivery Orders'),
                    ]),

                Section::make('Invoice Items')
                    ->schema([
                        RepeatableEntry::make('invoiceItem')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('product')
                                            ->label('Product')
                                            ->formatStateUsing(function($state){
                                                return "{$state['sku']} - {$state['name']}";
                                            }),
                                        TextEntry::make('quantity')
                                            ->label('Quantity'),
                                        TextEntry::make('price')
                                            ->label('Price')
                                            ->money('IDR'),
                                        TextEntry::make('total')
                                            ->label('Total')
                                            ->money('IDR'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Journal Entries')
                    ->schema([
                        ViewEntry::make('journal_entries_table')
                            ->label('')
                            ->view('filament.infolists.journal-entries-table')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Actions\Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-book-open')
                ->color('success')
                ->action(function ($record) {
                    $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
                        ->where('source_id', $record->id)
                        ->get();

                    if ($journalEntries->count() === 1) {
                        // Jika hanya 1 journal entry, langsung ke halaman detail
                        $entry = $journalEntries->first();
                        return redirect()->to("/admin/journal-entries/{$entry->id}");
                    } else {
                        // Jika multiple entries, gunakan filter
                        $sourceType = urlencode(\App\Models\Invoice::class);
                        $sourceId = $record->id;
                        return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}");
                    }
                }),
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
