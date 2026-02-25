<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Filament\Resources\StockMovementResource\Pages\ViewStockMovement;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Stock Movement')
                    ->schema([
                        Select::make('product_id')
                            ->preload()
                            ->label('Product')
                            ->searchable()
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->required(),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', 1);
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', 1)
                                    ->where(function ($q) use ($search) {
                                        $q->where('perusahaan', 'like', "%{$search}%")
                                          ->orWhere('kode', 'like', "%{$search}%");
                                    });
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->required(),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                return $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->required(),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Radio::make('type')
                            ->label('Type')
                            ->inlineLabel()
                            ->options(function () {
                                return [
                                    'purchase_in' => 'Purchase In',
                                    'sales' => 'Sales',
                                    'transfer_in' => 'Transfer In',
                                    'transfer_out' => 'Transfer Out',
                                    'manufacture_in' => 'Manufacture In',
                                    'manufacture_out' => 'Manufacture Out',
                                    'adjustment_in' => 'Adjustment In',
                                    'adjustment_out' => 'Adjustment Out',
                                ];
                            })
                            ->required(),
                        TextInput::make('reference_id')
                            ->maxLength(255)
                            ->default(null),
                        Select::make('source')
                            ->label('Source')
                            ->options([
                                'App\Models\SaleOrder' => 'Sales Order',
                                'App\Models\PurchaseOrder' => 'Purchase Order',
                                'App\Models\DeliveryOrder' => 'Delivery Order',
                                'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                                'App\Models\StockTransfer' => 'Stock Transfer',
                                'App\Models\ManufacturingOrder' => 'Manufacturing Order',
                                'App\Models\StockAdjustment' => 'Stock Adjustment',
                            ])
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->default(function ($record) {
                                return $record ? $record->from_model_type : null;
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('source_number', null); // Reset source number when source changes
                            }),
                        Select::make('source_number')
                            ->label('Source Number')
                            ->searchable()
                            ->preload()
                            ->options(function ($get) {
                                $source = $get('source');
                                if (!$source) return [];

                                switch ($source) {
                                    case 'App\Models\SaleOrder':
                                        return \App\Models\SaleOrder::pluck('so_number', 'id');
                                    case 'App\Models\PurchaseOrder':
                                        return \App\Models\PurchaseOrder::pluck('po_number', 'id');
                                    case 'App\Models\DeliveryOrder':
                                        return \App\Models\DeliveryOrder::pluck('do_number', 'id');
                                    case 'App\Models\PurchaseReceipt':
                                        return \App\Models\PurchaseReceipt::pluck('receipt_number', 'id');
                                    case 'App\Models\StockTransfer':
                                        return \App\Models\StockTransfer::pluck('transfer_number', 'id');
                                    case 'App\Models\ManufacturingOrder':
                                        return \App\Models\ManufacturingOrder::pluck('mo_number', 'id');
                                    case 'App\Models\StockAdjustment':
                                        return \App\Models\StockAdjustment::pluck('adjustment_number', 'id');
                                    default:
                                        return [];
                                }
                            })
                            ->default(function ($record) {
                                return $record ? $record->from_model_id : null;
                            })
                            ->visible(function ($get) {
                                return !empty($get('source'));
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Set the from_model_type and from_model_id based on selection
                                $source = $get('source');
                                if ($source && $state) {
                                    $set('from_model_type', $source);
                                    $set('from_model_id', $state);
                                }
                            }),
                        TextInput::make('from_model_type')
                            ->hidden(),
                        TextInput::make('from_model_id')
                            ->hidden(),
                        DatePicker::make('date')
                            ->required(),
                        Textarea::make('notes')
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function (Builder $query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    }),
                TextColumn::make('rak')
                    ->label('Rak')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('rak', function ($query) use ($search) {
                            return $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    }),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->color(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'success',
                            'sales' => 'danger',
                            'transfer_in' => 'primary',
                            'transfer_out' => 'warning',
                            'manufacture_in' => 'info',
                            'manufacture_out' => 'warning',
                            'adjustment_in' => 'secondary',
                            'adjustment_out' => 'danger',
                            default => 'gray',
                        };
                    })->formatStateUsing(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'Purchase In',
                            'sales' => 'Sales',
                            'transfer_in' => 'Transfer In',
                            'transfer_out' => 'Transfer Out',
                            'manufacture_in' => 'Manufacture In',
                            'manufacture_out' => 'Manufacture Out',
                            'adjustment_in' => 'Adjustment In',
                            'adjustment_out' => 'Adjustment Out',
                            default => '-'
                        };
                    })
                    ->badge(),
                TextColumn::make('reference_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('fromModel')
                    ->label('Source')
                    ->formatStateUsing(function ($record) {
                        if ($record->fromModel) {
                            $modelType = $record->from_model_type;
                            $modelName = match ($modelType) {
                                'App\Models\SaleOrder' => 'Sales Order',
                                'App\Models\PurchaseOrder' => 'Purchase Order',
                                'App\Models\DeliveryOrder' => 'Delivery Order',
                                'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                                'App\Models\StockTransfer' => 'Stock Transfer',
                                'App\Models\ManufacturingOrder' => 'Manufacturing Order',
                                'App\Models\StockAdjustment' => 'Stock Adjustment',
                                default => 'Unknown'
                            };

                            $sourceNumber = match ($modelType) {
                                'App\Models\SaleOrder' => $record->fromModel->so_number ?? 'N/A',
                                'App\Models\PurchaseOrder' => $record->fromModel->po_number ?? 'N/A',
                                'App\Models\DeliveryOrder' => $record->fromModel->do_number ?? 'N/A',
                                'App\Models\PurchaseReceipt' => $record->fromModel->receipt_number ?? 'N/A',
                                'App\Models\StockTransfer' => $record->fromModel->transfer_number ?? 'N/A',
                                'App\Models\ManufacturingOrder' => $record->fromModel->mo_number ?? 'N/A',
                                'App\Models\StockAdjustment' => $record->fromModel->adjustment_number ?? 'N/A',
                                default => 'N/A'
                            };

                            return $modelName . ' - ' . $sourceNumber;
                        }
                        return '-';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        // This is complex to search, so we'll skip for now
                    }),
                TextColumn::make('date')
                    ->dateTime()
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
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Product')
                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                        return "({$product->sku}) {$product->name}";
                    }),
                SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Gudang')
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    }),
                SelectFilter::make('rak')
                    ->relationship('rak', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Rak')
                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                        return "({$rak->code}) {$rak->name}";
                    }),
                SelectFilter::make('type')
                    ->options([
                        'purchase_in' => 'Purchase In',
                        'sales' => 'Sales',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'manufacture_in' => 'Manufacture In',
                        'manufacture_out' => 'Manufacture Out',
                        'adjustment_in' => 'Adjustment In',
                        'adjustment_out' => 'Adjustment Out',
                    ])
                    ->multiple()
                    ->label('Type'),
                SelectFilter::make('source_type')
                    ->label('Source Type')
                    ->options([
                        'App\Models\SaleOrder' => 'Sales Order',
                        'App\Models\PurchaseOrder' => 'Purchase Order',
                        'App\Models\DeliveryOrder' => 'Delivery Order',
                        'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                        'App\Models\StockTransfer' => 'Stock Transfer',
                        'App\Models\ManufacturingOrder' => 'Manufacturing Order',
                        'App\Models\StockAdjustment' => 'Stock Adjustment',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => $query->where('from_model_type', $value)
                        );
                    }),
                Filter::make('date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Date From'),
                        DatePicker::make('date_to')
                            ->label('Date To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->label('Date Range'),
            ])
            ->actions([
                ViewAction::make()
                    ->color('primary')
            ], position:ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Stock Movement</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Stock Movement adalah log pergerakan stok yang mencatat semua aktivitas masuk/keluar inventory secara otomatis dari berbagai transaksi.</li>' .
                            '<li><strong>Tipe Movement:</strong> <em>Purchase In</em> (pembelian), <em>Sales</em> (penjualan), <em>Transfer In/Out</em> (pemindahan antar gudang), <em>Manufacture In/Out</em> (produksi), <em>Adjustment In/Out</em> (penyesuaian stok).</li>' .
                            '<li><strong>Tracking:</strong> Setiap movement tercatat dengan produk, gudang, rak, quantity, tanggal, dan reference ke transaksi asal.</li>' .
                            '<li><strong>Read-Only:</strong> Data movement bersifat read-only karena di-generate otomatis dari transaksi sebenarnya.</li>' .
                            '<li><strong>Reporting:</strong> Digunakan untuk audit trail inventory, stock history, dan analisis pergerakan stok per periode.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan semua modul transaksi (Sales, Purchase, Manufacturing, Transfer, Adjustment).</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('date', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => ViewStockMovement::route('/{record}'),
            // 'create' => Pages\CreateStockMovement::route('/create'),
            // 'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}
