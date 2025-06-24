<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturingOrderResource\Pages;
use App\Filament\Resources\ManufacturingOrderResource\Pages\ViewManufacturingOrder;
use App\Http\Controllers\HelperController;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class ManufacturingOrderResource extends Resource
{
    protected static ?string $model = ManufacturingOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Manufacturing Order')
                    ->schema([
                        TextInput::make('mo_number')
                            ->required()
                            ->reactive()
                            ->unique(ignoreRecord: true)
                            ->suffixAction(Action::make('generateMoNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate MO Number')
                                ->action(function ($set, $get, $state) {
                                    $manufacturingOrderService = app(ManufacturingService::class);
                                    $set('mo_number', $manufacturingOrderService->generateMoNumber());
                                }))
                            ->maxLength(255),
                        Select::make('product_id')
                            ->required()
                            ->label('Product')
                            ->preload()
                            ->reactive()
                            ->searchable()
                            ->relationship('product', 'name')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            }),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Select::make('product_unit_conversions_id')
                            ->label('Satuan Conversi')
                            ->reactive()
                            ->preload()
                            ->searchable()
                            ->relationship('productUnitConversion', 'id', function (Builder $query, $get) {
                                $query->where('product_id', $get('product_id'));
                            })
                            ->getOptionLabelFromRecordUsing(function (ProductUnitConversion $productUnitConversion) {
                                return "{$productUnitConversion->uom->name} {$productUnitConversion->nilai_konversi}";
                            })
                            ->createOptionForm(function ($get) {
                                return [
                                    Fieldset::make('Satuan Conversi')
                                        ->schema([
                                            Select::make('product_id')
                                                ->label('Product')
                                                ->preload()
                                                ->searchable()
                                                ->required()
                                                ->reactive()
                                                ->relationship('product', 'id')
                                                ->getOptionLabelFromRecordUsing(function (Product $product) {
                                                    return "({$product->sku}) {$product->name}";
                                                }),
                                            Select::make('uom_id')
                                                ->label('Satuan')
                                                ->preload()
                                                ->searchable()
                                                ->reactive()
                                                ->relationship('uom', 'name')
                                                ->required(),
                                            TextInput::make('nilai_konversi')
                                                ->label('Nilai Konversi')
                                                ->numeric()
                                                ->default(0)
                                                ->required()
                                        ])
                                ];
                            }),
                        DateTimePicker::make('start_date')
                            ->label('Tanggal Mulai'),
                        DateTimePicker::make('end_date')
                            ->label('Tanggal Selesai'),
                        Repeater::make('manufacturingOrderMaterial')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->schema([
                                Select::make('material_id')
                                    ->label('Material')
                                    ->helperText("Bahan Baku")
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->relationship('material', 'sku')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                Select::make('product_unit_conversions_id')
                                    ->label('Satuan')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->required()
                                    ->relationship('productUnitConversion', 'id', function (Builder $query, $get) {
                                        $query->where('product_id', $get('material_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (ProductUnitConversion $productUnitConversion) {
                                        return "{$productUnitConversion->uom->name} {$productUnitConversion->nilai_konversi}";
                                    }),
                                TextInput::make('qty_required')
                                    ->label('Quantity Required')
                                    ->helperText("Quantity yang dibutuhkan")
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                TextInput::make('qty_used')
                                    ->label('Quantity Used')
                                    ->numeric()
                                    ->helperText('Quantity yang digunakan')
                                    ->required()
                                    ->default(0),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->required()
                                    ->relationship('warehouse', 'name', function (Builder $query, $get) {
                                        $query->whereHas('inventoryStock', function ($query) use ($get) {
                                            $query->where('product_id', $get('material_id'));
                                        });
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                        return "({$warehouse->kode}) {$warehouse->name}";
                                    }),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->helperText(function ($get) {
                                        $inventoryStock = InventoryStock::where(function ($query) use ($get) {
                                            $query->where('warehouse_id', $get('warehouse_id'))
                                                ->orWhere('rak_id', $get('rak_id'));
                                        })->where('product_id', $get('material_id'))->first();
                                        if ($inventoryStock) {
                                            return "Stock Material : {$inventoryStock->qty_available}";
                                        }

                                        return "Stock Material : 0";
                                    })
                                    ->relationship('rak', 'id', function (Builder $query, $get) {
                                        $query->where('warehouse_id', $get('warehouse_id'));
                                    })->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                        return "({$rak->code}) {$rak->name}";
                                    }),
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mo_number')
                    ->label('MO Number')
                    ->searchable(),
                TextColumn::make('product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function ($query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('productUnitConversion')
                    ->label('Satuan')
                    ->formatStateUsing(function ($state) {
                        return "{$state->nilai_konversi} {$state->uom->name}";
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'in_progress' => 'warning',
                            'completed' => 'success',
                            default => '-'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    }),
                TextColumn::make('start_date')
                    ->dateTime()
                    ->label('Tanggal Mulai')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('end_date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Tanggal Selesai')
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
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    ActionsAction::make('Produksi')
                        ->label('Produksi')
                        ->color('success')
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request manufacturing order') && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            $manufacturingService = app(ManufacturingService::class);
                            $status = $manufacturingService->checkStockMaterial($record);
                            if ($status) {
                                $record->update([
                                    'status' => 'in_progress'
                                ]);
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing In Progress");
                            } else {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "Stock material tidak mencukupi");
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
            'index' => Pages\ListManufacturingOrders::route('/'),
            'create' => Pages\CreateManufacturingOrder::route('/create'),
            'view' => ViewManufacturingOrder::route('/{record}'),
            'edit' => Pages\EditManufacturingOrder::route('/{record}/edit'),
        ];
    }
}
