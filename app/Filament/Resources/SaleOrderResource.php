<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleOrderResource\Pages;
use App\Filament\Resources\SaleOrderResource\Pages\ViewSaleOrder;
use App\Filament\Resources\SaleOrderResource\RelationManagers\SaleOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Services\SalesOrderService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SaleOrderResource extends Resource
{
    protected static ?string $model = SaleOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Sales Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Sales')
                    ->schema([
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(function ($record) {
                                return $record ? Str::upper($record->status) : '-';
                            }),
                        Select::make('customer_id')
                            ->required()
                            ->label('Customer')
                            ->preload()
                            ->searchable()
                            ->relationship('customer', 'name'),
                        TextInput::make('so_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        DateTimePicker::make('order_date')
                            ->required(),
                        DateTimePicker::make('delivery_date'),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('Rp.')
                            ->required()
                            ->disabled()
                            ->default(0)
                            ->numeric(),
                        Repeater::make('saleOrderItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->addActionLabel("Add Items")
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::find($state);
                                        $set('unit_price', $product->sell_price);
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->required()
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $state, $get('tax')));
                                    })
                                    ->required()
                                    ->default(0),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $state));
                                    })
                                    ->prefix('Rp.'),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->prefix('Rp.'),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->default(0)
                                    ->prefix('Rp.'),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->readOnly()
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $component->state(HelperController::hitungSubtotal($record->quantity, $record->unit_price, $record->discount, $record->tax));
                                        }
                                    })
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $quantity = $livewire->data['quantity'] ?? 0;
                                        $unit_price = $livewire->data['unit_price'] ?? 0;
                                        $discount = $livewire->data['discount'] ?? 0;
                                        $tax = $livewire->data['tax'] ?? 0;
                                        $component->state(HelperController::hitungSubtotal($$quantity, $unit_price, $discount, $tax));
                                    })
                                    ->prefix('Rp.')
                            ])
                    ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('so_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'process' => 'warning',
                            'completed' => 'success',
                            'canceled' => 'danger'
                        };
                    })
                    ->badge(),
                TextColumn::make('delivery_date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->money('idr')
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
                        ->color('primary'),
                    DeleteAction::make(),
                    Action::make('sync_total_amount')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->updateTotalAmount($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
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
            SaleOrderItemRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleOrders::route('/'),
            'create' => Pages\CreateSaleOrder::route('/create'),
            'view' => ViewSaleOrder::route('/{record}'),
            'edit' => Pages\EditSaleOrder::route('/{record}/edit'),
        ];
    }
}
