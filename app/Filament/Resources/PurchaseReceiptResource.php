<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptResource\Pages;
use App\Filament\Resources\PurchaseReceiptResource\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceiptResource\RelationManagers\PurchaseReceiptItemRelationManager;
use App\Models\Currency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Rak;
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
use Illuminate\Validation\Rule;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?int $navigationSort = 4;

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
                            ->validationMessages([
                                'required' => 'Receipt number tidak boleh kosong',
                                'unique' => 'Receipt number sudah digunakan'
                            ])
                            ->suffixAction(Action::make('generateReceiptNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Receipt Number')
                                ->action(function ($set, $get, $state) {
                                    $purchaseReceipService = app(PurchaseReceiptService::class);
                                    $set('receipt_number', $purchaseReceipService->generateReceiptNumber());
                                }))
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($record) {
                                return Rule::unique('purchase_receipts', 'receipt_number')
                                    ->whereNull('deleted_at')
                                    ->ignore($record?->id ?? null);
                            })
                            ->required(),
                        Select::make('purchase_order_id')
                            ->label('Kode Pembelian')
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
                            ->validationMessages([
                                'required' => 'Penerima belum dipilih',
                                'exists' => 'Penerima tidak tersedia'
                            ])
                            ->relationship('receivedBy', 'name')
                            ->required(),
                        Select::make('currency_id')
                            ->label('Mata Uang')
                            ->preload()
                            ->reactive()
                            ->validationMessages([
                                'exists' => "Mata uang tidak tersedia",
                                'required' => 'Mata uang belum dipilih'
                            ])
                            ->searchable(['name'])
                            ->relationship('currency', 'name')
                            ->getOptionLabelFromRecordUsing(function (Currency $currency) {
                                return "{$currency->name} ({$currency->symbol})";
                            })
                            ->required(),
                        TextInput::make('other_cost')
                            ->label('Biaya Lainnya')
                            ->numeric()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Biaya lain tidak boleh kosong. Minimal 0',
                                'numeric' => 'Biaya lain tidak valid !'
                            ])
                            ->prefix(function ($get) {
                                $currency = Currency::find($get('currency_id'));
                                if ($currency) {
                                    return $currency->symbol;
                                }

                                return null;
                            })
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
                                    ->validationMessages([
                                        'required' => 'Gudang belum dipilih',
                                        'exists' => 'Gudang tidak tersedia'
                                    ])
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
                                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                        return "({$rak->code}) {$rak->name}";
                                    })
                                    ->nullable(),
                                TextInput::make('qty_received')
                                    ->label('Quantity Received')
                                    ->numeric()
                                    ->helperText("Quantity yang datang")
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity diterima tidak boleh kosong.minimal 0',
                                        'numeric' => 'Quantity diterima tidak valid !'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $qtyReceived = (float) ($state ?? 0);
                                        $qtyAccepted = (float) ($get('qty_accepted') ?? 0);
                                        $qtyRejected = max(0, $qtyReceived - $qtyAccepted);
                                        $set('qty_rejected', $qtyRejected);
                                    })
                                    ->default(0),
                                TextInput::make('qty_accepted')
                                    ->label('Quantity Accepted')
                                    ->numeric()
                                    ->validationMessages([
                                        'required' => 'Quantity diambil tidak boleh kosong.minimal 0',
                                        'numeric' => 'Quantity diambil tidak valid !'
                                    ])
                                    ->default(0)
                                    ->helperText("Quantity yang di ambil")
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get, $component) {
                                        $qtyReceived = (float) ($get('qty_received') ?? 0);
                                        $qtyAccepted = (float) ($state ?? 0);
                                        
                                        // Validation: qty_accepted cannot exceed qty_received
                                        if ($qtyAccepted > $qtyReceived) {
                                            $component->state($qtyReceived);
                                            $qtyAccepted = $qtyReceived;
                                            
                                            \Filament\Notifications\Notification::make()
                                                ->title('Peringatan')
                                                ->body("Quantity Accepted tidak boleh melebihi Quantity Received ({$qtyReceived})")
                                                ->warning()
                                                ->send();
                                        }
                                        
                                        $qtyRejected = max(0, $qtyReceived - $qtyAccepted);
                                        $set('qty_rejected', $qtyRejected);
                                    })
                                    ->required(),
                                TextInput::make('qty_rejected')
                                    ->label('Quantity Rejected')
                                    ->numeric()
                                    ->helperText("Quantity yang ditolak (otomatis dihitung)")
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0),
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
