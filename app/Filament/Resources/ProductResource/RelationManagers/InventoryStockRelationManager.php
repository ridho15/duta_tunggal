<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Rak;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryStockRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryStock';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('warehouse_id')
                    ->label('Gudang')
                    ->required()
                    ->preload()
                    ->searchable()
                    ->relationship('warehouse', 'name')
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    }),
                TextInput::make('qty_available')
                    ->label('Quantity Available')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('qty_reserved')
                    ->label('Quantity Reserved')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Select::make('rak_id')
                    ->label('Rak')
                    ->preload()
                    ->searchable()
                    ->relationship('rak', 'code')
                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                        return "({$rak->code}) {$rak->name}";
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->label('Warehouse'),
                TextColumn::make('qty_available')
                    ->label('Quantity Available')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qty_reserved')
                    ->label('Quantity Reserved')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
