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
use App\Support\CurrencyConversionResolver;
use App\Support\LineAmounts;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function mount($record): void
    {
        parent::mount($record);
        // debug log invoice whenever the page is instantiated
        \Illuminate\Support\Facades\Log::debug('Viewing invoice for debug', [
            'id' => $this->record->id,
            'tax_rate' => $this->record->tax,
            'ppn_amount' => $this->record->ppn_amount,
            'account_payable' => optional($this->record->accountPayable)->toArray(),
        ]);
    }

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
                                TextEntry::make('tax_invoice_number')
                                    ->label('No. Faktur Pajak')
                                    ->badge()
                                    ->placeholder(fn ($record) => \App\Services\SalesInvoiceTaxNumber::isMissing($record) ? 'Belum diisi' : '–')
                                    ->color(fn ($state, $record) => filled($state) ? 'success' : (\App\Services\SalesInvoiceTaxNumber::isMissing($record) ? 'danger' : 'gray')),
                                TextEntry::make('currency_display')
                                    ->label('Mata Uang')
                                    ->state(fn ($record) => $record->displayCurrency?->code ? ($record->displayCurrency?->symbol . ' ' . $record->displayCurrency?->code) : '-'),
                                TextEntry::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->date(),
                                TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date(),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(\App\Support\StatusLabels::formatter('invoice'))
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'unpaid' => 'gray',
                                        'sent' => 'warning',
                                        'paid' => 'success',
                                        'partially_paid' => 'primary',
                                        'overdue' => 'danger',
                                        'cancelled' => 'gray',
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
                                    ->formatStateUsing(fn ($state, $record) => LineAmounts::money((float) $state, $record->display_currency_id)),
                                TextEntry::make('other_fee_total')
                                    ->label('Other Fee')
                                    ->formatStateUsing(fn ($state, $record) => LineAmounts::money((float) $state, $record->display_currency_id)),
                                TextEntry::make('tax_type_display')
                                    ->label('Tipe Pajak')
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'Non Pajak' => 'gray',
                                        'Inklusif' => 'info',
                                        'Eksklusif' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('effective_ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->suffix('%')
                                    ->visible(fn ($record) => (float) ($record->effective_ppn_rate ?? 0) > 0),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('ppn_amount')
                                    ->label('Nominal PPN (Rp)')
                                    ->formatStateUsing(fn ($state, $record) => LineAmounts::money((float) $state, $record->display_currency_id))
                                    ->visible(fn ($record) => (float) ($record->ppn_amount ?? 0) > 0),
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->formatStateUsing(fn ($state, $record) => LineAmounts::money((float) $state, $record->display_currency_id)),
                                TextEntry::make('total')
                                    ->label('Grand Total')
                                    ->formatStateUsing(fn ($state, $record) => LineAmounts::money((float) $state, $record->display_currency_id))
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
                    ->description('Harga Satuan × Qty = Jumlah · Diskon · DPP · PPN · Total baris. Angka identik dengan PDF invoice.')
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
                                            })
                                            ->columnSpan(2),
                                        TextEntry::make('quantity_display')
                                            ->label('Qty')
                                            ->getStateUsing(fn ($record) => rtrim(rtrim(number_format($record->breakdown()['quantity'], 2, ',', '.'), '0'), ',')),
                                        TextEntry::make('unit_price_display')
                                            ->label('Harga Satuan')
                                            ->getStateUsing(fn ($record) => LineAmounts::money($record->breakdown()['unit_price'])),
                                        TextEntry::make('gross_display')
                                            ->label('Jumlah (Harga × Qty)')
                                            ->getStateUsing(fn ($record) => LineAmounts::money($record->breakdown()['gross'])),
                                        TextEntry::make('discount_display')
                                            ->label('Diskon')
                                            ->getStateUsing(function ($record) {
                                                $b = $record->breakdown();

                                                return number_format($b['discount_pct'], 2, ',', '.') . '% = ' . LineAmounts::money($b['discount_amount']);
                                            }),
                                        TextEntry::make('dpp_display')
                                            ->label('DPP')
                                            ->getStateUsing(fn ($record) => LineAmounts::money($record->breakdown()['dpp'])),
                                        TextEntry::make('ppn_display')
                                            ->label('PPN')
                                            ->getStateUsing(function ($record) {
                                                $b = $record->breakdown();

                                                return number_format($b['tax_rate'], 2, ',', '.') . '% = ' . LineAmounts::money($b['ppn']);
                                            }),
                                        TextEntry::make('total_display')
                                            ->label('Total Baris')
                                            ->getStateUsing(fn ($record) => LineAmounts::money($record->breakdown()['total']))
                                            ->weight('bold'),
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
        return \App\Filament\Support\DocumentActions::layout($this->headerActionList(), ['edit', 'delete', 'print_invoice', 'view_journal_entries', 'cancel_invoice']);
    }

    /** Daftar lengkap aksi header; pengelompokan utama/"Lainnya" oleh DocumentActions (D13). */
    private function headerActionList(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
            SalesInvoiceResource::taxNumberPageAction(),
            \App\Filament\Support\CreditNoteActions::create(Actions\Action::make('create_credit_note')),
            \App\Filament\Support\CreditNoteActions::cancelInvoice(Actions\Action::make('cancel_invoice')),
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
                        return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][source_id]={$sourceId}");
                    }
                }),
            Actions\Action::make('print_invoice')
                ->label('Preview Invoice')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->url(fn($record) => route('pdf-stream', ['type' => 'sales-invoice', 'id' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }
}
