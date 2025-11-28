<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReturnProductResource\Pages;
use App\Filament\Resources\ReturnProductResource\Pages\ViewReturnProduct;
use App\Http\Controllers\HelperController;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\ReturnProduct;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\ReturnProductService;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class ReturnProductResource extends Resource
{
    protected static ?string $model = ReturnProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Return Product')
                    ->schema([
                        TextInput::make('return_number')
                            ->label('Return Number')
                            ->required()
                            ->reactive()
                            ->prefixAction(ActionsAction::make('generateReturnNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Return Number')
                                ->action(function ($set, $get, $state) {
                                    $returnProductService = app(ReturnProductService::class);
                                    $set('return_number', $returnProductService->generateReturnNumber());
                                }))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Radio::make('from_model_type')
                            ->label('From Order')
                            ->inlineLabel()
                            ->reactive()
                            ->options(function () {
                                return [
                                    'App\Models\DeliveryOrder' => 'Delivery Order',
                                    'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                                ];
                            })
                            ->required(),
                        Select::make('from_model_id')
                            ->label(function ($set, $get) {
                                return 'From Order';
                            })
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->options(function ($set, $get, $state) {
                                if ($get('from_model_type') == 'App\Models\DeliveryOrder') {
                                    return DeliveryOrder::select(['id', 'do_number'])->get()->pluck('do_number', 'id');
                                } elseif ($get('from_model_type') == 'App\Models\PurchaseReceipt') {
                                    return PurchaseReceipt::with(['purchaseOrder' => function ($query) {
                                        $query->select(['id', 'po_number', 'order_date']);
                                    }])->select(['id', 'purchase_order_id'])->get()->pluck('purchaseOrder.po_number', 'id');
                                }
                                return [];
                            })
                            ->preload(),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->searchable()
                            ->preload()
                            ->relationship('warehouse', 'name')
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->string()
                            ->nullable(),
                        Repeater::make('returnProductItem')
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->columns(2)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                return $data;
                            })
                            ->relationship()
                            ->schema([
                                Radio::make('from_item_model_type')
                                    ->label('From Item Model')
                                    ->options([
                                        'App\Models\DeliveryOrderItem' => 'Sale Order item',
                                        'App\Models\PurchaseReceiptItem' => 'Purchase Receipt Item'
                                    ])->reactive()
                                    ->inlineLabel()
                                    ->default(function ($set, $get) {
                                        $from_model_type = $get('../../from_model_type');
                                        if ($from_model_type == 'App\Models\DeliveryOrder') {
                                            return 'App\Models\DeliveryOrderItem';
                                        } elseif ($from_model_type == 'App\Models\PurchaseReceipt') {
                                            return 'App\Models\PurchaseReceiptItem';
                                        }

                                        return '';
                                    })
                                    ->required(),
                                Select::make('from_item_model_id')
                                    ->label('From Item Model')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->options(function ($set, $get) {
                                        if ($get('from_item_model_type') == 'App\Models\DeliveryOrderItem') {
                                            $saleOrderId = $get('../../from_model_id');
                                            $listSaleOrderItem = SaleOrderItem::with(['product'])->where('sale_order_id', $saleOrderId)->select(['id', 'product_id'])->get();
                                            $items = [];
                                            foreach ($listSaleOrderItem as $index => $saleOrderItem) {
                                                $items[$saleOrderItem->id] = "({$saleOrderItem->product->sku}) {$saleOrderItem->product->name}";
                                            }

                                            return $items;
                                        } elseif ($get('from_item_model_type') == 'App\Models\PurchaseReceiptItem') {
                                            $items = [];
                                            $purchaseReceiptId = $get('../../from_model_id');
                                            $listPurchaseReceiptItem = PurchaseReceiptItem::with(['purchaseReceipt.purchaseOrder'])->where('purchase_receipt_id', $purchaseReceiptId)->get();
                                            foreach ($listPurchaseReceiptItem as $purchaseReceiptItem) {
                                                $items[$purchaseReceiptItem->id] = "({$purchaseReceiptItem->product->sku}) {$purchaseReceiptItem->product->name}";
                                            }
                                            return $items;
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $from_item_model_type = $get('from_item_model_type');
                                        $fromModelItem = null;
                                        if ($from_item_model_type == 'App\Models\DeliveryOrderItem') {
                                            $fromModelItem = SaleOrderItem::find($get('from_item_model_id'));
                                        } elseif ($from_item_model_type == 'App\Models\PurchaseReceiptItem') {
                                            $fromModelItem = PurchaseReceiptItem::find($get('from_item_model_id'));
                                        }

                                        if ($fromModelItem) {
                                            $set('product_id', $fromModelItem->product_id);
                                            $set('max_quantity', $fromModelItem->quantity);
                                            $set('quantity', $fromModelItem->quantity);
                                        }
                                    }),
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->disabled()
                                    ->relationship('product', 'id')
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if ($state > $get('max_quantity')) {
                                            $set('quantity', $get('max_quantity'));
                                            HelperController::sendNotification(isSuccess: false, title: "Information", message: "Quantity yang kamu masukkan lebih besar dari sumber order");
                                        }
                                    })
                                    ->default(0),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('rak', 'name', function ($get, Builder $query) {
                                        $query->where('warehouse_id', $get('../../warehouse_id'));
                                    })
                                    ->required(),
                                Radio::make('condition')
                                    ->label('Condition')
                                    ->options([
                                        'good' => 'Good',
                                        'damage' => "Damage",
                                        'repair' => "Repair"
                                    ])->inline()
                                    ->required(),
                                Textarea::make('note')
                                    ->label('Note')
                                    ->string()
                                    ->nullable()
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label('Return Number')
                    ->searchable(),
                TextColumn::make('from_model_type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('From Model'),
                TextColumn::make('from_model_id')
                    ->label('From Resource')
                    ->formatStateUsing(function ($record) {
                        if ($record->from_model_type == 'App\Models\DeliveryOrder') {
                            return $record->fromModel->do_number;
                        } elseif ($record->from_model_type == 'App\Models\PurchaseReceipt') {
                            return $record->fromModel->receipt_number;
                        } elseif ($record->from_model_type == 'App\Models\QualityControl') {
                            return $record->fromModel->qc_number;
                        }

                        return '-';
                    }),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable(),
                TextColumn::make('status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->label('Status')
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'approved' => 'success'
                        };
                    })
                    ->badge(),
                TextColumn::make('returnProductItem')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->product->sku}) {$state->product->name}";
                    })
                    ->searchable()
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
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
                        ->visible(function ($record) {
                            return $record->status == 'draft' || Auth::user()->hasRole('Super Admin');
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('approve return product') && $record->status == 'draft';
                        })
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $returnProductService = app(ReturnProductService::class);
                            $returnProductService->updateQuantityFromModel($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Berhasil mengubah status return product");
                        }),
                ])
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
            'index' => Pages\ListReturnProducts::route('/'),
            'create' => Pages\CreateReturnProduct::route('/create'),
            'view' => ViewReturnProduct::route('/{record}'),
            'edit' => Pages\EditReturnProduct::route('/{record}/edit'),
        ];
    }
}
