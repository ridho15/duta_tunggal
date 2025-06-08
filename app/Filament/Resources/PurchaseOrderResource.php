<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\PurchaseOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Order')
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->required(),
                        TextInput::make('po_number')
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('order_date')
                            ->required(),
                        DateTimePicker::make('expected_date'),
                        TextInput::make('total_amount')
                            ->required()
                            ->prefix('Rp.')
                            ->numeric()
                            ->disabled()
                            ->default(0),
                        Textarea::make('note')
                            ->label('Notes'),
                        Toggle::make('is_asset')
                            ->label('Asset ?')
                            ->required(),
                        Repeater::make('purchaseOrderItem')
                            ->label('Order Item')
                            ->columnSpanFull()
                            ->relationship()
                            ->columns(3)
                            ->afterStateUpdated(function (Get $get, $state, $livewire) {
                                $purchaseOrder = $livewire->getRecord();
                                $total_amount = 0;
                                foreach ($state as $item) {
                                    $total_amount += ($item['quantity'] * $item['unit_price']) - $item['discount'] + $item['tax'];
                                }

                                if ($purchaseOrder) {
                                    $purchaseOrder->update([
                                        'total_amount' => $total_amount
                                    ]);
                                }
                            })
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "{$record->sku} - {$record->name}";
                                    })
                                    ->relationship('product', 'name')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $product = Product::find($state);
                                        $set('unit_price', $product->cost_price);

                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->numeric(),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->prefix('Rp.')
                                    ->default(0),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->prefix('Rp.')
                                    ->default(0),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->prefix('Rp.')
                                    ->default(0),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->prefix('Rp.')
                                    ->readOnly()
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('po_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status PO')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        switch ($state) {
                            case 'draft':
                                return 'gray';
                                break;
                            case 'draft':
                                return 'gray';
                                break;
                            case 'partially_received':
                                return 'warning';
                                break;
                            case 'request_close':
                                return 'warning';
                                break;
                            case 'request_approval':
                                return 'info';
                                break;
                            case 'closed':
                                return 'danger';
                                break;
                            case 'completed':
                                return 'success';
                                break;
                        }
                    })
                    ->badge(),
                TextColumn::make('expected_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
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
                IconColumn::make('is_asset')
                    ->label('Asset?')
                    ->boolean(),
                TextColumn::make('date_approved')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('close_requested_by')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('close_requested_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_by')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_by')
                    ->numeric()
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
                        ->hidden(function () {
                            return Auth::user()->hasRole('Owner');
                        })
                        ->color('success'),
                    DeleteAction::make()
                        ->hidden(function () {
                            return Auth::user()->hasRole('Owner');
                        }),
                    Action::make('konfirmasi')
                        ->label('Konfirmasi')
                        ->hidden(function ($record) {
                            return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'approved',
                                'date_approved' => Carbon::now(),
                                'approved_by' => Auth::user()->id,
                            ]);
                        }),
                    Action::make('tolak')
                        ->label('Tolak')
                        ->hidden(function ($record) {
                            return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'draft'
                            ]);
                        }),
                    Action::make('request_approval')
                        ->label('Request Approval')
                        ->hidden(function ($record) {
                            return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_approval', 'closed', 'completed', 'approved']);
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'request_approval'
                            ]);
                        }),
                    Action::make('request_close')
                        ->label('Request Close')
                        ->hidden(function ($record) {
                            return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_close', 'closed', 'completed', 'approved']);
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'request_close'
                            ]);
                        }),
                    Action::make('cetak_pdf')
                        ->label('Cetak PDF')
                        ->icon('heroicon-o-document-check')
                        ->color('danger')
                        ->hidden(function ($record) {
                            return in_array($record->status, ['draft', 'closed', 'request_close', 'request_approval']);
                        })
                        ->openUrlInNewTab()
                        ->url(function ($record) {
                            return route('purchase-order.cetak', ['id' => $record->id]);
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
            PurchaseOrderItemRelationManager::class
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(Auth::user()->hasRole([
            'Owner'
        ]), function (Builder $query) {
            return $query->whereIn('status', ['request_close', 'request_approval', 'approved', 'completed']);
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
