<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankReconciliationResource\Pages;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters;

class BankReconciliationResource extends Resource
{
    protected static ?string $model = BankReconciliation::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Finance - Akuntansi';
    protected static ?string $modelLabel = 'Rekonsiliasi Bank';
    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Fieldset::make('Form')
                ->schema([
                    Forms\Components\Grid::make(12)->schema([
                        Forms\Components\Select::make('coa_id')->label('Akun Bank')
                            ->helperText('Pilih akun kas/bank. Pastikan kode COA benar untuk Kas/Bank.')
                            ->options(function () {
                                // Termasuk beberapa prefix umum untuk akun kas/bank
                                return ChartOfAccount::query()
                                    ->where(function ($q) {
                                        $q->where('code', 'like', '111%') // Kas & Bank umumnya 111xxx
                                            ->orWhere('code', 'like', '112%') // Giro/Tabungan di sebagian template
                                            ->orWhere('name', 'like', '%Bank%')
                                            ->orWhere('name', 'like', '%Kas%');
                                    })
                                    ->where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]);
                            })
                            ->searchable()->required()->columnSpan(6),
                        Forms\Components\DatePicker::make('period_start')->label('Periode Dari')->required()->columnSpan(3),
                        Forms\Components\DatePicker::make('period_end')->label('Sampai')->required()->columnSpan(3),
                        Forms\Components\TextInput::make('statement_ending_balance')->numeric()->indonesianMoney()->label('Saldo Akhir Rekening Koran')->required()->columnSpan(4),
                        Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpan(8),
                    ]),
                    Forms\Components\Section::make('Transaksi yang Belum Direkonsiliasi')
                        ->description('Pilih transaksi yang muncul di rekening koran untuk menandainya sebagai sudah direkonsiliasi')
                        ->visible(fn($record) => filled($record))
                        ->schema([
                            Forms\Components\Select::make('selected_entry_ids')
                                ->label('Pilih Transaksi untuk Direkonsiliasi')
                                ->multiple()
                                ->options(function ($record) {
                                    if (!$record) return [];

                                    return \App\Models\JournalEntry::where('coa_id', $record->coa_id)
                                        ->whereBetween('date', [$record->period_start, $record->period_end])
                                        ->whereNull('bank_recon_id')
                                        ->where(function ($query) {
                                            $query->where('debit', '>', 0)->orWhere('credit', '>', 0);
                                        })
                                        ->get()
                                        ->mapWithKeys(function ($entry) {
                                            $label = sprintf(
                                                '%s - %s (%s)',
                                                $entry->reference ?? 'No Ref',
                                                $entry->description ?? 'No Description',
                                                $entry->debit > 0 ? 'Dr ' . number_format($entry->debit) : 'Cr ' . number_format($entry->credit)
                                            );
                                            return [$entry->id => $label];
                                        });
                                })
                        ])
                ])
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Rekonsiliasi')
                    ->schema([
                        Infolists\Components\TextEntry::make('coa.code')->label('Akun Bank')->formatStateUsing(fn ($state, $record) => $record->coa->code . ' - ' . $record->coa->name),
                        Infolists\Components\TextEntry::make('period_start')->date('d/m/Y')->label('Periode Dari'),
                        Infolists\Components\TextEntry::make('period_end')->date('d/m/Y')->label('Sampai'),
                        Infolists\Components\TextEntry::make('statement_ending_balance')->money('IDR')->label('Saldo Akhir Rekening Koran'),
                        Infolists\Components\TextEntry::make('book_balance')->money('IDR')->label('Saldo Buku'),
                        Infolists\Components\TextEntry::make('difference')->money('IDR')->label('Selisih'),
                        Infolists\Components\TextEntry::make('status')->badge()->color(fn ($state) => $state === 'closed' ? 'success' : 'secondary'),
                        Infolists\Components\TextEntry::make('notes')->label('Catatan'),
                    ])->columns(2),
                Infolists\Components\Section::make('Transaksi yang Direkonsiliasi')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('journalEntries')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('date')->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('reference'),
                                Infolists\Components\TextEntry::make('description'),
                                Infolists\Components\TextEntry::make('debit')->money('IDR'),
                                Infolists\Components\TextEntry::make('credit')->money('IDR'),
                            ])
                            ->columns(5)
                    ])
            ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('coa.code')->label('Akun Bank')->formatStateUsing(fn ($state, $record) => $record->coa->code . ' - ' . $record->coa->name),
                TextColumn::make('period_start')->date('d/m/Y')->label('Mulai'),
                TextColumn::make('period_end')->date('d/m/Y')->label('Selesai'),
                TextColumn::make('statement_ending_balance')->money('IDR')->label('Saldo Rek Koran'),
                TextColumn::make('book_balance')->money('IDR')->label('Saldo Buku'),
                TextColumn::make('difference')->money('IDR')->label('Selisih')->color(fn ($state) => $state != 0 ? 'danger' : 'success'),
                TextColumn::make('status')->badge()->colors(['secondary' => 'open', 'success' => 'closed']),
            ])
            ->filters([
                Filters\SelectFilter::make('coa_id')->label('Akun Bank')
                    ->options(ChartOfAccount::where('is_active', true)->orderBy('code')->pluck('name', 'id'))
            ])
            ->defaultSort('period_end', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankReconciliations::route('/'),
            'create' => Pages\CreateBankReconciliation::route('/create'),
            'view' => Pages\ViewBankReconciliation::route('/{record}'),
            'edit' => Pages\EditBankReconciliation::route('/{record}/edit'),
        ];
    }
}
