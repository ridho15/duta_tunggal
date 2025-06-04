<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlResource\Pages;
use App\Filament\Resources\QualityControlResource\RelationManagers;
use App\Models\QualityControl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QualityControlResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('warehouse_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('purchase_receipt_item_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('inspected_by')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('passed_quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('rejected_quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('reason_reject')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('status')
                    ->required(),
                Forms\Components\TextInput::make('product_id')
                    ->required()
                    ->numeric(),
                Forms\Components\DateTimePicker::make('date_send_stock'),
                Forms\Components\DateTimePicker::make('date_create_delivery_order'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchase_receipt_item_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inspected_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('passed_quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejected_quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('product_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_send_stock')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_create_delivery_order')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListQualityControls::route('/'),
            'create' => Pages\CreateQualityControl::route('/create'),
            'edit' => Pages\EditQualityControl::route('/{record}/edit'),
        ];
    }
}
