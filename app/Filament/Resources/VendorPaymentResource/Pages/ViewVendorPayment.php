<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\JournalEntry;
use Filament\Infolists\Components\RepeatableEntry;

class ViewVendorPayment extends ViewRecord
{
    protected static string $resource = VendorPaymentResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Load journal entries with COA relationship
        $this->record->load(['journalEntries.coa']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->color('warning'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.journal-entries.index', [
                    'tableFilters[source_type][value]' => 'App\Models\VendorPayment',
                    'tableFilters[source_id][value]' => $this->record->id
                ]))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Vendor Payment')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('supplier.name')
                                    ->label('Supplier'),
                                Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Tanggal Pembayaran')
                                    ->date(),
                                Infolists\Components\TextEntry::make('ntpn')
                                    ->label('NTPN'),
                                Infolists\Components\TextEntry::make('total_payment')
                                    ->label('Total Pembayaran')
                                    ->money('IDR'),
                                Infolists\Components\TextEntry::make('coa.name')
                                    ->label('Akun Pembayaran'),
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Metode Pembayaran'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status'),
                            ]),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Journal Entries')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                            ->label('View All Journal Entries')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(function () {
                                // Redirect to JournalEntryResource with filter for this vendor payment
                                $sourceType = urlencode(\App\Models\VendorPayment::class);
                                $sourceId = $this->record->id;

                                return "/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}";
                            })
                            ->openUrlInNewTab()
                            ->visible(function () {
                                return $this->record->journalEntries()->exists();
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('journalEntries')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('date')->date()->label('Date'),
                                Infolists\Components\TextEntry::make('coa.code')->label('COA'),
                                Infolists\Components\TextEntry::make('coa.name')->label('Account Name'),
                                Infolists\Components\TextEntry::make('debit')->money('IDR')->label('Debit')->color('success'),
                                Infolists\Components\TextEntry::make('credit')->money('IDR')->label('Credit')->color('danger'),
                                Infolists\Components\TextEntry::make('description')->label('Description'),
                                Infolists\Components\TextEntry::make('journal_type')->badge()->label('Type'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function () {
                        return $this->record->journalEntries()->exists();
                    })
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}