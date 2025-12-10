<?php

namespace App\Filament\Resources\OtherSaleResource\Pages;

use App\Filament\Resources\OtherSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use App\Models\OtherSale;
use App\Models\JournalEntry;

class ViewOtherSale extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = OtherSaleResource::class;

    protected static string $view = 'filament.resources.other-sale-resource.view-other-sale';

    public OtherSale $record;

    public function mount(OtherSale $record): void
    {
        $this->record = $record->load(['coa', 'cashBankAccount', 'customer', 'cabang', 'creator', 'journalEntries.coa']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => OtherSaleResource::getUrl('index')),

            Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->url(fn (): string => OtherSaleResource::getUrl('edit', ['record' => $this->record])),

            Actions\Action::make('delete')
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->delete();
                    return redirect(OtherSaleResource::getUrl('index'));
                }),

            Actions\Action::make('post_journal')
                ->label('Post Journal')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn(): bool => $this->record->status === 'draft')
                ->action(function () {
                    $service = new \App\Services\OtherSaleService();
                    $service->postJournalEntries($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Journal entries posted successfully')
                        ->success()
                        ->send();

                    return redirect(request()->header('Referer'));
                }),

            Actions\Action::make('reverse_journal')
                ->label('Reverse Journal')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn(): bool => $this->record->status === 'posted')
                ->action(function () {
                    $service = new \App\Services\OtherSaleService();
                    $service->reverseJournalEntries($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Journal entries reversed successfully')
                        ->warning()
                        ->send();

                    return redirect(request()->header('Referer'));
                }),
        ];
    }

    public function infolist(): Infolist
    {
        return Infolist::make($this)
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make('Informasi Transaksi')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('reference_number')
                                    ->label('Nomor Referensi')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('transaction_date')
                                    ->label('Tanggal Transaksi')
                                    ->date('d/m/Y'),

                                Infolists\Components\TextEntry::make('type')
                                    ->label('Jenis')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'posted' => 'success',
                                        default => 'gray',
                                    }),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('amount')
                            ->label('Jumlah')
                            ->money('IDR')
                            ->weight('bold')
                            ->size('lg'),
                    ]),

                Infolists\Components\Section::make('Informasi Akun')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('coa_name')
                                    ->label('Akun Pendapatan')
                                    ->state(fn () => $this->record->coa ? $this->record->coa->name . ' (' . $this->record->coa->code . ')' : '-'),

                                Infolists\Components\TextEntry::make('cash_bank_name')
                                    ->label('Akun Kas/Bank')
                                    ->state(fn () => $this->record->cash_bank_account_id ?
                                        ($this->record->cashBankAccount ? $this->record->cashBankAccount->name . ' (' . $this->record->cashBankAccount->account_number . ')' : '-') :
                                        'Accounts Receivable'),

                                Infolists\Components\TextEntry::make('customer_name')
                                    ->label('Customer')
                                    ->state(fn () => $this->record->customer ? $this->record->customer->name . ' (' . $this->record->customer->code . ')' : '-'),

                                Infolists\Components\TextEntry::make('cabang_name')
                                    ->label('Cabang')
                                    ->state(fn () => $this->record->cabang?->nama),
                            ]),
                    ]),

                Infolists\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan'),

                        Infolists\Components\TextEntry::make('creator_name')
                            ->label('Dibuat Oleh')
                            ->state(fn () => $this->record->creator?->name),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diubah Pada')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(JournalEntry::query()->where('source_type', OtherSale::class)->where('source_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referensi')
                    ->searchable(),

                Tables\Columns\TextColumn::make('coa.name')
                    ->label('Akun')
                    ->getStateUsing(fn ($record) => $record->coa ? $record->coa->name . ' (' . $record->coa->code . ')' : '-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50),

                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->money('IDR')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->money('IDR')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('journal_type')
                    ->label('Tipe Journal')
                    ->badge(),
            ])
            ->defaultSort('date', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([
                Tables\Actions\Action::make('view_journal_detail')
                    ->label('Lihat Detail Journal')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn (): string => route('filament.admin.resources.journal-entries.index', [
                        'tableFilters[reference][value]' => $this->record->reference_number,
                        'tableFilters[journal_type][value]' => 'other_sales'
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Belum ada journal entries')
            ->emptyStateDescription('Journal entries akan muncul setelah transaksi ini diposting.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}