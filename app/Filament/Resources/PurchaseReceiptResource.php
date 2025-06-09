<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptResource\Pages;
use App\Filament\Resources\PurchaseReceiptResource\RelationManagers\PurchaseReceiptItemRelationManager;
use App\Models\Currency;
use App\Models\PurchaseReceipt;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions\ActionContainer;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Receipt')
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->preload()
                            ->searchable()
                            ->relationship('purchaseOrder', 'po_number')
                            ->required(),
                        DateTimePicker::make('receipt_date')
                            ->required(),
                        Select::make('received_by')
                            ->label('Received By')
                            ->preload()
                            ->searchable()
                            ->relationship('receivedBy', 'name')
                            ->required(),
                        Textarea::make('notes')
                            ->nullable(),
                        Repeater::make('purchaseReceiptItem')
                            ->relationship()
                            ->nullable()
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Purchase Receipt Item')
                            ->columns(3)
                            ->deletable(false)
                            ->defaultItems(0)
                            ->schema([
                                Select::make('product_id')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('product', 'name', function ($get, Builder $query) {
                                        $purchaseOrderId = $get('../../purchase_order_id');
                                        return $query->whereHas('purchaseOrderItem', function (Builder $query) use ($purchaseOrderId) {
                                            $query->where('purchase_order_id', $purchaseOrderId);
                                        });
                                    })
                                    ->reactive()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "{$record->sku} - {$record->name}";
                                    }),
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('warehouse', 'name'),
                                TextInput::make('qty_received')
                                    ->label('Quantity Received')
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                TextInput::make('qty_accepted')
                                    ->label('Quantity Accepted')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                TextInput::make('qty_rejected')
                                    ->label('Quantity Rejected')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Repeater::make('purchaseReceiptItemPhoto')
                                    ->relationship()
                                    ->addActionLabel('Tambah Photo')
                                    ->schema([
                                        FileUpload::make('photo_url')
                                            ->label('Photo')
                                            ->image()
                                            ->maxSize(1024)
                                            ->required(),
                                    ]),
                                Repeater::make('purchaseReceiptItemNominal')
                                    ->relationship()
                                    ->columnSpanFull()
                                    ->addActionLabel("Tambah Currency")
                                    ->defaultItems(0)
                                    ->addAction(function (Action $action) {
                                        return $action->disabled(function ($record) {
                                            if ($record && $record->is_sent == 1) {
                                                return true;
                                            }

                                            return false;
                                        });
                                    })
                                    ->deleteAction(function (Action $action) {
                                        return $action->requiresConfirmation()
                                            ->disabled(function ($record) {
                                                if ($record && $record->is_sent == 1) {
                                                    return true;
                                                }

                                                return false;
                                            });
                                    })
                                    ->columns(2)
                                    ->schema([
                                        Select::make('currency_id')
                                            ->label('Currency')
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function ($set, $state) {
                                                $currency = Currency::find($state);
                                                $set('symbol', $currency->symbol);
                                            })
                                            ->searchable()
                                            ->required()
                                            ->relationship('currency', 'name'),
                                        TextInput::make('nominal')
                                            ->label('Nominal')
                                            ->numeric()
                                            ->reactive()
                                            ->prefix(function ($get) {
                                                return $get('symbol');
                                            })
                                            ->default(0)
                                    ]),

                            ]),

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('receivedBy.name')
                    ->label('Received By')
                    ->searchable(),
                TextColumn::make('notes')
                    ->label('Notes')
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
            PurchaseReceiptItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReceipts::route('/'),
            'create' => Pages\CreatePurchaseReceipt::route('/create'),
            'edit' => Pages\EditPurchaseReceipt::route('/{record}/edit'),
        ];
    }
}
