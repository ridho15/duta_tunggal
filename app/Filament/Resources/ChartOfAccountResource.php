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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 21;

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
                            'Expense' => 'Expense'
                        ];
                    })
                    ->required(),
                TextInput::make('level')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('parent_id')
                    ->label('Induk Akun')
                    ->preload()
                    ->searchable()
                    ->relationship('coaParent', 'code')
                    ->nullable(),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                Textarea::make('description')
                    ->label('Description'),
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
                TextColumn::make('level')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coaParent.code')
                    ->label('Induk Akun')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable(),
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
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
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
}
