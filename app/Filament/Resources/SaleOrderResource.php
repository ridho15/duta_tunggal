<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleOrderResource\Pages;
use App\Filament\Resources\SaleOrderResource\Pages\ViewSaleOrder;
use App\Filament\Resources\SaleOrderResource\RelationManagers\SaleOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\CustomerService;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use App\Services\CreditValidationService;
use Barryvdh\DomPDF\Facade\Pdf;
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
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleOrderResource extends Resource
{
    protected static ?string $model = SaleOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    // Group label updated to include English hint per request
    protected static ?string $navigationGroup = 'Penjualan (Sales Order)';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?string $pluralModelLabel = 'Penjualan';

    // Ensure Penjualan group appears after Pembelian
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Penjualan')
                    ->schema([
                        Section::make()
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Placeholder::make('status')
                                    ->label('Status')
                                    ->content(function ($record) {
                                        return $record ? Str::upper($record->status) : '-';
                                    }),
                                Select::make('options_form')
                                    ->label('Options From')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->hiddenOn(['edit', 'view'])
                                    ->loadingMessage("loading...")
                                    ->options(function () {
                                        return [
                                            '0' => 'None',
                                            '1' => 'Refer Penjualan',
                                            '2' => 'Refer Quotation',
                                        ];
                                    })->default(0),
                            ]),
                        Select::make('quotation_id')
                            ->label('Quotation')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $items = [];
                                $quotation = Quotation::find($state);
                                if ($quotation) {
                                    foreach ($quotation->quotationItem as $item) {
                                        array_push($items, [
                                            'product_id' => $item->product_id,
                                            'quantity' => $item->quantity,
                                            'unit_price' => HelperController::parseIndonesianMoney($item->unit_price),
                                            'discount' => $item->discount,
                                            'tax' => $item->tax,
                                            'notes' => $item->notes,
                                            'warehouse_id' => $item->warehouse_id,
                                            'subtotal' => HelperController::hitungSubtotal($item->quantity, HelperController::parseIndonesianMoney($item->unit_price), $item->discount, $item->tax, null),
                                            'rak_id' => $item->rak_id
                                        ]);
                                    }
                                    $set('total_amount', $quotation->total_amount);
                                    $set('customer_id', $quotation->customer_id);
                                    $set('shipped_to', $quotation->customer->address);
                                    $set('saleOrderItem', $items);
                                }
                            })
                            ->visible(function ($get) {
                                return $get('options_form') == 2;
                            })
                            ->options(Quotation::where('status', 'approve')->select(['id', 'customer_id', 'quotation_number'])->get()->pluck('quotation_number', 'id'))
                            ->required()
                            ->validationMessages([
                                'required' => 'Quotation wajib dipilih'
                            ]),
                        Select::make('sale_order_id')
                            ->label('Sales Order')
                            ->preload()
                            ->loadingMessage('Loading ...')
                            ->reactive()
                            ->searchable()
                            ->visible(function ($get) {
                                return $get('options_form') == 1;
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $items = [];
                                $saleOrder = SaleOrder::find($state);
                                if ($saleOrder) {
                                    foreach ($saleOrder->saleOrderItem as $item) {
                                        array_push($items, [
                                            'product_id' => $item->product_id,
                                            'unit_price' => (int) $item->unit_price,
                                            'quantity' => $item->quantity,
                                            'discount' => $item->discount,
                                            'tax' => $item->tax,
                                            'notes' => $item->notes,
                                        ]);
                                    }
                                    $set('total_amount', $saleOrder->total_amount);
                                    $set('customer_id', $saleOrder->customer_id);
                                    $set('shipped_to', $saleOrder->customer->address);
                                }
                                $set('saleOrderItem', $items);
                            })
                            ->options(SaleOrder::select(['id', 'so_number', 'customer_id'])->get()->pluck('so_number', 'id'))
                            ->required()
                            ->validationMessages([
                                'required' => 'Sales Order wajib dipilih'
                            ]),
                        Select::make('customer_id')
                            ->required()
                            ->label('Customer')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->helperText(function ($state) {
                                $customer = Customer::find($state);
                                if (!$customer) return null;

                                $creditService = app(CreditValidationService::class);
                                $creditSummary = $creditService->getCreditSummary($customer);

                                $helper = [];

                                // Deposit info
                                if ($customer->deposit->remaining_amount) {
                                    $helper[] = "Saldo: Rp." . number_format($customer->deposit->remaining_amount, 0, ',', '.');
                                }

                                // Credit info for credit customers
                                if ($customer->tipe_pembayaran === 'Kredit') {
                                    $helper[] = "Kredit Limit: Rp." . number_format($creditSummary['credit_limit'], 0, ',', '.');
                                    $helper[] = "Terpakai: Rp." . number_format($creditSummary['current_usage'], 0, ',', '.') . " ({$creditSummary['usage_percentage']}%)";
                                    $helper[] = "Tersedia: Rp." . number_format($creditSummary['available_credit'], 0, ',', '.');

                                    if ($creditSummary['overdue_count'] > 0) {
                                        $helper[] = "⚠️ {$creditSummary['overdue_count']} tagihan jatuh tempo (Rp." . number_format($creditSummary['overdue_total'], 0, ',', '.') . ")";
                                    }
                                }

                                return implode(' | ', $helper);
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $customer = Customer::find($state);
                                if ($customer) {
                                    $set('shipped_to', $customer->address);
                                }
                            })
                            ->relationship('customer', 'name')
                            ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                                return "({$customer->code}) {$customer->name}";
                            })
                            ->validationMessages([
                                'required' => 'Customer wajib dipilih'
                            ])
                            ->createOptionForm([
                                Fieldset::make('Form Customer')
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Kode Customer')
                                            ->required()
                                            ->reactive()
                                            ->suffixAction(ActionsAction::make('generateCode')
                                                ->icon('heroicon-m-arrow-path') // ikon reload
                                                ->tooltip('Generate Kode Customer')
                                                ->action(function ($set, $get, $state) {
                                                    $customerService = app(CustomerService::class);
                                                    $set('code', $customerService->generateCode());
                                                }))
                                            ->validationMessages([
                                                'unique' => 'Kode customer sudah digunakan',
                                                'required' => 'Kode customer tidak boleh kosong',
                                            ])
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('name')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Nama customer tidak boleh kosong',
                                            ])
                                            ->label('Nama Customer')
                                            ->maxLength(255),
                                        TextInput::make('perusahaan')
                                            ->label('Perusahaan')
                                            ->validationMessages([
                                                'required' => 'Perusahaan tidak boleh kosong',
                                            ])
                                            ->required(),
                                        TextInput::make('nik_npwp')
                                            ->label('NIK / NPWP')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'NIK / NPWP tidak boleh kosong',
                                                'numeric' => 'NIK / NPWP tidak valid !'
                                            ])
                                            ->numeric(),
                                        TextInput::make('address')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Alamat tidak boleh kosong',
                                            ])
                                            ->label('Alamat')
                                            ->maxLength(255),
                                        TextInput::make('telephone')
                                            ->label('Telepon')
                                            ->tel()
                                            ->validationMessages([
                                                'regex' => 'Telepon tidak valid !'
                                            ])
                                            ->placeholder('Contoh: 0211234567')
                                            ->regex('/^0[2-9][0-9]{1,3}[0-9]{5,8}$/')
                                            ->helperText('Hanya nomor telepon rumah/kantor, bukan nomor HP.')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label('Handphone')
                                            ->tel()
                                            ->validationMessages([
                                                'required' => 'Nomor handphone tidak boleh kosong',
                                                'regex' => 'Nomor handphone tidak valid !'
                                            ])
                                            ->maxLength(15)
                                            ->rules(['regex:/^08[0-9]{8,12}$/'])
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('fax')
                                            ->label('Fax')
                                            ->required(),
                                        TextInput::make('tempo_kredit')
                                            ->numeric()
                                            ->label('Tempo Kredit (Hari)')
                                            ->helperText('Hari')
                                            ->required()
                                            ->default(0),
                                        TextInput::make('kredit_limit')
                                            ->label('Kredit Limit (Rp.)')
                                            ->default(0)
                                            ->required()
                                            ->numeric()
                                            ->indonesianMoney(),
                                        Radio::make('tipe_pembayaran')
                                            ->label('Tipe Bayar Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'Bebas' => 'Bebas',
                                                'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                                                'Kredit' => 'Kredit (Bayar Kredit)'
                                            ])->required(),
                                        Radio::make('tipe')
                                            ->label('Tipe Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'PKP' => 'PKP',
                                                'PRI' => 'PRI'
                                            ])
                                            ->required(),
                                        Checkbox::make('isSpecial')
                                            ->label('Spesial (Ya / Tidak)'),
                                        Textarea::make('keterangan')
                                            ->label('Keterangan')
                                            ->nullable(),
                                    ]),
                            ]),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    return \App\Models\Cabang::where('id', $user?->cabang_id)
                                        ->get()
                                        ->mapWithKeys(function ($cabang) {
                                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                        });
                                }
                                
                                return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                            })
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih'
                            ]),
                        TextInput::make('so_number')
                            ->label('SO Number')
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'SO number tidak boleh kosong',
                                'unique' => 'SO Number sudah digunakan !'
                            ])
                            ->unique(ignoreRecord: true)
                            ->suffixAction(ActionsAction::make('generateSoNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate SO Number')
                                ->action(function ($set, $get, $state) {
                                    $salesOrderService = app(SalesOrderService::class);
                                    $set('so_number', $salesOrderService->generateSoNumber());
                                }))
                            ->maxLength(255),
                        DatePicker::make('order_date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal order wajib diisi'
                            ]),
                        DatePicker::make('delivery_date')
                            ->validationMessages([
                                'date' => 'Format tanggal pengiriman tidak valid'
                            ]),
                        TextInput::make('shipped_to')
                            ->label('Shipped To')
                            ->reactive()
                            ->nullable()
                            ->maxLength(255)
                            ->validationMessages([
                                'max' => 'Alamat pengiriman maksimal 255 karakter'
                            ]),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->required()
                            ->disabled()
                            ->reactive()
                            ->default(0)
                            ->indonesianMoney()
                            ->validationMessages([
                                'required' => 'Total amount wajib diisi',
                                'numeric' => 'Total amount harus berupa angka'
                            ])
                            ->rule(function ($get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $customerId = $get('customer_id');
                                    if (!$customerId || !$value) return;

                                    $customer = Customer::find($customerId);
                                    if (!$customer) return;

                                    $creditService = app(CreditValidationService::class);
                                    $validation = $creditService->canCustomerMakePurchase($customer, (float)$value);

                                    if (!$validation['can_purchase']) {
                                        $fail(implode(' ', $validation['messages']));
                                    }
                                };
                            }),
                        Radio::make('tipe_pengiriman')
                            ->label('Tipe Pengiriman Ke Customer')
                            ->inline()
                            ->options([
                                'Ambil Sendiri' => 'Customer Ambil Sendiri',
                                'Kirim Langsung' => 'Kirim Ke Customer'
                            ])->required()
                            ->validationMessages([
                                'required' => 'Tipe Pengiriman belum di pilih'
                            ]),
                        Repeater::make('saleOrderItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->reactive()
                            ->columns(3)
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data) {
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                return $data;
                            })
                            ->addActionLabel("Add Items")
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable(['sku', 'name'])
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit_price', $product->sell_price);
                                            $set('subtotal', HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Produk belum dipilih'
                                    ])
                                    ->required()
                                    ->helperText(function ($get) {
                                        if (!$get('product_id')) {
                                            return null;
                                        }

                                        // Get total stock across all locations
                                        $totalStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->sum('qty_available');

                                        if ($totalStock > 0) {
                                            // Get stock by warehouses
                                            $stockByWarehouse = InventoryStock::where('product_id', $get('product_id'))
                                                ->where('qty_available', '>', 0)
                                                ->with(['warehouse', 'rak'])
                                                ->get()
                                                ->groupBy('warehouse_id')
                                                ->map(function ($items) {
                                                    $warehouseName = $items->first()->warehouse->name ?? 'Unknown';
                                                    $warehouseTotal = $items->sum('qty_available');
                                                    return $warehouseName . ': ' . number_format($warehouseTotal, 0, ',', '.');
                                                })
                                                ->values()
                                                ->take(3) // Limit to 3 warehouses for display
                                                ->implode(' | ');

                                            return "📦 Total Stock: " . number_format($totalStock, 0, ',', '.') . " (" . $stockByWarehouse . ")";
                                        }

                                        return "⚠️ Tidak ada stock tersedia";
                                    })
                                    ->relationship('product', 'name')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(function ($get) {
                                        $user = Auth::user();
                                        $manageType = $user?->manage_type ?? [];
                                        $query = Warehouse::whereHas('inventoryStock', function (Builder $query) use ($get) {
                                            $query->where('product_id', $get('product_id'));
                                        });
                                        
                                        if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                            $query->where('cabang_id', $user?->cabang_id);
                                        }
                                        
                                        return $query->get()->mapWithKeys(function ($warehouse) {
                                            return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                        });
                                    })
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search, $get) {
                                        $user = Auth::user();
                                        $manageType = $user?->manage_type ?? [];
                                        $query = Warehouse::whereHas('inventoryStock', function (Builder $query) use ($get) {
                                            $query->where('product_id', $get('product_id'));
                                        })
                                        ->where(function ($q) use ($search) {
                                            $q->where('name', 'like', "%{$search}%")
                                              ->orWhere('kode', 'like', "%{$search}%");
                                        });
                                        
                                        if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                            $query->where('cabang_id', $user?->cabang_id);
                                        }
                                        
                                        return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                            return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                        });
                                    })
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Gudang belum dipilih'
                                    ])
                                    ->helperText(function ($get) {
                                        if (!$get('product_id') || !$get('warehouse_id')) {
                                            return null;
                                        }

                                        $warehouseStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->sum('qty_available');

                                        if ($warehouseStock > 0) {
                                            return "🏪 Stock di gudang ini: " . number_format($warehouseStock, 0, ',', '.');
                                        }

                                        return "⚠️ Tidak ada stock di gudang ini";
                                    }),
                                Select::make('rak_id')
                                    ->label(function ($get) {
                                        $baseLabel = 'Rak';
                                        
                                        if (!$get('product_id') || !$get('warehouse_id') || !$get('rak_id')) {
                                            return $baseLabel;
                                        }

                                        $rakStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->where('rak_id', $get('rak_id'))
                                            ->sum('qty_available');

                                        if ($rakStock <= 0) {
                                            return $baseLabel . ' 🚨 STOCK HABIS';
                                        } elseif ($rakStock < 10) {
                                            return $baseLabel . ' ⚠️ STOCK SEDIKIT (' . $rakStock . ')';
                                        } else {
                                            return $baseLabel . ' ✅ (' . number_format($rakStock, 0, ',', '.') . ')';
                                        }
                                    })
                                    ->preload()
                                    ->reactive()
                                    ->searchable(['name', 'code'])
                                    ->relationship('rak', 'name', function (Builder $query, $get) {
                                        if (!$get('product_id') || !$get('warehouse_id')) {
                                            return $query->where('warehouse_id', $get('warehouse_id') ?? 0);
                                        }

                                        // Only show racks that have inventory stock for the selected product and warehouse
                                        return $query->where('warehouse_id', $get('warehouse_id'))
                                                    ->whereHas('inventoryStock', function (Builder $q) use ($get) {
                                                        $q->where('product_id', $get('product_id'))
                                                          ->where('qty_available', '>', 0);
                                                    });
                                    })
                                    ->nullable()
                                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                        return "({$rak->code}) {$rak->name}";
                                    })
                                    ->helperText(function ($get) {
                                        if (!$get('product_id') || !$get('warehouse_id')) {
                                            return 'Pilih produk dan gudang terlebih dahulu';
                                        }

                                        // Check if there are any racks with stock for this product in this warehouse
                                        $availableRacks = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->where('qty_available', '>', 0)
                                            ->whereNotNull('rak_id')
                                            ->count();

                                        if ($availableRacks == 0) {
                                            return "❌ TIDAK ADA RAK DENGAN STOCK PRODUK INI DI GUDANG INI";
                                        }

                                        if (!$get('rak_id')) {
                                            return "📦 {$availableRacks} rak tersedia dengan stock produk ini";
                                        }

                                        $rakStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->where('rak_id', $get('rak_id'))
                                            ->sum('qty_available');

                                        if ($rakStock <= 0) {
                                            return "🚨 STOCK HABIS - Rak ini tidak memiliki stock produk ini";
                                        }

                                        if ($rakStock < 10) {
                                            return "⚠️ STOCK SEDIKIT - Tersedia: " . number_format($rakStock, 0, ',', '.') . " (kurang dari 10)";
                                        }

                                        return "✅ Stock di rak ini: " . number_format($rakStock, 0, ',', '.');
                                    }),
                                TextInput::make('quantity')
                                    ->label(function ($get) {
                                        $baseLabel = 'Quantity';
                                        
                                        if (!$get('product_id') || !$get('warehouse_id') || !$get('rak_id')) {
                                            return $baseLabel;
                                        }

                                        $rakStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->where('rak_id', $get('rak_id'))
                                            ->sum('qty_available');

                                        $currentQuantity = (float) ($get('quantity') ?? 0);
                                        
                                        if ($rakStock <= 0) {
                                            return $baseLabel . ' 🚨 STOCK HABIS';
                                        } elseif ($currentQuantity > $rakStock) {
                                            return $baseLabel . ' ❌ MELEBIHI STOCK (' . number_format($rakStock, 0, ',', '.') . ')';
                                        } elseif ($rakStock < 10) {
                                            return $baseLabel . ' ⚠️ STOCK SEDIKIT (' . number_format($rakStock, 0, ',', '.') . ')';
                                        } else {
                                            return $baseLabel . ' ✅ (' . number_format($rakStock, 0, ',', '.') . ')';
                                        }
                                    })
                                    ->numeric()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Quantity harus diisi',
                                        'numeric' => 'Quantity tidak valid !'
                                    ])
                                    ->rules([
                                        function ($get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                if (!$value || $value <= 0) {
                                                    return; // Skip validation if quantity is empty or zero
                                                }

                                                $productId = $get('product_id');
                                                $warehouseId = $get('warehouse_id');
                                                $rakId = $get('rak_id');

                                                if (!$productId || !$warehouseId) {
                                                    $fail('Pilih produk dan gudang terlebih dahulu');
                                                    return;
                                                }

                                                // If rak is selected, check stock at rak level
                                                if ($rakId) {
                                                    $availableStock = InventoryStock::where('product_id', $productId)
                                                        ->where('warehouse_id', $warehouseId)
                                                        ->where('rak_id', $rakId)
                                                        ->sum('qty_available');
                                                } else {
                                                    // If no rak selected, check total warehouse stock (including null rak_id)
                                                    $availableStock = InventoryStock::where('product_id', $productId)
                                                        ->where('warehouse_id', $warehouseId)
                                                        ->sum('qty_available');
                                                }

                                                if ($availableStock < $value) {
                                                    $stockLocation = $rakId ? 'rak ini' : 'gudang ini';
                                                    $fail("Stock tidak mencukupi! Tersedia di {$stockLocation}: " . number_format($availableStock, 0, ',', '.') . " | Diminta: " . number_format($value, 0, ',', '.'));
                                                }
                                            };
                                        }
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->suffix(function ($get) {
                                        if (!$get('product_id') || !$get('warehouse_id')) {
                                            return null;
                                        }

                                        $rakId = $get('rak_id');
                                        if ($rakId) {
                                            // Show rak-level stock
                                            $stock = InventoryStock::where('product_id', $get('product_id'))
                                                ->where('warehouse_id', $get('warehouse_id'))
                                                ->where('rak_id', $rakId)
                                                ->sum('qty_available');
                                            $level = 'Rak';
                                        } else {
                                            // Show warehouse-level stock
                                            $stock = InventoryStock::where('product_id', $get('product_id'))
                                                ->where('warehouse_id', $get('warehouse_id'))
                                                ->sum('qty_available');
                                            $level = 'Gudang';
                                        }

                                        if ($stock <= 0) {
                                            return '🚨 HABIS';
                                        } elseif ($stock < 10) {
                                            return '⚠️ ' . $stock;
                                        } else {
                                            return '✅ ' . number_format($stock, 0, ',', '.');
                                        }
                                    })
                                    ->helperText(function ($get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('warehouse_id');
                                        $rakId = $get('rak_id');
                                        $quantity = (float) ($get('quantity') ?? 0);

                                        if (!$productId || !$warehouseId) {
                                            return 'Pilih produk dan gudang terlebih dahulu';
                                        }

                                        if (!$rakId) {
                                            // Show warehouse-level stock when no rak is selected
                                            $warehouseStock = InventoryStock::where('product_id', $productId)
                                                ->where('warehouse_id', $warehouseId)
                                                ->sum('qty_available');

                                            if ($quantity <= 0) {
                                                return "Stock gudang: " . number_format($warehouseStock, 0, ',', '.') . " | Pilih rak untuk stock detail";
                                            }

                                            if ($quantity > $warehouseStock) {
                                                return "❌ QUANTITY MELEBIHI STOCK GUDANG - Tersedia: " . number_format($warehouseStock, 0, ',', '.') . " | Diminta: " . number_format($quantity, 0, ',', '.');
                                            }

                                            return "✅ Quantity OK (Gudang) - Tersedia: " . number_format($warehouseStock, 0, ',', '.') . " | Diminta: " . number_format($quantity, 0, ',', '.');
                                        }

                                        // Show rak-level stock when rak is selected
                                        $rakStock = InventoryStock::where('product_id', $productId)
                                            ->where('warehouse_id', $warehouseId)
                                            ->where('rak_id', $rakId)
                                            ->sum('qty_available');

                                        if ($quantity <= 0) {
                                            return "Stock rak: " . number_format($rakStock, 0, ',', '.') . " | Masukkan quantity untuk validasi";
                                        }

                                        if ($quantity > $rakStock) {
                                            return "❌ QUANTITY MELEBIHI STOCK RAK - Tersedia: " . number_format($rakStock, 0, ',', '.') . " | Diminta: " . number_format($quantity, 0, ',', '.');
                                        }

                                        return "✅ Quantity OK (Rak) - Tersedia: " . number_format($rakStock, 0, ',', '.') . " | Diminta: " . number_format($quantity, 0, ',', '.');
                                    })
                                    ->required()
                                    ->default(0),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'required' => 'Unit Price harus diisi',
                                        'numeric' => 'Unit Price tidak valid !'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                    }),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->validationMessages([
                                        'numeric' => 'Discount harus berupa angka',
                                        'min' => 'Discount minimal 0%',
                                        'max' => 'Discount maksimal 100%'
                                    ])
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->suffix('%'),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->reactive()
                                    ->validationMessages([
                                        'numeric' => 'Tax harus berupa angka',
                                        'min' => 'Tax minimal 0%',
                                        'max' => 'Tax maksimal 100%'
                                    ])
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                                    })
                                    ->default(0)
                                    ->suffix('%'),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->readOnly()
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $component->state(HelperController::hitungSubtotal($record->quantity, $record->unit_price, $record->discount, $record->tax, $record->tipe_pajak ?? null));
                                        }
                                    })
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $quantity = $livewire->data['quantity'] ?? 0;
                                        $unit_price = HelperController::parseIndonesianMoney($livewire->data['unit_price'] ?? 0);
                                        $discount = $livewire->data['discount'] ?? 0;
                                        $tax = $livewire->data['tax'] ?? 0;
                                        $component->state(HelperController::hitungSubtotal($quantity, $unit_price, $discount, $tax, $livewire->data['tipe_pajak'] ?? null));

                                        // Calculate and update total amount
                                        $items = $livewire->data['saleOrderItem'] ?? [];
                                        $totalAmount = 0;
                                        foreach ($items as $item) {
                                            $totalAmount += HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                        }
                                        $livewire->data['total_amount'] = $totalAmount;

                                        // Check credit validation
                                        $customerId = $livewire->data['customer_id'] ?? null;
                                        if ($customerId && $totalAmount > 0) {
                                            $customer = Customer::find($customerId);
                                            if ($customer) {
                                                $creditService = app(CreditValidationService::class);
                                                $validation = $creditService->canCustomerMakePurchase($customer, (float)$totalAmount);

                                                if (!$validation['can_purchase']) {
                                                    Notification::make()
                                                        ->title('Peringatan Kredit')
                                                        ->body(implode('<br>', $validation['messages']))
                                                        ->danger()
                                                        ->persistent()
                                                        ->send();
                                                } elseif (!empty($validation['warnings'])) {
                                                    Notification::make()
                                                        ->title('Peringatan Kredit')
                                                        ->body(implode('<br>', $validation['warnings']))
                                                        ->warning()
                                                        ->send();
                                                }
                                            }
                                        }
                                    })
                                    
                            ])
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Calculate total amount whenever repeater items change
                                $totalAmount = 0;
                                if (is_array($state)) {
                                    foreach ($state as $item) {
                                        $totalAmount += HelperController::hitungSubtotal(
                                            $item['quantity'] ?? 0,
                                            HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                                            $item['discount'] ?? 0,
                                            $item['tax'] ?? 0,
                                            $item['tipe_pajak'] ?? null
                                        );
                                    }
                                }
                                $set('total_amount', $totalAmount);
                            })
            ])
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer')
                    ->label('Customer')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('customer', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
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
                            'received' => 'primary',
                            'approved' => 'success',
                            'confirmed' => 'success',
                            'canceled' => 'danger',
                            'reject' => 'danger',
                            'request_approve' => 'primary',
                            'request_close' => 'warning',
                            'closed' => 'danger',
                            default => '-'
                        };
                    })
                    ->badge(),
                TextColumn::make('shipped_to')
                    ->label('Shipped To')
                    ->searchable(),
                TextColumn::make('delivery_date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status Stok')
                    ->badge()
                    ->state(function (SaleOrder $record): string {
                        return $record->hasInsufficientStock() ? 'KURANG STOK' : 'STOK OK';
                    })
                    ->color(function (SaleOrder $record): string {
                        return $record->hasInsufficientStock() ? 'warning' : 'success';
                    })
                    ->size('sm')
                    ->weight('bold')
                    ->tooltip(function (SaleOrder $record): ?string {
                        if ($record->hasInsufficientStock()) {
                            $insufficientItems = $record->getInsufficientStockItems();
                            $tooltip = "⚠️ Item dengan stok kurang:\n";
                            foreach ($insufficientItems as $item) {
                                $tooltip .= "• {$item['item']->product->name}: Tersedia {$item['available']}, Dibutuhkan {$item['needed']}\n";
                            }
                            return trim($tooltip);
                        }
                        return '✅ Semua item memiliki stok yang cukup';
                    }),
                TextColumn::make('requestApproveBy.name')
                    ->label('Request Approve By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Request Approve At'),
                TextColumn::make('requestCloseBy.name')
                    ->label('Request Approve By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_close_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Request Approve At'),
                TextColumn::make('approveBy.name')
                    ->label('Approve By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Approve At'),
                TextColumn::make('closeBy.name')
                    ->label('Close By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('close_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Close At'),
                TextColumn::make('rejectBy.name')
                    ->label('Reject By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reject_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Reject At'),
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
                SelectFilter::make('customer')
                    ->label('Customer')
                    ->searchable()
                    ->preload()
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                        return "({$customer->code}) {$customer->name}";
                    }),
                SelectFilter::make('stock_status')
                    ->label('Status Stok')
                    ->options([
                        'sufficient' => 'Stok Tersedia',
                        'insufficient' => 'Kurang Stok'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'insufficient') {
                            return $query->whereHas('saleOrderItem', function (Builder $q) {
                                $q->whereRaw('quantity > (
                                    SELECT COALESCE(SUM(qty_available), 0) 
                                    FROM inventory_stocks 
                                    WHERE inventory_stocks.product_id = sale_order_items.product_id 
                                    AND inventory_stocks.warehouse_id = sale_order_items.warehouse_id 
                                    AND inventory_stocks.rak_id = sale_order_items.rak_id
                                )');
                            });
                        }

                        if ($data['value'] === 'sufficient') {
                            return $query->whereDoesntHave('saleOrderItem', function (Builder $q) {
                                $q->whereRaw('quantity > (
                                    SELECT COALESCE(SUM(qty_available), 0) 
                                    FROM inventory_stocks 
                                    WHERE inventory_stocks.product_id = sale_order_items.product_id 
                                    AND inventory_stocks.warehouse_id = sale_order_items.warehouse_id 
                                    AND inventory_stocks.rak_id = sale_order_items.rak_id
                                )');
                            });
                        }

                        return $query;
                    })
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with('saleOrderItem');
            })
            ->recordClasses(function (SaleOrder $record): string {
                return $record->hasInsufficientStock() ? 'insufficient-stock-row' : '';
            })
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('primary')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('update sales order') &&
                                   in_array($record->status, ['draft', 'request_approve', 'approved']);
                        }),
                    DeleteAction::make()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('delete sales order') &&
                                   in_array($record->status, ['draft', 'request_approve']);
                        }),
                    Action::make('request_approve')
                        ->label('Request Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request sales order') && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestApprove($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request approve");
                        }),
                    Action::make('request_close')
                        ->label('Request Close')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request sales order') &&
                                   in_array($record->status, ['approved', 'confirmed', 'completed']);
                        })
                        ->form(
                            function ($record) {
                                return [
                                    Textarea::make('reason_close')
                                        ->label('Reason Close')
                                        ->string()
                                        ->required(),
                                ];
                            }
                        )
                        ->action(function (array $data, $record) {
                            $record->update($data);
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestClose($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request close");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_approve');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->approve($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan approve sale order");
                        }),
                    Action::make('closed')
                        ->label('Close')
                        ->requiresConfirmation()
                        ->color('warning')
                        ->icon('heroicon-o-x-circle')
                        ->form(
                            function ($record) {
                                return [
                                    Textarea::make('reason_close')
                                        ->label('Reason Close')
                                        ->string()
                                        ->required()
                                        ->default($record->reason_close),
                                ];
                            }
                        )
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_close');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->close($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Closed");
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_approve');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->reject($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan Reject Sale");
                        }),
                    Action::make('pdf_sale_order')
                        ->label('Download PDF')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status == 'approved' || $record->status == 'completed' || $record->status == 'confirmed' || $record->status == 'received';
                        })
                        ->icon('heroicon-o-document')
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.sales-order', [
                                'saleOrder' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Sale_Order_' . $record->so_number . '.pdf');
                        }),

                    Action::make('completed')
                        ->label('Complete')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            if (!Auth::user()->hasPermissionTo('update sales order')) {
                                return false;
                            }

                            if (!in_array($record->status, ['approved', 'confirmed'])) {
                                return false;
                            }

                            // Untuk Ambil Sendiri: cukup approved tanpa Delivery Order
                            if ($record->tipe_pengiriman === 'Ambil Sendiri') {
                                return true;
                            }

                            // Untuk Kirim Langsung: perlu Delivery Order completed
                            return $record->deliveryOrder()->where('status', 'completed')->exists();
                        })
                        ->color('success')
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->completed($record);

                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Completed");
                        }),
                    Action::make('btn_titip_saldo')
                        ->label('Saldo Titip Customer')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('update deposit') &&
                                   in_array($record->status, ['approved', 'confirmed', 'completed']);
                        })
                        ->form(function ($record) {
                            if ($record->customer->deposit->id == null) {
                                return [
                                    TextInput::make('titip_saldo')
                                        ->numeric()
                                        ->indonesianMoney()
                                        ->required()
                                        ->default(0),
                                    Select::make('coa_id')
                                        ->label('COA')
                                        ->preload()
                                        ->searchable()
                                        ->relationship('coa', 'name')
                                        ->required(),
                                    Textarea::make('note')
                                        ->label('Note')
                                        ->nullable()
                                ];
                            } else {
                                return [
                                    TextInput::make('titip_saldo')
                                        ->numeric()
                                        ->indonesianMoney()
                                        ->required()
                                        ->default(0),
                                    Textarea::make('note')
                                        ->label('Note')
                                        ->nullable()
                                ];
                            }
                        })
                        ->action(function (array $data, $record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->titipSaldo($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Saldo Titip Customer berhasil disimpan");
                        }),
                    Action::make('create_purchase_order')
                        ->label('Create Purchase Order')
                        ->color('success')
                        ->icon('heroicon-o-document-duplicate')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('create purchase order');
                        })
                        ->form([
                            Fieldset::make("Form")
                                ->schema([
                                    Select::make('supplier_id')
                                        ->label('Supplier')
                                        ->preload()
                                        ->reactive()
                                        ->searchable()
                                        ->validationMessages([
                                            'required' => 'Supplier harus dipilih',
                                        ])
                                        ->afterStateUpdated(function ($state, $set) {
                                            $supplier = Supplier::find($state);
                                            if ($supplier) {
                                                $set('tempo_hutang', $supplier->tempo_hutang);
                                            }
                                        })
                                        ->options(function () {
                                            return Supplier::select(['id', 'name', 'code', DB::raw("CONCAT('(', code, ') ', name) as label")])->get()->pluck('label', 'id');
                                        })->required(),
                                    TextInput::make('po_number')
                                        ->label('PO Number')
                                        ->string()
                                        ->reactive()
                                        ->validationMessages([
                                            'required' => 'PO Number tidak boleh kosong',
                                            'string' => 'PO Number tidak valid !',
                                            'unique' => 'PO Number sudah digunakan'
                                        ])
                                        ->suffixAction(ActionsAction::make('generatePoNumber')
                                            ->icon('heroicon-m-arrow-path') // ikon reload
                                            ->tooltip('Generate PO Number')
                                            ->action(function ($set, $get, $state) {
                                                $purchaseOrderService = app(PurchaseOrderService::class);
                                                $set('po_number', $purchaseOrderService->generatePoNumber());
                                            }))
                                        ->maxLength(255)
                                        ->rule(function ($state) {
                                            $purchaseOrder = PurchaseOrder::where('po_number', $state)->first();
                                            if ($purchaseOrder) {
                                                HelperController::sendNotification(isSuccess: false, title: 'Information', message: "PO number sudah digunakan");
                                                throw ValidationException::withMessages([
                                                    "items" => 'PO Number sudah digunakan'
                                                ]);
                                            }
                                        })
                                        ->required(),
                                    DatePicker::make('order_date')
                                        ->label('Tanggal Pembelian')
                                        ->validationMessages([
                                            'required' => 'Tanggal Pembelian tidak boleh kosong'
                                        ])
                                        ->required(),
                                    DatePicker::make('delivery_date')
                                        ->label('Tanggal Pengiriman'),
                                    DatePicker::make('expected_date')
                                        ->label('Tanggal Diharapkan'),
                                    Select::make('warehouse_id')
                                        ->label('Gudang')
                                        ->preload()
                                        ->searchable(['name', 'kode'])
                                        ->required()
                                        ->options(function () {
                                            return Warehouse::select(['id', 'kode', 'name', DB::raw("CONCAT('(', kode, ') ', name) as label")])->get()->pluck('label', 'id');
                                        })
                                        ->validationMessages([
                                            'required' => 'Gudang belum dipilih',
                                        ]),
                                    TextInput::make('tempo_hutang')
                                        ->label('Tempo Hutang (Hari)')
                                        ->numeric()
                                        ->reactive()
                                        ->default(0)
                                        ->validationMessages([
                                            'required' => 'Tempo Hutan tidak boleh kosong',
                                        ])
                                        ->required()
                                        ->suffix('Hari'),
                                    Textarea::make('note')
                                        ->label('Note')
                                        ->nullable()
                                ])
                        ])
                        ->action(function (array $data, $record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->createPurchaseOrder($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Purchase Order Created");
                        }),
                    Action::make('sync_total_amount')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('update sales order');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->updateTotalAmount($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                        })
                ])->button()
                    ->label('Action')
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Sale Order</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Sale Order adalah pesanan penjualan yang dibuat dari Quotation atau langsung, memerlukan approval sebelum diproses.</li>' .
                            '<li><strong>Status Flow:</strong> Draft → Request Approve → Approved → Confirmed → Received → Completed. Atau bisa Request Close → Closed.</li>' .
                            '<li><strong>Tipe Pengiriman:</strong> <em>Ambil Sendiri</em> (customer datang ke gudang), <em>Kirim Langsung</em> (barang dikirim ke customer).</li>' .
                            '<li><strong>Validasi:</strong> <em>Status Stok</em> menunjukkan apakah stok cukup. <em>Credit Limit</em> customer dicek saat approve.</li>' .
                            '<li><strong>Stock Management:</strong> <em>Ambil Sendiri</em>: Stock berkurang saat <em>Complete</em> (manual). <em>Kirim Langsung</em>: Perlu Delivery Order completed terlebih dahulu.</li>' .
                            '<li><strong>Actions:</strong> <em>Request Approve</em> (draft), <em>Approve/Reject</em> (request_approve), <em>Request Close</em> (approved+), <em>Close</em> (request_close), <em>Complete</em> (approved+), <em>PDF/Kwitansi</em> (approved+), <em>Create PO</em> (untuk drop ship), <em>Sync Total</em> (update amount).</li>' .
                            '<li><strong>Permissions:</strong> <em>request sales order</em> untuk request actions, <em>response sales order</em> untuk approve/reject/close, <em>update sales order</em> untuk complete.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan inventory, accounting, dan bisa generate Purchase Order untuk drop shipping.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            SaleOrderItemRelationManager::class
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('created_at', 'desc');
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
