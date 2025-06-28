<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlResource\Pages;
use App\Filament\Resources\QualityControlResource\Pages\ViewQualityControl;
use App\Http\Controllers\HelperController;
use App\Models\InventoryStock;
use App\Models\Production;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\ReturnProduct;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use App\Services\ReturnProductService;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;

class QualityControlResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Gudang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quality Control')
                    ->schema([
                        Section::make('From / Resource')
                            ->description('Sumber Quality Control')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Radio::make('from_model_type')
                                    ->label('From Type')
                                    ->options([
                                        'App\Models\PurchaseReceiptItem' => 'Receipt Item',
                                        'App\Models\Production' => 'Production'
                                    ])
                                    ->reactive()
                                    ->inlineLabel()
                                    ->required(),
                                Select::make('from_model_id')
                                    ->label(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\PurchaseReceiptItem') {
                                            return "From Receipt Item";
                                        } elseif ($get('from_model_type') == 'App\Models\Production') {
                                            return "From Production";
                                        }

                                        return "From";
                                    })
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if ($get('from_model_type') == "App\Models\PurchaseReceiptItem") {
                                            $purchaseReceiptItem = PurchaseReceiptItem::find($state);
                                            $set('product_id', $purchaseReceiptItem->product_id);
                                        } elseif ($get('from_model_type') == "App\Models\Production") {
                                            $production = Production::find($state);
                                            $set('product_id', $production->manufacturingOrder->product_id);
                                        }
                                    })
                                    ->options(function ($set, $get, $state) {
                                        if ($get('from_model_type') == 'App\Models\PurchaseReceiptItem') {
                                            $items = [];
                                            $listPurchaseReceiptItem = PurchaseReceiptItem::with(['purchaseReceipt.purchaseOrder'])->whereHas('purchaseReceipt', function (Builder $query) {
                                                $query->whereHas('purchaseOrder', function ($query) {
                                                    $query->where('status', '!=', 'draft');
                                                });
                                            })->get();
                                            foreach ($listPurchaseReceiptItem as $purchaseReceiptItem) {
                                                $items[$purchaseReceiptItem->id] = $purchaseReceiptItem->purchaseReceipt->purchaseOrder->po_number;
                                            }
                                            return $items;
                                        } elseif ($get('from_model_type') == 'App\Models\Production') {
                                            return Production::where('status', 'finished')->get()->pluck('production_number', 'id');
                                        }
                                        
                                        return [];
                                    })
                                    ->required()
                            ]),
                        TextInput::make('qc_number')
                            ->label('Quality Control Number')
                            ->required()
                            ->reactive()
                            ->suffixAction(ActionsAction::make('generateQcNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate QC Number')
                                ->action(function ($set, $get, $state) {
                                    $qualityControlService = app(QualityControlService::class);
                                    $set('qc_number', $qualityControlService->generateQcNumber());
                                }))
                            ->unique(ignoreRecord: true),
                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->reactive()
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
                        Select::make('warehouse_id')
                            ->required()
                            ->label('Gudang')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->relationship('warehouse', 'name', function (Builder $query, $get) {
                                $query->whereHas('inventoryStock', function ($query) use ($get) {
                                    $query->where('product_id', $get('product_id'));
                                });
                            })
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode} {$warehouse->name})";
                            }),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->preload()
                            ->reactive()
                            ->searchable()
                            ->helperText(function ($get) {
                                $inventoryStock = InventoryStock::where('product_id', $get('product_id'))
                                    ->where('warehouse_id', $get('warehouse_id'))
                                    ->where('rak_id', $get('rak_id'))->first();
                                if ($inventoryStock) {
                                    return "Jumlah stock tersedia pada rak : {$inventoryStock->qty_available}";
                                }
                                return "Jumlah stock tersedia pada rak : -";
                            })
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                return "({$rak->code}) {$rak->name}";
                            })
                            ->nullable(),
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
                TextColumn::make('qc_number')
                    ->label('Quality Control Number')
                    ->searchable(),
                TextColumn::make('fromModel.purchaseReceipt.receipt_number')
                    ->label("Receipt Number")
                    ->formatStateUsing(function ($record) {
                        if ($record->from_model_type == 'App\Models\PurchaseReceiptItem') {
                            return $record->fromModel->purchaseReceipt->receipt_number;
                        }

                        return null;
                    })->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('fromModel', function ($query) use ($search) {
                            $query->whereHas('purchaseReceipt', function ($query) use ($search) {
                                $query->where('receipt_number', 'LIKE', '%' . $search . '%');
                            });
                        });
                    }),
                TextColumn::make('fromModel.production_number')
                    ->label('Production Number')
                    ->formatStateUsing(function ($record) {
                        if ($record->from_model_type == 'App\Models\Production') {
                            return $record->fromModel->production_number;
                        }

                        return null;
                    })->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('fromModel', function ($query) use ($search) {
                            $query->where('production_number', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function ($query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
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
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
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
                    DeleteAction::make(),
                    Action::make('Complete')
                        ->color('success')
                        ->label('Complete')
                        ->requiresConfirmation()
                        ->hidden(function ($record) {
                            return $record->status == 1;
                        })
                        ->icon('heroicon-o-check-badge')
                        ->form(function ($record) {
                            if ($record->rejected_quantity > 0) {
                                return [
                                    Fieldset::make('Form Return Product')
                                        ->columns(1)
                                        ->schema([
                                            TextInput::make('return_number')
                                                ->label('Return Number')
                                                ->required()
                                                ->reactive()
                                                ->suffixAction(ActionsAction::make('generateReturnNumber')
                                                    ->icon('heroicon-m-arrow-path') // ikon reload
                                                    ->tooltip('Generate Return Number')
                                                    ->action(function ($set, $get, $state) {
                                                        $returnProductService = app(ReturnProductService::class);
                                                        $set('return_number', $returnProductService->generateReturnNumber());
                                                    }))
                                                ->maxLength(255),
                                            Select::make('warehouse_id')
                                                ->label('Warehouse')
                                                ->preload()
                                                ->reactive()
                                                ->searchable()
                                                ->relationship('warehouse', 'id')
                                                ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                                    return "({$warehouse->kode}) {$warehouse->name}";
                                                })
                                                ->required(),
                                            Select::make('rak_id')
                                                ->label('Rak')
                                                ->preload()
                                                ->reactive()
                                                ->searchable()
                                                ->relationship('rak', 'id', function (Builder $query, $get) {
                                                    $query->where('warehouse_id', $get('warehouse_id'));
                                                })
                                                ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                                    return "({$rak->code}) {$rak->name}";
                                                })
                                                ->nullable(),
                                            Textarea::make('reason')
                                                ->label('Reason')
                                                ->nullable()
                                                ->string()
                                                ->default($record->reason_reject)
                                        ])
                                ];
                            }

                            return null;
                        })
                        ->action(function (array $data, $record) {
                            if (array_key_exists('return_number', $data)) {
                                $returnProduct = ReturnProduct::where('return_number', $data['return_number'])->first();
                                if ($returnProduct) {
                                    return HelperController::sendNotification(isSuccess: false, title: "Information", message: "Return Number sudah digunakan !");
                                }
                            }
                            $qualityControlService = app(QualityControlService::class);
                            $qualityControlService->completeQualityControl($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Completed");
                            if ($record->from_model_type == 'App\Models\PurchaseReceiptItem') {
                                $qualityControlService->checkPenerimaanBarang($record);
                            }
                        })
                ])
            ], position: ActionsPosition::BeforeColumns)
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
            'view' => ViewQualityControl::route('/{record}'),
            'edit' => Pages\EditQualityControl::route('/{record}/edit'),
        ];
    }
}
