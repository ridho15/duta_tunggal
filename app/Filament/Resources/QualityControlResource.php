<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlResource\Pages;
use App\Filament\Resources\QualityControlResource\RelationManagers;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QualityControlResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quality Control')
                    ->schema([
                        Select::make('warehouse_id')
                            ->required()
                            ->label('Warehouse')
                            ->searchable()
                            ->preload()
                            ->relationship('warehouse', 'name'),
                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->relationship('product', 'name', function (Builder $query) {})
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "({$record->sku}) {$record->name}";
                            })
                            ->required(),
                        Select::make('inspected_by')
                            ->label('Inspected By')
                            ->relationship('inspectedBy', 'name')
                            ->preload()
                            ->searchable()
                            ->default(null),
                        TextInput::make('passed_quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('rejected_quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Textarea::make('notes')
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->searchable()
                    ->label('Warehouse'),
                TextColumn::make('purchaseReceiptItem.purchaseReceipt.purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('inspectedBy.name')
                    ->searchable()
                    ->label('Inspected By'),
                TextColumn::make('passed_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rejected_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function ($state) {
                        return $state == 1 ? 'success' : 'gray';
                    })
                    ->formatStateUsing(function ($state) {
                        return $state == 1 ? 'Sudah Proses' : 'Belum Proses';
                    }),
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('rak.name')
                    ->label("Rak")
                    ->searchable(),
                TextColumn::make('notes')
                    ->label('Notes'),
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

                TextColumn::make('date_send_stock')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('date_create_delivery_order')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
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
