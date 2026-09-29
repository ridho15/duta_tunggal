<?php

namespace App\Filament\Resources\PurchaseReceiptResource\RelationManagers;

use App\Models\PurchaseOrderItem;
use App\Support\OrderRequestQuantityLock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class PurchaseReceiptItemRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseReceiptItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('purchase_order_item_id'),
                Select::make('product_id')
                    ->label('Product')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->relationship('product', 'name', function ($get, Builder $query) {
                        $purchaseOrderId = $this->getOwnerRecord()?->purchase_order_id ?: $get('../../purchase_order_id');
                        return $query->whereHas('purchaseOrderItem', function (Builder $query) use ($purchaseOrderId) {
                            $query->where('purchase_order_id', $purchaseOrderId);
                        });
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->code})")
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set) {
                        $purchaseOrderId = $this->getOwnerRecord()?->purchase_order_id;
                        if ($purchaseOrderId && $state) {
                            $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
                                ->where('product_id', $state)
                                ->first();
                            if ($poItem) {
                                $set('purchase_order_item_id', $poItem->id);
                            }
                        }
                    }),
                TextInput::make('qty_received')
                    ->label('Quantity Received')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn ($get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $purchaseReceipt = $this->getOwnerRecord();
                            $productId = $get('product_id');
                            if (! $purchaseReceipt || ! $purchaseReceipt->purchase_order_id || ! $productId) {
                                return;
                            }
                            $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseReceipt->purchase_order_id)
                                ->where('product_id', $productId)
                                ->first();
                            if ($poItem) {
                                $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit(
                                    (int) $poItem->id,
                                    $record?->id ? (int) $record->id : null
                                );
                                if ((float) $value > (float) $limit['remaining_received']) {
                                    $fail("Quantity Received ({$value}) tidak boleh melebihi sisa PO ({$limit['remaining_received']}).");
                                }
                            }
                        }
                    ]),
                TextInput::make('qty_accepted')
                    ->label('Quantity Accepted')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn ($get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $purchaseReceipt = $this->getOwnerRecord();
                            $productId = $get('product_id');
                            if (! $purchaseReceipt || ! $purchaseReceipt->purchase_order_id || ! $productId) {
                                return;
                            }
                            $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseReceipt->purchase_order_id)
                                ->where('product_id', $productId)
                                ->first();
                            if ($poItem) {
                                $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit(
                                    (int) $poItem->id,
                                    $record?->id ? (int) $record->id : null
                                );
                                if ((float) $value > (float) $limit['remaining_accepted']) {
                                    $fail("Quantity Accepted ({$value}) tidak boleh melebihi sisa PO ({$limit['remaining_accepted']}).");
                                }
                            }
                        }
                    ]),
                TextInput::make('qty_rejected')
                    ->label('Quantity Rejected')
                    ->numeric(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->required(),
                Select::make('rak_id')
                    ->label('Rak')
                    ->relationship('rak', 'name'),
                TextInput::make('reason_rejected')
                    ->label('Reason Rejected'),
                FileUpload::make('photos')
                    ->label('Photos')
                    ->multiple()
                    ->directory('purchase-receipt-items')
                    ->visibility('public'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->formatStateUsing(function ($record) {
                        return '(' . $record->product->sku . ') ' . $record->product->name;
                    })
                    ->sortable(),
                TextColumn::make('qty_received')
                    ->label('Quantity Received')
                    ->sortable(),
                TextColumn::make('qty_accepted')
                    ->label('Quantity Accepted')
                    ->sortable(),
                TextColumn::make('qty_rejected')
                    ->label('Quantity Rejected')
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->rupiah()
                    ->getStateUsing(fn ($record) => (float) ($record->unit_price ?: ($record->purchaseOrderItem?->unit_price ?? 0))),
                TextColumn::make('total_price')
                    ->label('Total Nilai')
                    ->rupiah()
                    ->getStateUsing(fn ($record) => ((float) ($record->qty_accepted ?? $record->qty_received ?? 0)) * (float) ($record->unit_price ?: ($record->purchaseOrderItem?->unit_price ?? 0))),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->formatStateUsing(function ($record) {
                        if ($record->warehouse) {
                            return '(' . $record->warehouse->kode . ') ' . $record->warehouse->name;
                        }
                        return '';
                    })
                    ->sortable(),
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->sortable(),
                IconColumn::make('has_qc')
                    ->label('Has QC')
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        return $record->purchaseOrderItem->qualityControl()->exists();
                    }),
                ImageColumn::make('purchaseReceiptItemPhoto.photo_url')
                    ->label('Photos')
                    ->circular(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $purchaseOrderId = $this->getOwnerRecord()?->purchase_order_id;
                        if ($purchaseOrderId && ! empty($data['product_id']) && empty($data['purchase_order_item_id'])) {
                            $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
                                ->where('product_id', $data['product_id'])
                                ->first();
                            if ($poItem) {
                                $data['purchase_order_item_id'] = $poItem->id;
                            }
                        }
                        return $data;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->color('success')
                        ->mutateFormDataUsing(function (array $data): array {
                            $purchaseOrderId = $this->getOwnerRecord()?->purchase_order_id;
                            if ($purchaseOrderId && ! empty($data['product_id']) && empty($data['purchase_order_item_id'])) {
                                $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
                                    ->where('product_id', $data['product_id'])
                                    ->first();
                                if ($poItem) {
                                    $data['purchase_order_item_id'] = $poItem->id;
                                }
                            }
                            return $data;
                        }),
                    DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
