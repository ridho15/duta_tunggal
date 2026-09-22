<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditNoteResource\Pages;
use App\Filament\Support\CreditNoteActions;
use App\Models\CreditNote;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nota Kredit (T5.3, D10/D35–D40): daftar dan tampilan. Draf dibuat dari aksi "Buat Nota Kredit" pada Invoice/Retur (bukan halaman
 * Create); yang terbit final (tanpa Edit/Hapus). Seluruh menu tersembunyi bila flag SALES_CONTROLS_CREDIT_NOTES mati.
 */
class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationLabel = 'Nota Kredit';

    protected static ?string $modelLabel = 'Nota Kredit';

    protected static ?string $pluralModelLabel = 'Nota Kredit';

    protected static ?string $navigationGroup = 'Keuangan Penjualan';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;   // diakses lewat hub Keuangan Penjualan

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['invoice', 'customer']);
    }

    public static function statusColor(?string $state): string
    {
        return $state === CreditNote::STATUS_ISSUED ? 'success' : 'gray';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('credit_note_number')->label('No. Nota Kredit')->searchable()->sortable(),
                TextColumn::make('type')->label('Jenis')->badge()
                    ->formatStateUsing(fn (?string $state): string => CreditNote::TYPE_LABELS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === CreditNote::TYPE_CANCELLATION ? 'danger' : 'warning'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (?string $state): string => CreditNote::STATUS_LABELS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => static::statusColor($state)),
                TextColumn::make('invoice.invoice_number')->label('Invoice')->searchable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->limit(30),
                TextColumn::make('credit_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('total')->label('Total')->rupiah()->sortable(),
                TextColumn::make('tax_document_number')->label('No. Nota Retur Pajak')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(CreditNote::STATUS_LABELS),
                Tables\Filters\SelectFilter::make('type')->label('Jenis')->options(CreditNote::TYPE_LABELS),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print_credit_note')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->url(fn (CreditNote $record): string => route('pdf-stream', ['type' => 'credit-note', 'id' => $record->id]))
                    ->openUrlInNewTab(),
                CreditNoteActions::issue(Tables\Actions\Action::make('issue')),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Nota Kredit')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('credit_note_number')->label('No. Nota Kredit'),
                    TextEntry::make('type')->label('Jenis')->badge()->formatStateUsing(fn (?string $state): string => CreditNote::TYPE_LABELS[$state] ?? (string) $state),
                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn (?string $state): string => CreditNote::STATUS_LABELS[$state] ?? (string) $state)
                        ->color(fn (?string $state): string => static::statusColor($state)),
                    TextEntry::make('invoice.invoice_number')->label('Invoice Asal'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('credit_date')->label('Tanggal')->date('d M Y'),
                    TextEntry::make('customerReturn.return_number')->label('Retur Asal')->placeholder('-'),
                    TextEntry::make('tax_document_number')->label('No. Nota Retur Pajak')->placeholder('Belum diisi'),
                    TextEntry::make('issued_at')->label('Diterbitkan')->dateTime('d M Y H:i')->placeholder('Belum terbit'),
                ]),
                TextEntry::make('reason')->label('Alasan')->columnSpanFull(),
            ]),
            Section::make('Nilai')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('subtotal')->label('DPP')->rupiah(),
                    TextEntry::make('tax_amount')->label('PPN')->rupiah(),
                    TextEntry::make('other_fee_amount')->label('Biaya Pengiriman')->rupiah(),
                    TextEntry::make('total')->label('Total Nota Kredit')->rupiah()->weight('bold'),
                    TextEntry::make('applied_to_ar')->label('Mengurangi Piutang')->rupiah()->placeholder('-'),
                    TextEntry::make('applied_to_deposit')->label('Menjadi Deposit Customer')->rupiah()->placeholder('-'),
                ]),
            ]),
            Section::make('Rincian')->schema([
                RepeatableEntry::make('items')->label('')->schema([
                    TextEntry::make('description')->label('Uraian')
                        ->state(fn ($record): string => (string) ($record->description ?: ($record->product?->name ?: $record->invoiceItem?->product?->name ?: '-'))),
                    TextEntry::make('quantity')->label('Qty'),
                    TextEntry::make('unit_price')->label('Harga')->rupiah(),
                    TextEntry::make('subtotal')->label('DPP')->rupiah(),
                    TextEntry::make('tax_amount')->label('PPN')->rupiah(),
                    TextEntry::make('total')->label('Total')->rupiah(),
                ])->columns(6),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditNotes::route('/'),
            'view' => Pages\ViewCreditNote::route('/{record}'),
        ];
    }
}
