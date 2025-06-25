<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountPayableResource\Pages;
use App\Models\AccountPayable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;

class AccountPayableResource extends Resource
{
    protected static ?string $model = AccountPayable::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('invoice_id')
                    ->required()
                    ->preload()
                    ->searchable()
                    ->label('Invoice')
                    ->relationship('invoice', 'invoice_number'),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->relationship('supplier', 'name'),
                TextInput::make('total')
                    ->required()
                    ->numeric(),
                TextInput::make('paid')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                TextInput::make('remaining')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->sortable()
                    ->money('idr'),
                TextColumn::make('paid')
                    ->label('Paid')
                    ->sortable()
                    ->money('idr'),
                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->sortable()
                    ->money('idr'),
                TextColumn::make('status')
                    ->label('Status')
                    ->color(function ($state) {
                        return match ($state) {
                            'Belum Lunas' => 'warning',
                            'Lunas' => 'success',
                            default => '-'
                        };
                    })->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->badge(),
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
                ViewAction::make()
                    ->color('primary')
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountPayables::route('/'),
            // 'create' => Pages\CreateAccountPayable::route('/create'),
            // 'edit' => Pages\EditAccountPayable::route('/{record}/edit'),
        ];
    }
}
