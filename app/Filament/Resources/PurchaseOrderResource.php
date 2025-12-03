<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\PurchaseOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\AssetService;
use App\Services\InvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\QualityControlService;
use App\Services\PurchaseReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\DB;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    // Group label updated to include English hint per request
    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?string $navigationLabel = 'Pembelian';

    protected static ?string $pluralModelLabel = 'Pembelian';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Pembelian')
                    ->schema([
                        Section::make('Reference')
                            ->description("Referensi untuk membuat PO, boleh di abaikan")
                            ->columns(2)
                            ->schema([
                                Radio::make('refer_model_type')
                                    ->label('Refer From')
                                    ->reactive()
                                    ->inlineLabel()
                                    ->options([
                                        'App\Models\SaleOrder' => 'Sales Order',
                                        'App\Models\OrderRequest' => 'Order Request'
                                    ])
                                    ->nullable(),
                                Select::make('refer_model_id')
                                    ->label(function ($get) {
                                        if ($get('refer_model_type') == 'App\Models\SaleOrder') {
                                            return 'Refer From Sales Order';
                                        } elseif ($get('refer_model_type') == 'App\Models\OrderRequest') {
                                            return "Refer From Order Request";
                                        }

                                        return "Refer From";
                                    })
                                    ->reactive()
                                    ->preload()
                                    ->searchable()
                                    ->options(function ($set, $get, $state) {
                                        if ($get('refer_model_type') == 'App\Models\SaleOrder') {
                                            return SaleOrder::select(['id', 'so_number'])->get()->pluck('so_number', 'id');
                                        } elseif ($get('refer_model_type') == 'App\Models\OrderRequest') {
                                            return OrderRequest::where('status', 'approved')->select(['id', 'request_number'])->get()->pluck('request_number', 'id');
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $items = [];
                                        if ($get('refer_model_type') == 'App\Models\SaleOrder') {
                                            $saleOrder = SaleOrder::find($state);
                                            foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                                $subtotal = ((int)$saleOrderItem->quantity * (int) $saleOrderItem->unit_price) - (int) $saleOrderItem->discount + (int) $saleOrderItem->tax;
                                                array_push($items, [
                                                    'product_id' => $saleOrderItem->product_id,
                                                    'quantity' => $saleOrderItem->quantity,
                                                    'unit_price' => $saleOrderItem->product->cost_price,
                                                    'discount' => 0,
                                                    'tax' => 0,
                                                    'subtotal' => $subtotal
                                                ]);
                                            }
                                        } elseif ($get('refer_model_type') == 'App\Models\OrderRequest') {
                                            $orderRequest = OrderRequest::find($state);
                                            if ($orderRequest) {
                                                $set('supplier_id', $orderRequest->supplier_id);
                                                $set('warehouse_id', $orderRequest->warehouse_id);
                                                if($orderRequest->supplier) {
                                                    $set('tempo_hutang', $orderRequest->supplier->tempo_hutang);
                                                }
                                                foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
                                                    $subtotal = ((int)$orderRequestItem->quantity * (int) $orderRequestItem->unit_price) - (int) $orderRequestItem->discount + (int) $orderRequestItem->tax;
                                                    array_push($items, [
                                                        'product_id' => $orderRequestItem->product_id,
                                                        'quantity' => $orderRequestItem->quantity,
                                                        'unit_price' => $orderRequestItem->product->cost_price,
                                                        'discount' => 0,
                                                        'tax' => 0,
                                                        'subtotal' => $subtotal,
                                                        'currency_id' => 7, // Default to IDR (Indonesian Rupiah)
                                                    ]);
                                                }
                                            }
                                        }

                                        $set('purchaseOrderItem', $items);
                                    })
                                    ->nullable(),
                            ]),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->reactive()
                            ->relationship('supplier', 'name')
                            ->validationMessages([
                                'required' => 'Supplier belum dipilih',
                            ])
                            ->searchable(['code', 'name'])
                            ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                                return "({$supplier->code}) {$supplier->name}";
                            })
                            ->afterStateUpdated(function ($state, $set) {
                                $supplier = Supplier::find($state);
                                if ($supplier) {
                                    $set('tempo_hutang', $supplier->tempo_hutang);
                                }
                            })
                            ->required(),
                        TextInput::make('po_number')
                            ->required()
                            ->reactive()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'PO Number tidak boleh kosong',
                                'unique' => 'PO Number sudah digunakan'
                            ])
                            ->suffixAction(
                                ActionsAction::make('generatePoNumber')
                                    ->icon('heroicon-m-arrow-path') // ikon reload
                                    ->tooltip('Generate PO Number')
                                    ->action(function ($set, $get, $state) {
                                        $purchaseOrderService = app(PurchaseOrderService::class);
                                        $set('po_number', $purchaseOrderService->generatePoNumber());
                                    })
                            )
                            ->maxLength(255),
                        DatePicker::make('order_date')
                            ->label('Tanggal Pembelian')
                            ->validationMessages([
                                'required' => 'Tanggal Pembelian tidak boleh kosong'
                            ])
                            ->required(),
                        DatePicker::make('expected_date')
                            ->label('Tanggal Diharapkan'),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->preload()
                            ->searchable(['name', 'kode'])
                            ->required()
                            ->validationMessages([
                                'required' => 'Gudang belum dipilih',
                            ])
                            ->relationship('warehouse', 'nama', function(Builder $query) {
                                return $query->orderBy('name')->where('status', 1);
                            })
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode}) {$warehouse->name}";
                            }),
                        Toggle::make('is_import')
                            ->label('Pembelian Import?')
                            ->helperText('Aktifkan untuk menandai pembelian impor sehingga pajak impor dicatat saat pembayaran')
                            ->reactive(),
                        Radio::make('ppn_option')
                            ->label('Opsi PPN')
                            ->inline()
                            ->reactive()
                            ->default('standard')
                            ->options([
                                'standard' => 'Pakai PPN (Default)',
                                'non_ppn' => 'Non PPN',
                            ])
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if (!in_array($state, ['standard', 'non_ppn'])) {
                                    return;
                                }

                                if ($state === 'non_ppn') {
                                    $items = collect($get('purchaseOrderItem') ?? [])->map(function ($item) {
                                        $item['tax'] = 0;
                                        $item['tipe_pajak'] = 'Non Pajak';
                                        return $item;
                                    })->all();
                                    $set('purchaseOrderItem', $items);
                                }
                            })
                            ->helperText('Pilih Non PPN bila pemasok tidak mengenakan PPN untuk pesanan ini'),
                        TextInput::make('tempo_hutang')
                            ->label('Tempo Hutang (Hari)')
                            ->numeric()
                            ->reactive()
                            ->default(0)
                            ->required()
                            ->suffix('Hari'),
                        Textarea::make('note')
                            ->label('Keterangan')
                            ->string(),
                        Toggle::make('is_asset')
                            ->label('Asset ?')
                            ->required(),
                        Repeater::make('purchaseOrderItem')
                            ->label('Order Items')
                            ->columnSpanFull()
                            ->relationship()
                            ->addActionAlignment(Alignment::Right)
                            ->columns(3)
                            ->reactive()
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, $get) {
                                $data['subtotal'] = HelperController::hitungSubtotal($data['quantity'], HelperController::parseIndonesianMoney($data['unit_price']), $data['discount'], $data['tax'], $data['tipe_pajak'] ?? null);
                                return $data;
                            })
                            ->addAction(function (ActionsAction $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Order Items');
                            })
                            ->defaultItems(0)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(function (Get $get) {
                                        $supplierId = $get('../../supplier_id');
                                        if ($supplierId) {
                                            return Product::where('supplier_id', $supplierId)->get()->mapWithKeys(function ($product) {
                                                return [$product->id => "{$product->sku} - {$product->name}"];
                                            });
                                        }
                                        return [];
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit_price', $product->cost_price);
                                            $set('discount', 0); // Default discount 0, bisa disesuaikan
                                            $set('tax', $product->pajak ?? 0);
                                            $set('tipe_pajak', $product->tipe_pajak ?? 'Inklusif');
                                        }
                                        $set('subtotal', HelperController::hitungSubtotal((int)$get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), (int)$get('discount'), (int)$get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->required(),
                                Select::make('currency_id')
                                    ->label('Mata Uang')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->required()
                                    ->options(function () {
                                        return Currency::orderBy('name')->get()->mapWithKeys(function (Currency $c) {
                                            return [$c->id => "{$c->name} ({$c->symbol})"];
                                        });
                                    })
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Ensure this currency is added to purchaseOrderCurrency if not already present
                                        // log selection to help debug validation issue
                                        \Illuminate\Support\Facades\Log::debug('POItem.currency afterStateUpdated START', [
                                            'state' => $state,
                                            'state_type' => gettype($state),
                                            'is_null' => is_null($state),
                                            'is_empty' => empty($state),
                                            'purchaseOrderItem' => $get('../..purchaseOrderItem') ?? null,
                                        ]);

                                        $currencies = $get('../../purchaseOrderCurrency') ?? [];
                                        \Illuminate\Support\Facades\Log::debug('POItem.currency current currencies', [
                                            'currencies' => $currencies,
                                            'currencies_count' => count($currencies),
                                        ]);

                                        $currencyExists = false;
                                        
                                        foreach ($currencies as $index => $currency) {
                                            \Illuminate\Support\Facades\Log::debug('POItem.currency checking currency', [
                                                'index' => $index,
                                                'currency' => $currency,
                                                'currency_id' => $currency['currency_id'] ?? null,
                                                'state' => $state,
                                                'matches' => (($currency['currency_id'] ?? null) == $state)
                                            ]);
                                            if (($currency['currency_id'] ?? null) == $state) {
                                                $currencyExists = true;
                                                break;
                                            }
                                        }
                                        
                                        \Illuminate\Support\Facades\Log::debug('POItem.currency exists check result', [
                                            'currencyExists' => $currencyExists,
                                            'will_add' => (!$currencyExists && $state)
                                        ]);
                                        
                                        if (!$currencyExists && $state) {
                                            $currencies[] = [
                                                'currency_id' => $state,
                                                'nominal' => 0
                                            ];
                                            \Illuminate\Support\Facades\Log::debug('POItem.currency adding new currency', [
                                                'new_currencies' => $currencies
                                            ]);
                                            $set('../../purchaseOrderCurrency', $currencies);
                                            
                                            // Verify the set worked
                                            $updatedCurrencies = $get('../../purchaseOrderCurrency') ?? [];
                                            \Illuminate\Support\Facades\Log::debug('POItem.currency after set verification', [
                                                'updated_currencies' => $updatedCurrencies,
                                                'updated_count' => count($updatedCurrencies)
                                            ]);
                                        }
                                        
                                        \Illuminate\Support\Facades\Log::debug('POItem.currency afterStateUpdated END');
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $set('subtotal', HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->numeric(),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->reactive()
                                    ->required()
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'required' => 'Unit price tidak boleh kosong',
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $set('subtotal', HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->prefix(function ($get) {
                                        $currency = Currency::find($get('currency_id'));
                                        if ($currency) {
                                            return $currency->symbol;
                                        }

                                        return null;
                                    })
                                    ->default(0),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->reactive()
                                    ->numeric()
                                    ->required()
                                    ->maxValue(100)
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'required' => 'Discount tidak boleh kosong. Minimal 0'
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $set('subtotal', HelperController::hitungSubtotal((int)$get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), (int)$get('discount'), (int)$get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->suffix('%')
                                    ->default(0),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->reactive()
                                    ->numeric()
                                    ->maxValue(100)
                                    ->required()
                                    ->disabled(fn (Get $get) => ($get('../../ppn_option') ?? 'standard') === 'non_ppn')
                                    ->validationMessages([
                                        'required' => 'Tax tidak boleh kosong, Minimal 0'
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $set('subtotal', HelperController::hitungSubtotal((int)$get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), (int)$get('discount'), (int)$get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->suffix('%')
                                    ->default(0),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->prefix(function ($get) {
                                        $currency = Currency::find($get('currency_id'));
                                        if ($currency) {
                                            return $currency->symbol;
                                        }

                                        return null;
                                    })
                                    ->default(0)
                                    ->readOnly()
                                    ->indonesianMoney()
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $items = $livewire->data['purchaseOrderItem'] ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += \App\Http\Controllers\HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                        }

                                        // Add biaya amounts converted using purchaseOrderCurrency.nominal if available
                                        $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
                                        $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
                                        foreach ($biayas as $biaya) {
                                            $nominal = 1;
                                            if (isset($biaya['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                                                        $nominal = $c['nominal'] ?? $nominal;
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += ($biaya['total'] ?? 0) * $nominal;
                                        }

                                        $livewire->data['total_amount'] = $total;
                                    }),
                                Radio::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->inline()
                                    ->reactive()
                                    ->required()
                                    ->disabled(fn (Get $get) => ($get('../../ppn_option') ?? 'standard') === 'non_ppn')
                                    ->options([
                                        'Non Pajak' => 'Non Pajak',
                                        'Inklusif' => 'Inklusif',
                                        'Eklusif' => 'Eklusif'
                                    ])
                                    ->validationMessages([
                                        'required' => 'Tipe Pajak belum dipilih'
                                    ]),
                            ]),
                        Repeater::make('purchaseOrderBiaya')
                            ->columnSpanFull()
                            ->relationship()
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, $get) {
                                // Ensure 'total' is provided as a numeric value so
                                // the ->indonesianMoney() formatter can render it.
                                if (isset($data['total']) && is_numeric($data['total'])) {
                                    // cast to int if it has no cents, otherwise float
                                    $data['total'] = (int) $data['total'] == $data['total'] ? (int) $data['total'] : (float) $data['total'];
                                }
                                return $data;
                            })
                            ->addActionAlignment(Alignment::Right)
                            ->addAction(function (ActionsAction $action) {
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
                                    ->label('Mata uang')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->required()
                                    ->options(function () {
                                        return Currency::orderBy('name')->get()->mapWithKeys(function (Currency $c) {
                                            return [$c->id => "{$c->name} ({$c->symbol})"];
                                        });
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
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $items = $livewire->data['purchaseOrderItem'] ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += \App\Http\Controllers\HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                        }

                                        $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
                                        $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
                                        foreach ($biayas as $biaya) {
                                            $nominal = 1;
                                            if (isset($biaya['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                                                        $nominal = $c['nominal'] ?? $nominal;
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += ((float)$biaya['total'] ?? 0) * $nominal;
                                        }

                                        $livewire->data['total_amount'] = $total;
                                    }),
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
                        Repeater::make('purchaseOrderCurrency')
                            ->label("Mata Uang")
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, $get) {
                                // Ensure 'nominal' is numeric so ->indonesianMoney() will render it
                                if (isset($data['nominal']) && is_numeric($data['nominal'])) {
                                    $data['nominal'] = (int) $data['nominal'] == $data['nominal'] ? (int) $data['nominal'] : (float) $data['nominal'];
                                }
                                return $data;
                            })
                            ->addActionAlignment(Alignment::Right)
                            ->relationship()
                            ->addAction(function (ActionsAction $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Mata Uang');
                            })
                            ->columnSpanFull()
                            ->columns(2)
                            ->defaultItems(1)
                            ->default([
                                [
                                    'currency_id' => 7, // Default to IDR
                                    'nominal' => 0
                                ]
                            ])
                            ->required()
                            ->validationMessages([
                                'required' => 'Minimal satu mata uang harus dipilih'
                            ])
                            ->schema([
                                Select::make('currency_id')
                                    ->label('Mata uang')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->required()
                                    ->options(function () {
                                        return Currency::orderBy('name')->get()->mapWithKeys(function (Currency $c) {
                                            return [$c->id => "{$c->name} ({$c->symbol})"];
                                        });
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                TextInput::make('nominal')
                                    ->label('Nominal')
                                    ->reactive()
                                    ->indonesianMoney()
                                    ->prefix(function ($get) {
                                        $currency = Currency::find($get('currency_id'));
                                        if ($currency) {
                                            return $currency->symbol;
                                        }

                                        return null;
                                    })
                                    ->numeric()
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $items = $livewire->data['purchaseOrderItem'] ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += \App\Http\Controllers\HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                        }

                                        $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
                                        $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
                                        foreach ($biayas as $biaya) {
                                            $nominal = 1.0;
                                            if (isset($biaya['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                                                        $nominal = (float)($c['nominal'] ?? $nominal);
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += ((float)$biaya['total'] ?? 0) * $nominal;
                                        }

                                        $livewire->data['total_amount'] = $total;
                                    }),
                            ]),
                        TextInput::make('total_amount')
                        ->label("Total Amount")
                            ->required()
                            ->reactive()
                            ->readOnly()
                            ->indonesianMoney()
                            ->helperText('Total dihitung dari item dan biaya; tampil untuk referensi saja')
                            ->afterStateHydrated(function ($component, $record) {
                                if (! $record) {
                                    return;
                                }

                                $total = 0;
                                foreach ($record->purchaseOrderItem as $item) {
                                    $total += \App\Http\Controllers\HelperController::hitungSubtotal((int)$item->quantity, (int)$item->unit_price, (int)$item->discount, (int)$item->tax, $item->tipe_pajak);
                                }

                                foreach ($record->purchaseOrderBiaya as $biaya) {
                                    $biayaAmount = $biaya->total * ($biaya->currency->to_rupiah ?? 1);
                                    $total += $biayaAmount;
                                }
                                $component->state($total);
                            }),

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->orderByDesc('order_date');
            })
            ->columns([
                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('supplier', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    }),
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable(),
                IconColumn::make('is_import')
                    ->label('Import?')
                    ->boolean()
                    ->tooltip(fn ($state) => $state ? 'Pembelian import (pajak dicatat saat pembayaran)' : 'Pembelian lokal'),
                TextColumn::make('ppn_option')
                    ->label('Opsi PPN')
                    ->formatStateUsing(function ($state) {
                        return $state === 'non_ppn' ? 'Non PPN' : 'PPN';
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'non_ppn' ? 'warning' : 'success')
                    ->toggleable(),
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function (Builder $query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    }),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('tempo_hutang')
                    ->label('Tempo Hutang')
                    ->sortable()
                    ->suffix(' Hari'),
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
                TextColumn::make('qc_status')
                    ->label('QC Status')
                    ->formatStateUsing(function ($record) {
                        $totalItems = $record->purchaseOrderItem->count();
                        $qcItems = $record->purchaseOrderItem->filter(function ($item) {
                            return $item->qualityControl !== null;
                        })->count();
                        
                        if ($totalItems === 0) return 'No Items';
                        if ($qcItems === 0) return 'Not Started';
                        if ($qcItems === $totalItems) return 'Completed';
                        return 'Partial (' . $qcItems . '/' . $totalItems . ')';
                    })
                    ->color(function ($record) {
                        $totalItems = $record->purchaseOrderItem->count();
                        $qcItems = $record->purchaseOrderItem->filter(function ($item) {
                            return $item->qualityControl !== null;
                        })->count();
                        
                        if ($totalItems === 0) return 'gray';
                        if ($qcItems === 0) return 'warning';
                        if ($qcItems === $totalItems) return 'success';
                        return 'info';
                    })
                    ->badge()
                    ->toggleable(),
                TextColumn::make('expected_date')
                    ->label('Tanggal Diharapkan')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('remaining_qty_status')
                    ->label('Status Penerimaan')
                    ->formatStateUsing(function ($record) {
                        $totalItems = $record->purchaseOrderItem->count();
                        $completedItems = $record->purchaseOrderItem->filter(function ($item) {
                            return $item->remaining_quantity <= 0;
                        })->count();

                        $itemsWithReceipts = $record->purchaseOrderItem->filter(function ($item) {
                            return $item->purchaseReceiptItem()->sum('qty_accepted') > 0;
                        })->count();

                        if ($totalItems === 0) return 'No Items';
                        if ($completedItems === $totalItems) return 'Semua Diterima';
                        if ($completedItems > 0) return 'Sebagian (' . $completedItems . '/' . $totalItems . ')';
                        if ($itemsWithReceipts > 0) return 'Sebagian Diterima';
                        return 'Belum Diterima';
                    })
                    ->color(function ($record) {
                        $totalItems = $record->purchaseOrderItem->count();
                        $completedItems = $record->purchaseOrderItem->filter(function ($item) {
                            return $item->remaining_quantity <= 0;
                        })->count();

                        $itemsWithReceipts = $record->purchaseOrderItem->filter(function ($item) {
                            return $item->purchaseReceiptItem()->sum('qty_accepted') > 0;
                        })->count();

                        if ($totalItems === 0) return 'gray';
                        if ($completedItems === $totalItems) return 'success';
                        if ($completedItems > 0) return 'info';
                        if ($itemsWithReceipts > 0) return 'warning';
                        return 'danger';
                    })
                    ->badge()
                    ->tooltip(function ($record) {
                        $details = $record->purchaseOrderItem->map(function ($item) {
                            $remaining = $item->remaining_quantity;
                            $total = $item->quantity;
                            $received = $item->total_received;
                            return "{$item->product->name}: {$received}/{$total} (sisa: {$remaining})";
                        })->join("\n");

                        return "Detail Penerimaan:\n" . $details;
                    }),
                TextColumn::make('purchaseOrderItem.product.name')
                    ->label('Product')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                IconColumn::make('is_asset')
                    ->label('Asset?')
                    ->boolean(),
                TextColumn::make('date_approved')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
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
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Purchase Order</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Purchase Order (PO) adalah instruksi pembelian resmi ke supplier.</li>' .
                            '<li><strong>Membuat PO:</strong> PO dapat dibuat dari Order Request atau Sales Order, atau dibuat manual lewat tombol Create PO.</li>' .
                            '<li><strong>QC & Penerimaan:</strong> Setelah barang diterima, buat Purchase Receipt dan kirim item ke Quality Control jika diperlukan. Stok hanya bertambah setelah QC disetujui atau receipt selesai sesuai alur.</li>' .
                            '<li><strong>Dampak Status Completed:</strong> PO berstatus <em>completed</em> menandakan semua barang telah diterima; selanjutnya proses invoice dan pembayaran dapat dilanjutkan.</li>' .
                            '<li><strong>Catatan:</strong> Beberapa tindakan (approve, close) memerlukan hak akses tertentu.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status PO')
                    ->options([
                        'draft' => 'Draft',
                        'request_approval' => 'Request Approval',
                        'approved' => 'Approved',
                        'partially_received' => 'Partially Received',
                        'completed' => 'Completed',
                        'request_close' => 'Request Close',
                        'closed' => 'Closed',
                    ])
                    ->placeholder('Pilih Status'),
                SelectFilter::make('is_import')
                    ->label('Pembelian Import')
                    ->options([
                        1 => 'Import',
                        0 => 'Non Import',
                    ])
                    ->placeholder('Semua PO'),
                SelectFilter::make('ppn_option')
                    ->label('Opsi PPN')
                    ->options([
                        'standard' => 'PPN',
                        'non_ppn' => 'Non PPN',
                    ])
                    ->placeholder('Semua Opsi PPN'),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                        return "({$supplier->code}) {$supplier->name}";
                    }),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    }),
                Filter::make('order_date')
                    ->form([
                        DatePicker::make('order_date_from')
                            ->label('Tanggal Order Dari'),
                        DatePicker::make('order_date_until')
                            ->label('Tanggal Order Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['order_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '>=', $date),
                            )
                            ->when(
                                $data['order_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['order_date_from'] ?? null) {
                            $indicators['order_date_from'] = 'Order dari ' . Carbon::parse($data['order_date_from'])->toFormattedDateString();
                        }

                        if ($data['order_date_until'] ?? null) {
                            $indicators['order_date_until'] = 'Order sampai ' . Carbon::parse($data['order_date_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('is_asset')
                    ->label('Tipe PO')
                    ->options([
                        1 => 'Asset',
                        0 => 'Non Asset',
                    ])
                    ->placeholder('Pilih Tipe'),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->color('success'),
                    DeleteAction::make()
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        }),
                    Action::make('konfirmasi')
                        ->label('Konfirmasi')
                        ->visible(function ($record) {
                            return Gate::allows('response purchase order')
                                && ($record->status == 'request_approval' || $record->status == 'request_close');
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->form(function ($record) {
                            if ($record->status == 'request_close') {
                                return [
                                    Textarea::make('close_reason')
                                        ->label('Close Reason')
                                        ->required()
                                        ->string()
                                ];
                            }
                            // request_approval case
                            if ($record->status == 'request_approval' && $record->is_asset) {
                                return [
                                    Fieldset::make('Asset Parameters')->schema([
                                        DatePicker::make('usage_date')->label('Tanggal Pakai')->required()->default($record->order_date),
                                        TextInput::make('useful_life_years')->label('Umur Manfaat (Tahun)')->numeric()->required()->default(5),
                                        TextInput::make('salvage_value')->label('Nilai Sisa')->numeric()->default(0),
                                        Select::make('asset_coa_id')->label('COA Aset')->options(function () {
                                            return ChartOfAccount::where('type', 'Asset')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => "({$coa->code}) {$coa->name}"]);
                                        })->searchable()->required(),
                                        Select::make('accumulated_depreciation_coa_id')->label('COA Akumulasi Penyusutan')->options(function () {
                                            return ChartOfAccount::where('type', 'Contra Asset')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => "({$coa->code}) {$coa->name}"]);
                                        })->searchable()->required(),
                                        Select::make('depreciation_expense_coa_id')->label('COA Beban Penyusutan')->options(function () {
                                            return ChartOfAccount::where('type', 'Expense')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => "({$coa->code}) {$coa->name}"]);
                                        })->searchable()->required(),
                                    ])->columns(1),
                                ];
                            }

                            return null;
                        })
                        ->action(function (array $data, $record) {
                            if ($record->status == 'request_approval') {
                                DB::transaction(function () use ($record, $data) {
                                    $status = $record->is_asset ? 'completed' : 'approved';
                                    $record->update([
                                        'status' => $status,
                                        'date_approved' => Carbon::now(),
                                        'approved_by' => Auth::user()->id,
                                    ]);

                                    if ($record->is_asset) {
                                        $record->update([
                                            'completed_at' => Carbon::now(),
                                            'completed_by' => Auth::user()->id,
                                        ]);
                                        foreach ($record->purchaseOrderItem as $item) {
                                            $total = \App\Http\Controllers\HelperController::hitungSubtotal((int)$item->quantity, (int)$item->unit_price, (int)$item->discount, (int)$item->tax, $item->tipe_pajak);

                                            $asset = Asset::create([
                                                'name' => $item->product->name,
                                                'product_id' => $item->product_id,
                                                'purchase_order_id' => $record->id,
                                                'purchase_order_item_id' => $item->id,
                                                'purchase_date' => $record->order_date,
                                                'usage_date' => $data['usage_date'] ?? $record->order_date,
                                                'purchase_cost' => $total,
                                                'salvage_value' => $data['salvage_value'] ?? 0,
                                                'useful_life_years' => (int)($data['useful_life_years'] ?? 5),
                                                'asset_coa_id' => $data['asset_coa_id'],
                                                'accumulated_depreciation_coa_id' => $data['accumulated_depreciation_coa_id'],
                                                'depreciation_expense_coa_id' => $data['depreciation_expense_coa_id'],
                                                'status' => 'active',
                                                'notes' => 'Generated from PO ' . $record->po_number,
                                            ]);

                                            $asset->calculateDepreciation();

                                            // Post asset acquisition journal entry
                                            $assetService = new AssetService();
                                            $assetService->postAssetAcquisitionJournal($asset, $record->supplier->coa_id ?? null);
                                        }
                                    }
                                });
                            } elseif ($record->status == 'request_close') {
                                $record->update([
                                    'close_reason' => $data['close_reason'],
                                    'status' => 'closed',
                                    'closed_at' => Carbon::now(),
                                    'closed_by' => Auth::user()->id,
                                ]);
                            }
                        }),
                    Action::make('tolak')
                        ->label('Tolak')
                        ->visible(function ($record) {
                            return Gate::allows('response purchase order')
                                && ($record->status == 'request_approval' || $record->status == 'request_close');
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
                        ->visible(function ($record) {
                            return Gate::allows('request purchase order')
                                && $record->status == 'draft';
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
                        ->visible(function ($record) {
                            return Gate::allows('request purchase order')
                                && ($record->status != 'closed' || $record->status != 'completed');
                        })
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('close_reason')
                                ->label('Close Reason')
                                ->string()
                                ->required(),
                        ])
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (array $data, $record) {
                            $record->update([
                                'status' => 'request_close',
                                'close_reason' => $data['close_reason']
                            ]);
                        }),
                    Action::make('create_receipt_remaining')
                        ->label('Buat Penerimaan Sisa')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('info')
                        ->visible(function ($record) {
                            // Show only if PO is approved/partially_received, has remaining items, AND already has some receipts
                            if ($record->status !== 'approved' && $record->status !== 'partially_received') {
                                return false;
                            }

                            // Must have remaining quantity > 0
                            $hasRemaining = $record->purchaseOrderItem->contains(function ($item) {
                                return $item->remaining_quantity > 0;
                            });

                            // Must have at least one item that already has receipts (partial receipt scenario)
                            $hasAnyReceipts = $record->purchaseOrderItem->contains(function ($item) {
                                return $item->purchaseReceiptItem()->exists();
                            });

                            return $hasRemaining && $hasAnyReceipts;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Buat Penerimaan untuk Barang Sisa')
                        ->modalDescription('Akan membuat penerimaan baru untuk item yang masih memiliki quantity tersisa.')
                        ->modalSubmitActionLabel('Buat Penerimaan')
                        ->action(function ($record) {
                            // Redirect to create purchase receipt page with pre-selected PO
                            return redirect()->route('filament.admin.resources.purchase-receipts.create', [
                                'purchase_order_id' => $record->id
                            ]);
                        }),
                    Action::make('cetak_pdf')
                        ->label('Cetak PDF')
                        ->icon('heroicon-o-document-check')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status != 'draft' && $record->status != 'closed';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.purchase-order', [
                                'purchaseOrder' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Pembelian_' . $record->po_number . '.pdf');
                        }),
                    Action::make('update_total_amount')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->action(function ($record) {
                            $purchaseOrderService = app(PurchaseOrderService::class);
                            $purchaseOrderService->updateTotalAmount($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total amount berhasil disinkronkan");
                        }),
                    Action::make('terbit_invoice')
                        ->label('Terbitkan Invoice')
                        ->visible(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->form([
                            TextInput::make('invoice_number')
                                ->label('Invoice Number')
                                ->required()
                                ->reactive()
                                ->suffixAction(ActionsAction::make('generateInvoiceNumber')
                                    ->icon('heroicon-m-arrow-path') // ikon reload
                                    ->tooltip('Generate Invoice Number')
                                    ->action(function ($set, $get, $state) {
                                        $invoiceService = app(InvoiceService::class);
                                        $set('invoice_number', $invoiceService->generateInvoiceNumber());
                                    }))
                                ->maxLength(255),
                            DatePicker::make('invoice_date')
                                ->label('Tanggal Invoice')
                                ->required(),
                            DatePicker::make('due_date')
                                ->label('Tanggal Jatuh Tempo')
                                ->required(),
                            TextInput::make('tax')
                                ->required()
                                ->suffix('%')
                                ->numeric()
                                ->maxValue(100)
                                ->default(0),
                            TextInput::make('other_fee')
                                ->required()
                                ->numeric()
                                ->default(function ($record) {
                                    $otherFee = 0;
                                    foreach ($record->purchaseOrderBiaya as $biaya) {
                                        if ($biaya->masuk_invoice == 1) {
                                            $otherFee += ($biaya->total * $biaya->currency->to_rupiah);
                                        }
                                    }

                                    return $otherFee;
                                }),
                        ])
                        ->action(function (array $data, $record) {
                            // Check invoice
                            $invoice = Invoice::where('invoice_number', $data['invoice_number'])->first();
                            if ($invoice) {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "Invoice number sudah digunakan");
                                return;
                            }
                            $purchaseOrderService = app(PurchaseOrderService::class);
                            $purchaseOrderService->generateInvoice($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Generate invoice berhasil");
                        })
                ])->button()
                    ->label('Action')
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
        return parent::getEloquentQuery();
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
