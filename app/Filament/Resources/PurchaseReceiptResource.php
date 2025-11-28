<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptResource\Pages;
use App\Filament\Resources\PurchaseReceiptResource\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceiptResource\RelationManagers\PurchaseReceiptItemRelationManager;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
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

    // Group label updated to indicate Purchase Order group
    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?int $navigationSort = 2;

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
                            ->options(function () {
                                return \App\Models\PurchaseOrder::where('status', 'approved')
                                    ->get()
                                    ->map(function ($po) {
                                        return [
                                            'id' => $po->id,
                                            'label' => $po->po_number
                                        ];
                                    })
                                    ->pluck('label', 'id');
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $items = [];
                                $purchaseOrder = \App\Models\PurchaseOrder::with('purchaseOrderCurrency')->find($state);
                                $warehouse_id = $purchaseOrder->warehouse_id ?? null;
                                $rak_id = $purchaseOrder->rak_id ?? null;
                                $currency_id = $purchaseOrder->purchaseOrderCurrency->first()->currency_id ?? 7; // Default to IDR if none
                                $set('currency_id', $currency_id);
                                $listPurchaseOrderItem = \App\Models\PurchaseOrderItem::where('purchase_order_id', $state)->get();
                                foreach ($listPurchaseOrderItem as $purchaseOrderItem) {
                                    $items[] = [
                                        'product_id' => $purchaseOrderItem->product_id,
                                        'purchase_order_item_id' => $purchaseOrderItem->id,
                                        'qty_received' => $purchaseOrderItem->quantity,
                                        'qty_accepted' => 0,
                                        'qty_rejected' => 0,
                                        'reason_rejected' => null,
                                        'warehouse_id' => $warehouse_id,
                                        'rak_id' => $rak_id,
                                    ];
                                }
                                $set('purchaseReceiptItem', $items);

                                // Auto-copy biaya dari PO ke PR
                                $biayas = [];
                                $listPurchaseOrderBiaya = \App\Models\PurchaseOrderBiaya::where('purchase_order_id', $state)->get();
                                foreach ($listPurchaseOrderBiaya as $poBiaya) {
                                    $biayas[] = [
                                        'nama_biaya' => $poBiaya->nama_biaya,
                                        'currency_id' => $poBiaya->currency_id,
                                        'coa_id' => $poBiaya->coa_id,
                                        'total' => $poBiaya->total,
                                        'untuk_pembelian' => $poBiaya->untuk_pembelian,
                                        'masuk_invoice' => $poBiaya->masuk_invoice == 1 ? true : false,
                                        'purchase_order_biaya_id' => $poBiaya->id,
                                    ];
                                }
                                $set('purchaseReceiptBiaya', $biayas);
                            })
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
                        Repeater::make('purchaseReceiptBiaya')
                            ->columnSpanFull()
                            ->relationship()
                            ->addActionAlignment(Alignment::Right)
                            ->addAction(function (Action $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Biaya');
                            })
                            ->label('Biaya Lain')
                            ->columns(3)
                            ->schema([
                                TextInput::make('nama_biaya')
                                    ->label('Nama Biaya')
                                    ->string()
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => 'Nama biaya belum diisi',
                                        'string' => 'Nama biaya tidak valid !',
                                        'max' => 'Nama biaya terlalu panjang'
                                    ]),
                                Select::make('currency_id')
                                    ->label('Mata Uang')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('currency', 'name')
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (Currency $currency) {
                                        return "{$currency->name} ({$currency->symbol})";
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                Select::make('coa_id')
                                    ->label('COA Biaya')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('coa', 'name')
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (ChartOfAccount $coa) {
                                        return "({$coa->code}) {$coa->name}";
                                    })
                                    ->options(function () {
                                        return ChartOfAccount::where('type', 'Expense')->orderBy('code')->get()->mapWithKeys(function ($coa) {
                                            return [$coa->id => "({$coa->code}) {$coa->name}"];
                                        });
                                    })
                                    ->validationMessages([
                                        'required' => 'COA biaya belum dipilih',
                                        'exists' => 'COA biaya tidak tersedia'
                                    ]),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->reactive()
                                    ->indonesianMoney()
                                    ->prefix(function ($get) {
                                        $currency = Currency::find($get('currency_id'));
                                        if ($currency) {
                                            return $currency->symbol;
                                        }
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Total tidak boleh kosong',
                                        'numeric' => 'Total biaya tidak valid !',
                                    ])
                                    ->default(0),
                                Radio::make('untuk_pembelian')
                                    ->label('Untuk Pembelian')
                                    ->options([
                                        0 => 'Non Pajak',
                                        1 => 'Pajak'
                                    ])
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tipe Pajak belum dipilih'
                                    ]),
                                Checkbox::make('masuk_invoice')
                                    ->label('Masuk Invoice')
                                    ->default(false),
                            ]),
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
                TextColumn::make('total_biaya')
                    ->label('Total Biaya Lain')
                    ->money('IDR')
                    ->getStateUsing(function ($record) {
                        return $record->purchaseReceiptBiaya->sum('total');
                    })
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
