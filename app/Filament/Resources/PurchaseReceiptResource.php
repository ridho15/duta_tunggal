<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptResource\Pages;
use App\Filament\Resources\PurchaseReceiptResource\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceiptResource\RelationManagers\PurchaseReceiptItemRelationManager;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Str;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationGroup = 'Pembelian';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Receipt')
                    ->schema([
                        TextInput::make('receipt_number')
                            ->label('Receipt Number')
                            ->string()
                            ->reactive()
                            ->suffixAction(Action::make('generateReceiptNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Receipt Number')
                                ->action(function ($set, $get, $state) {
                                    $purchaseReceipService = app(PurchaseReceiptService::class);
                                    $set('receipt_number', $purchaseReceipService->generateReceiptNumber());
                                }))
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $items = [];
                                $listPurchaseOrderItem = PurchaseOrderItem::where('purchase_order_id', $state)->get();
                                foreach ($listPurchaseOrderItem as $purchaseOrderItem) {
                                    array_push($items, [
                                        'product_id' => $purchaseOrderItem->product_id,
                                        'purchase_order_item_id' => $purchaseOrderItem->id,
                                        'qty_received' => $purchaseOrderItem->quantity,
                                        'qty_accepted' => 0,
                                        'qty_rejected' => 0,
                                        'reason_rejected' => null,
                                        'warehouse_id' => null,
                                        'rak_id' => null,
                                    ]);
                                }

                                $set('purchaseReceiptItem', $items);
                            })
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
                        Select::make('currency_id')
                            ->label('Currency')
                            ->preload()
                            ->searchable()
                            ->relationship('currency', 'name')
                            ->required(),
                        TextInput::make('other_cost')
                            ->label('Biaya Lainnya')
                            ->numeric()
                            ->prefix('Rp.')
                            ->default(0)
                            ->required(),
                        Textarea::make('notes')
                            ->nullable(),
                        Repeater::make('purchaseReceiptPhoto')
                            ->relationship()
                            ->defaultItems(0)
                            ->schema([
                                FileUpload::make('photo_url')
                                    ->image()
                                    ->maxSize(2048)
                                    ->required()
                            ]),
                        Repeater::make('purchaseReceiptItem')
                            ->relationship()
                            ->nullable()
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Purchase Receipt Item')
                            ->columns(3)
                            ->reactive()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('purchase_order_item_id')
                                    ->label('PO Item ID')
                                    ->readOnly()
                                    ->nullable(),
                                Select::make('product_id')
                                    ->preload()
                                    ->required()
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
                                    ->label('Gudang')
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->searchable()
                                    ->relationship('warehouse', 'name')
                                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                        return "({$warehouse->kode}) {$warehouse->name}";
                                    }),
                                Select::make('rak_id')
                                    ->label('Rak (Optional)')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->relationship('rak', 'name', function ($get, Builder $query) {
                                        $query->where('warehouse_id', $get('warehouse_id'));
                                    })
                                    ->nullable(),
                                TextInput::make('qty_received')
                                    ->label('Quantity Received')
                                    ->numeric()
                                    ->helperText("Quantity yang datang")
                                    ->required()
                                    ->default(0),
                                TextInput::make('qty_accepted')
                                    ->label('Quantity Accepted')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText("Quantity yang di ambil")
                                    ->required(),
                                TextInput::make('qty_rejected')
                                    ->label('Quantity Rejected')
                                    ->numeric()
                                    ->helperText("Quantity yang di tolak")
                                    ->default(0)
                                    ->required(),
                                Textarea::make('reason_rejected')
                                    ->label('Reason Rejected')
                                    ->string()
                                    ->nullable(),
                                Repeater::make('purchaseReceiptItemPhoto')
                                    ->relationship()
                                    ->addActionLabel('Tambah Photo')
                                    ->schema([
                                        FileUpload::make('photo_url')
                                            ->label('Photo')
                                            ->image()
                                            ->acceptedFileTypes(['image/*'])
                                            ->maxSize(1024)
                                            ->required(),
                                    ]),
                            ]),

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt Number')
                    ->searchable(),
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
                TextColumn::make('currency.name')
                    ->label('Currency'),
                TextColumn::make('other_cost')
                    ->money('idr')
                    ->sortable(),
                SelectColumn::make('status')
                    ->options(function () {
                        return [
                            'draft' => 'Draft',
                            'partial' => 'Partial',
                            'completed' => 'Completed'
                        ];
                    }),
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
            PurchaseReceiptItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReceipts::route('/'),
            'create' => Pages\CreatePurchaseReceipt::route('/create'),
            'view' => ViewPurchaseReceipt::route('/{record}'),
            'edit' => Pages\EditPurchaseReceipt::route('/{record}/edit'),
        ];
    }
}
