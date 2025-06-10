<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseConfirmationResource\Pages;
use App\Models\WarehouseConfirmation;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehouseConfirmationResource extends Resource
{
    protected static ?string $model = WarehouseConfirmation::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Warehouse';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Warehouse Confirmation')
                    ->schema([
                        Select::make('manufacturing_order_id')
                            ->label('Manufacturing Order')
                            ->preload()
                            ->searchable()
                            ->relationship('manufacturingOrder', 'mo_number')
                            ->required(),
                        Select::make('confirmed_by')
                            ->label('Confirmed By')
                            ->preload()
                            ->searchable()
                            ->preload('confirmedBy', 'name')
                            ->required(),
                        Textarea::make('note'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('manufacturing_order_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status'),
                TextColumn::make('confirmed_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->dateTime()
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
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListWarehouseConfirmations::route('/'),
            'create' => Pages\CreateWarehouseConfirmation::route('/create'),
            'edit' => Pages\EditWarehouseConfirmation::route('/{record}/edit'),
        ];
    }
}
