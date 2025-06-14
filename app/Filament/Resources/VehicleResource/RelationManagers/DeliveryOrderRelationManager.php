<?php

namespace App\Filament\Resources\VehicleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeliveryOrderRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryOrder';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Field::make('Form Delivery Order')
                    ->schema([
                        Select::make('from_sales')
                            ->label('From Sales')
                            ->nullable(),
                        DateTimePicker::make('delivery_date')
                            ->required(),
                        Select::make('driver_id')
                            ->label('Driver')
                            ->searchable()
                            ->preload()
                            ->relationship('driver', 'name')
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->nullable(),
                        Repeater::make('deliveryOrderItem')
                            ->schema([])
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
