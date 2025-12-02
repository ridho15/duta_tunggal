<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseConfirmationResource\Pages;
use App\Models\WarehouseConfirmation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Services\SalesOrderService;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WarehouseConfirmationResource extends Resource
{
    protected static ?string $model = WarehouseConfirmation::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Warehouse Confirmations';

    protected static ?string $pluralLabel = 'Warehouse Confirmations';

    public static function canViewAny(): bool
    {
        return Auth::check() && Auth::user()->can('view any warehouse confirmation');
    }

    public static function canView($record): bool
    {
        return Auth::check() && Auth::user()->can('view warehouse confirmation');
    }

    public static function canCreate(): bool
    {
        return Auth::check() && Auth::user()->can('create warehouse confirmation');
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && Auth::user()->can('update warehouse confirmation');
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && Auth::user()->can('delete warehouse confirmation');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Warehouse Confirmation')
                    ->schema([
                        Radio::make('confirmation_type')
                            ->label('Confirmation Type')
                            ->options([
                                'sales_order' => 'Sales Order Confirmation',
                                'manufacturing_order' => 'Manufacturing Order Confirmation'
                            ])
                            ->default('sales_order')
                            ->required()
                            ->reactive(),

                        // Sales Order Section
                        Select::make('sale_order_id')
                            ->label('Sales Order')
                            ->preload()
                            ->searchable()
                            ->relationship('saleOrder', 'so_number', function (Builder $query) {
                                $query->where('status', 'approved')
                                      ->where('tipe_pengiriman', 'Kirim Langsung')
                                      ->where(function ($q) {
                                          $q->whereDoesntHave('warehouseConfirmation');
                                          // In edit context, also allow the current SO if it exists
                                          if (request()->route('record')) {
                                              $wc = \App\Models\WarehouseConfirmation::find(request()->route('record'));
                                              if ($wc && $wc->sale_order_id) {
                                                  $q->orWhere('id', $wc->sale_order_id);
                                              }
                                          }
                                      });
                            })
                            ->getOptionLabelFromRecordUsing(function (SaleOrder $saleOrder) {
                                return $saleOrder->so_number . ' - ' . $saleOrder->customer->name;
                            })
                            ->visible(function ($get) {
                                return $get('confirmation_type') === 'sales_order';
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state) {
                                    $saleOrder = SaleOrder::with('saleOrderItem.product')->find($state);
                                    if ($saleOrder) {
                                        // Create confirmation items directly
                                        $confirmationItems = [];
                                        foreach ($saleOrder->saleOrderItem as $item) {
                                            $confirmationItems[] = [
                                                'sale_order_item_id' => $item->id,
                                                'product_name' => $item->product->name ?? 'Unknown Product',
                                                'requested_qty' => $item->quantity,
                                                'confirmed_qty' => $item->quantity,
                                                'warehouse_id' => $item->warehouse_id,
                                                'rak_id' => $item->rak_id,
                                                'status' => 'request'
                                            ];
                                        }
                                        $set('confirmation_items', $confirmationItems);
                                    }
                                }
                            }),

                        // Manufacturing Order Section (existing)
                        Select::make('manufacturing_order_id')
                            ->label('Manufacturing Order')
                            ->preload()
                            ->searchable()
                            ->relationship('manufacturingOrder', 'mo_number')
                            ->visible(function ($get) {
                                return $get('confirmation_type') === 'manufacturing_order';
                            })
                            ->required(),

                        // Confirmation Items for Sales Order
                        Repeater::make('confirmation_items')
                            ->label('Confirmation Items')
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('product_name')
                                    ->label('Product')
                                    ->disabled(),

                                TextInput::make('requested_qty')
                                    ->label('Requested Qty')
                                    ->disabled()
                                    ->numeric(),

                                TextInput::make('confirmed_qty')
                                    ->label('Confirmed Qty')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $requestedQty = $get('requested_qty') ?? 0;
                                        if ($state == $requestedQty) {
                                            $set('status', 'confirmed');
                                        } elseif ($state > 0 && $state < $requestedQty) {
                                            $set('status', 'partial_confirmed');
                                        } elseif ($state == 0) {
                                            $set('status', 'rejected');
                                        }

                                        // Update SaleOrderItem quantity
                                        $saleOrderItemId = $get('sale_order_item_id');
                                        if ($saleOrderItemId) {
                                            SaleOrderItem::where('id', $saleOrderItemId)->update(['quantity' => $state]);
                                        }
                                    }),

                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->searchable('name', 'kode')
                                    ->options(function () {
                                        return Warehouse::all()->mapWithKeys(function ($warehouse) {
                                            return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                        });
                                    })
                                    ->required(),

                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');

                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => "({$rak->code}) {$rak->name}"];
                                                });
                                        }

                                        return [];
                                    }),

                                Select::make('status')
                                    ->label('Item Status')
                                    ->options([
                                        'request' => 'Request',
                                        'confirmed' => 'Confirmed',
                                        'partial_confirmed' => 'Partial Confirmed',
                                        'rejected' => 'Rejected'
                                    ])
                                    ->default('request')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->visible(function ($get) {
                                return $get('confirmation_type') === 'sales_order';
                            }),

                        Textarea::make('note')
                            ->label('Notes')
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('saleOrder.so_number')
                    ->label('Sales Order')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('manufacturingOrder.mo_number')
                    ->label('Manufacturing Order')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(function ($state) {
                        return match (strtolower($state)) {
                            'confirmed' => 'success',
                            'partial_confirmed' => 'warning',
                            'rejected' => 'danger',
                            'request' => 'info',
                            default => 'gray'
                        };
                    }),

                TextColumn::make('user.name')
                    ->label('Confirmed By')
                    ->sortable(),

                TextColumn::make('confirmed_at')
                    ->label('Confirmed At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'request' => 'Request',
                        'confirmed' => 'Confirmed',
                        'partial_confirmed' => 'Partial Confirmed',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()->color('primary'),
                    EditAction::make()->color('success'),
                    Action::make('confirm')
                        ->label('Confirm Warehouse')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Warehouse')
                        ->modalDescription('This will confirm the warehouse confirmation and update the sales order status.')
                        ->action(function (WarehouseConfirmation $record) {
                            // Update warehouse confirmation status
                            $record->update([
                                'status' => 'confirmed',
                                'confirmed_by' => Auth::id(),
                                'confirmed_at' => now(),
                            ]);

                            // Update sales order status if needed
                            if ($record->saleOrder) {
                                $record->saleOrder->update([
                                    'status' => 'confirmed',
                                    'warehouse_confirmed_at' => now(),
                                ]);
                            }
                        })
                        ->visible(fn (WarehouseConfirmation $record): bool => strtolower($record->status) === 'request'),
                    DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        // Set confirmed_by and confirmed_at when status is being changed from request
        if (isset($data['status']) && $data['status'] !== 'request') {
            $data['confirmed_by'] = Auth::id();
            $data['confirmed_at'] = now();
        }

        return $data;
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
            'index' => Pages\ListWarehouseConfirmations::route('/'),
            'create' => Pages\CreateWarehouseConfirmation::route('/create'),
            'view' => Pages\ViewWarehouseConfirmation::route('/{record}'),
            'edit' => Pages\EditWarehouseConfirmation::route('/{record}/edit'),
        ];
    }
}
