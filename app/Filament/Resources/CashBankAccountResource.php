<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashBankAccountResource\Pages;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class CashBankAccountResource extends Resource
{
    protected static ?string $model = CashBankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Akun Kas/Bank';

    protected static ?int $navigationSort = 7;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Akun')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('bank_name')
                    ->label('Nama Bank')
                    ->nullable()
                    ->maxLength(255),

                Forms\Components\TextInput::make('account_number')
                    ->label('No. Rekening')
                    ->nullable()
                    ->maxLength(64),

                Forms\Components\Select::make('coa_id')
                    ->label('Akun COA')
                    ->options(fn () => ChartOfAccount::orderBy('code')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('bank_name')->label('Bank')->searchable()->sortable(),
                TextColumn::make('account_number')->label('No. Rekening')->toggleable(),
                TextColumn::make('coa.name')->label('COA')->toggleable()->limit(30),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashBankAccounts::route('/'),
            'create' => Pages\CreateCashBankAccount::route('/create'),
            'edit' => Pages\EditCashBankAccount::route('/{record}/edit'),
        ];
    }
}
