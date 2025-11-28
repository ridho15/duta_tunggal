<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashBankTransferResource\Pages;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Services\CashBankService;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashBankTransferResource extends Resource
{
    protected static ?string $model = CashBankTransfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Finance - Pembayaran';
    protected static ?string $modelLabel = 'Transfer Kas & Bank';
    protected static ?int $navigationSort = 7;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Fieldset::make('Form')
                ->schema([
                    Grid::make(12)->schema([
                        TextInput::make('number')->label('Nomor')
                            ->helperText('Auto-generate bila dikosongkan')
                            ->dehydrated()
                            ->columnSpan(3)
                            ->default(fn() => app(CashBankService::class)->generateTransferNumber())
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('generateNumber')
                                    ->label('Generate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Set $set) {
                                        $set('number', app(CashBankService::class)->generateTransferNumber());
                                    })
                            ),
                        DatePicker::make('date')->label('Tanggal')->required()->columnSpan(3),
                        TextInput::make('amount')->numeric()->minValue(0.01)->required()->indonesianMoney()->columnSpan(3),
                        TextInput::make('other_costs')
                            ->label('Biaya Lainnya')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->indonesianMoney()
                            ->columnSpan(3)
                            ->live()
                            ->helperText('Biaya admin bank atau biaya transfer lainnya'),
                        TextInput::make('reference')->label('Referensi')->columnSpan(4),
                        Select::make('from_coa_id')->label('Dari Kas/Bank (COA)')
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->searchable()->required()->columnSpan(4),
                        Select::make('to_coa_id')->label('Ke Kas/Bank (COA)')
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->searchable()->required()->columnSpan(4)
                            ->rule('different:from_coa_id'),
                        Select::make('other_costs_coa_id')
                            ->label('COA Biaya Lainnya')
                            ->options(fn() => ChartOfAccount::whereNotNull('parent_id')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->searchable()
                            ->columnSpan(3)
                            ->visible(fn (callable $get) => $get('other_costs') > 0)
                            ->required(fn (callable $get) => $get('other_costs') > 0)
                            ->helperText('Pilih COA untuk biaya lainnya'),
                        Textarea::make('description')->label('Keterangan')->columnSpan(6),
                        FileUpload::make('attachment_path')->label('Lampiran')->directory('cashbank')->columnSpan(6),
                    ])
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->searchable()->sortable(),
                TextColumn::make('date')->date('d/m/Y')->label('Tanggal')->sortable(),
                TextColumn::make('fromCoa.code')
                    ->label('Dari')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->fromCoa ? $record->fromCoa->code . ' - ' . $record->fromCoa->name : '-'
                    )
                    ->searchable(),
                TextColumn::make('toCoa.code')
                    ->label('Ke')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->toCoa ? $record->toCoa->code . ' - ' . $record->toCoa->name : '-'
                    )
                    ->searchable(),
                TextColumn::make('amount')->money('IDR')->label('Jumlah')->sortable(),
                TextColumn::make('other_costs')
                    ->money('IDR')
                    ->label('Biaya Lainnya')
                    ->sortable()
                    ->toggleable(),
                BadgeColumn::make('status')->label('Status')
                    ->colors(['secondary' => 'draft', 'success' => 'posted', 'info' => 'reconciled']),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('switch_to_transaction')
                    ->label('Transaksi Kas & Bank')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->url(fn() => \App\Filament\Resources\CashBankTransactionResource::getUrl('index'))
                    ->tooltip('Beralih ke menu Transaksi Kas & Bank'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('view_reconciliation')->label('Lihat Rekonsiliasi Bank')->icon('heroicon-o-eye')
                    ->url(fn($record) => '/bank-reconciliations?tableFilters[coa_id][value]=' . ($record->fromCoa && str_starts_with($record->fromCoa->code, '111') ? $record->fromCoa->id : ($record->toCoa && str_starts_with($record->toCoa->code, '111') ? $record->toCoa->id : '')))
                    ->visible(fn($record) => $record->status === 'posted' && ($record->fromCoa && str_starts_with($record->fromCoa->code, '111') || $record->toCoa && str_starts_with($record->toCoa->code, '111'))),
                Action::make('post')->label('Posting Jurnal')->action(function ($record) {
                    app(CashBankService::class)->postTransfer($record);
                })->visible(fn($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check')
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashBankTransfers::route('/'),
            'create' => Pages\CreateCashBankTransfer::route('/create'),
            'edit' => Pages\EditCashBankTransfer::route('/{record}/edit'),
        ];
    }
}
