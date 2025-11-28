<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChartOfAccountResource\Pages;
use App\Filament\Resources\ChartOfAccountResource\Pages\ViewChartOfAccount;
use App\Filament\Resources\ChartOfAccountResource\RelationManagers\JournalEntryRelationManager;
use App\Models\ChartOfAccount;
use App\Services\ChartOfAccountService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Master Data';

    // Position Finance group as the 6th group
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->reactive()
                    ->suffixAction(Action::make('generateCode')
                        ->icon('heroicon-m-arrow-path') // ikon reload
                        ->tooltip('Generate Code')
                        ->action(function ($set, $get, $state) {
                            $chartOfAccountService = app(ChartOfAccountService::class);
                            $set('code', $chartOfAccountService->generateCode());
                        }))
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipe')
                    ->options(function () {
                        return [
                            'Asset' => 'Asset',
                            'Liability' => 'Liability',
                            'Equity' => 'Equity',
                            'Revenue' => 'Revenue',
                            'Expense' => 'Expense',
                            'Contra Asset' => 'Contra Asset',
                        ];
                    })
                    ->required(),
                Select::make('parent_id')
                    ->label('Induk Akun')
                    ->preload()
                    ->searchable()
                    ->options(function () {
                        return \App\Models\ChartOfAccount::query()
                            ->whereNull('deleted_at')
                            ->get()
                            ->mapWithKeys(function ($coa) {
                                return [$coa->id => $coa->code . ' - ' . $coa->name];
                            })
                            ->toArray();
                    })
                    ->nullable(),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                Textarea::make('description')
                    ->label('Description'),
                TextInput::make('opening_balance')
                    ->label('Saldo Awal')
                    ->numeric()
                    ->default(0)
                    ->indonesianMoney()
                    ->maxLength(255),
                TextInput::make('debit')
                    ->label('Debit')
                    ->numeric()
                    ->default(0)
                    ->indonesianMoney()
                    ->maxLength(255),
                TextInput::make('credit')
                    ->label('Kredit')
                    ->numeric()
                    ->default(0)
                    ->indonesianMoney()
                    ->maxLength(255),
                TextInput::make('ending_balance')
                    ->label('Saldo Akhir')
                    ->numeric()
                    ->default(0)
                    ->indonesianMoney()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Otomatis dihitung berdasarkan jenis akun'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('coaParent.code')
                        ->label('Induk Akun')
                        ->searchable()
                        ->formatStateUsing(function ($state, $record) {
                            if ($record->coaParent) {
                                return $record->coaParent->code . ' - ' . $record->coaParent->name;
                            }
                            return null;
                        }),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable(),
                TextColumn::make('opening_balance')
                    ->label('Saldo Awal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('debit')
                    ->label('Debit')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('credit')
                    ->label('Kredit')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('ending_balance')
                    ->label('Saldo Akhir')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe Akun')
                    ->options([
                        'Asset' => 'Asset',
                        'Liability' => 'Liability',
                        'Equity' => 'Equity',
                        'Revenue' => 'Revenue',
                        'Expense' => 'Expense',
                        'Contra Asset' => 'Contra Asset',
                    ])
                    ->placeholder('Pilih Tipe Akun'),
            ])
            ->actions([
                ActionGroup::make([
                    TableAction::make('ledger')
                        ->label('Buku Besar')
                        ->icon('heroicon-o-book-open')
                        ->color('info')
                        ->url(fn ($record) => static::getUrl('view', ['record' => $record->id])),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            JournalEntryRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChartOfAccounts::route('/'),
            'create' => Pages\CreateChartOfAccount::route('/create'),
            'view' => ViewChartOfAccount::route('/{record}'),
            'edit' => Pages\EditChartOfAccount::route('/{record}/edit'),
        ];
    }

    protected function afterSave($record): void
    {
        $record->updateEndingBalance();
    }
}
