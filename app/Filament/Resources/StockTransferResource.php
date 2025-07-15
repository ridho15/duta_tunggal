<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransferResource\Pages;
use App\Filament\Resources\StockTransferResource\Pages\ViewStockTransfer;
use App\Http\Controllers\HelperController;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
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
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?string $modelLabel = 'Transfer Stock';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Stock Transfer')
                    ->schema([
                        TextInput::make('transfer_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->suffixAction(Action::make('generatePoNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate PO Number')
                                ->action(function ($set, $get, $state) {
                                    $stockTransferService = app(StockTransferService::class);
                                    $set('transfer_number', $stockTransferService->generateTransferNumber());
                                }))
                            ->maxLength(255),
                        DateTimePicker::make('transfer_date')
                            ->label('Tanggal Transfer')
                            ->required(),
                        Select::make('from_warehouse_id')
                            ->label('Dari Gudang')
                            ->preload()
                            ->searchable()
                            ->relationship('fromWarehouse', 'id')
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode}) {$warehouse->name}";
                            })
                            ->required(),
                        Select::make('to_warehouse_id')
                            ->label('Ke Gudang')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->relationship('toWarehouse', 'id', function (Builder $query, $get) {
                                $query->where('id', '!=', $get('../../from_warehouse_id'));
                            })
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode}) {$warehouse->name}";
                            })
                            ->required(),
                        Repeater::make('stockTransferItem')
                            ->relationship()
                            ->reactive()
                            ->label('Transfer Items')
                            ->columnSpanFull()
                            ->columns(2)
                            ->defaultItems(0)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->helperText(function ($get) {
                                        $inventoryStock = InventoryStock::where('warehouse_id', $get('../../from_warehouse_id'))
                                            ->where('product_id', $get('product_id'))->first();
                                        if ($inventoryStock) {
                                            return "Rak : ({$inventoryStock->rak->code}) {$inventoryStock->rak->name}";
                                        }

                                        return "Rak : -";
                                    })
                                    ->relationship('product', 'id', function (Builder $query, $get) {
                                        $query->whereHas('inventoryStock', function (Builder $query) use ($get) {
                                            $query->where('warehouse_id', $get('../../from_warehouse_id'));
                                        });
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    })->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(1)
                                    ->required(),
                                Select::make('from_warehouse_id')
                                    ->label('Dari Gudang')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->default(function ($get) {
                                        return $get('../../from_warehouse_id');
                                    })
                                    ->relationship('fromWarehouse', 'id', function (Builder $query, $get) {
                                        $query->where('id', $get('../../from_warehouse_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                        return "({$warehouse->kode}) {$warehouse->name}";
                                    })
                                    ->required(),
                                Select::make('from_rak_id')
                                    ->label('Dari Rak')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->helperText(function ($get) {
                                        $inventoryStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('rak_id', $get('from_rak_id'))->first();
                                        if ($inventoryStock) {
                                            return "Jumlah stock {$inventoryStock->qty_available}";
                                        }

                                        return "Jumlah Stock 0";
                                    })
                                    ->relationship('fromRak', 'id', function (Builder $query, $get) {
                                        $query->where('warehouse_id', $get('from_warehouse_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                        return "({$rak->code}) {$rak->name}";
                                    })
                                    ->required(),
                                Select::make('to_warehouse_id')
                                    ->label('Ke Gudang')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->relationship('toWarehouse', 'id', function (Builder $query, $get) {
                                        $query->where('id', $get('../../to_warehouse_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                        return "({$warehouse->kode}) {$warehouse->name}";
                                    })
                                    ->default(function ($get) {
                                        return $get('../../to_warehouse_id');
                                    })
                                    ->required(),
                                Select::make('to_rak_id')
                                    ->label('Ke Rak')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->helperText(function ($get) {
                                        $inventoryStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('rak_id', $get('to_rak_id'))->first();
                                        if ($inventoryStock) {
                                            return "Jumlah Stock {$inventoryStock->qty_available}";
                                        }

                                        return "Jumlah Stock 0";
                                    })
                                    ->relationship('toRak', 'id', function (Builder $query, $get) {
                                        $query->where('warehouse_id', $get('to_warehouse_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                        return "({$rak->code}) {$rak->name}";
                                    })
                                    ->required()
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transfer_number')
                    ->label('Transfer Number')
                    ->searchable(),
                TextColumn::make('transfer_date')
                    ->label('Tanggal Transfer')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('fromWarehouse')
                    ->label('Dari Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('fromWarehouse', function (Builder $query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('toWarehouse')
                    ->label('Ke Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('toWarehouse', function (Builder $query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'Draft' => 'gray',
                            'Request' => 'primary',
                            'Approved' => 'success',
                            'Reject' => 'danger',
                            'Cancelled' => 'warning',
                            default => '-'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    }),
                TextColumn::make('stockTransferItem.product.name')
                    ->label('Items')
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
                    DeleteAction::make(),
                    ActionsAction::make('request_transfer')
                        ->label('Request Transfer')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request stock transfer') && $record->status == 'Draft';
                        })
                        ->icon('heroicon-o-arrow-down-circle')
                        ->action(function ($record) {
                            $stockTransferService = app(StockTransferService::class);
                            $stockTransferService->requestTransfer($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Berhasil mengirimkan request stock transfer");
                        }),
                    ActionsAction::make('approve')
                        ->label('Approve')
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response stock transfer') && $record->status == 'Request';
                        })
                        ->action(function ($record) {
                            $stockTransferService = app(StockTransferService::class);
                            $stockTransferService->approveStockTransfer($record);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Request transfer stock berhasil di approve");
                        }),
                    ActionsAction::make('reject')
                        ->label('Reject')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response stock transfer') && $record->status == 'Request';
                        })
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'Reject'
                            ]);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Request transfer stock berhasil di reject");
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
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'view' => ViewStockTransfer::route('/{record}'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
