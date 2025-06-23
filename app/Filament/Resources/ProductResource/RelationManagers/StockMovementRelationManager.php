<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class StockMovementRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovement';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('warehouse_id')
                    ->label('Gudang')
                    ->preload()
                    ->searchable()
                    ->relationship('warehouse', 'id')
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    })
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->default(0)
                    ->numeric()
                    ->required(),
                Radio::make('type')
                    ->label('Type')
                    ->options([
                        'purchase' => 'Purchase',
                        'sales' => 'Sales',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'manufacture_in' => 'Manufacture In',
                        'manufacture_out' => 'Manufacture Out',
                    ])
                    ->required(),
                TextInput::make('reference_id')
                    ->maxLength(255)
                    ->default(null),
                DateTimePicker::make('date')
                    ->required(),
                Textarea::make('notes')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable(),
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->color(function ($state) {
                        return match ($state) {
                            'transfer_in' => 'primary',
                            'transfer_out' => 'warning',
                            'manufacture_in' => 'info',
                            'manufacture_out' => 'warning',
                            default => 'primary',
                        };
                    })->formatStateUsing(function ($state) {
                        return match ($state) {
                            'purchase' => 'Purchase',
                            'sales' => 'Sales',
                            'transfer_in' => 'Transfer In',
                            'transfer_out' => 'Transfer Out',
                            'manufacture_in' => 'Manufacture In',
                            'manufacture_out' => 'Manufacture Out',
                            default => '-'
                        };
                    })
                    ->badge(),
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
