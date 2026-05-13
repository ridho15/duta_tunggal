<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleOrderResource\Pages;
use App\Filament\Resources\SaleOrderResource\Pages\ViewSaleOrder;
use App\Filament\Resources\SaleOrderResource\RelationManagers\SaleOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\ChartOfAccount;
use App\Models\Currency;
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
use App\Support\CurrencyConversionResolver;
use App\Support\WarehouseStockOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
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

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Penjualan';

    protected static ?string $navigationLabel = 'Pesanan Penjualan';

    protected static ?string $modelLabel = 'Pesanan Penjualan';

    protected static ?string $pluralModelLabel = 'Pesanan Penjualan';

    // Ensure Penjualan group appears after Pembelian
    protected static ?int $navigationSort = 2;

    protected static function normalizeTaxTypeValue(?string $taxType): string
    {
        $normalized = strtolower(trim((string) $taxType));

        return match ($normalized) {
            'none', 'non pajak', 'non-pajak', 'nonpajak' => 'none',
            'inklusif', 'included', 'ppn included', 'ppn-included' => 'inklusif',
            'eksklusif', 'eklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'eklusif',
            default => 'eklusif',
        };
    }

    protected static function taxTypeOptions(): array
    {
        return [
            'none' => 'Non Pajak',
            'eklusif' => 'Eksklusif (PPN ditambahkan)',
            'inklusif' => 'Inklusif (PPN termasuk)',
        ];
    }

    protected static function resolveCurrencyOptions(): array
    {
        return Currency::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Currency $currency) {
                $label = trim((string) $currency->name);

                if ($currency->code) {
                    $label .= ' (' . $currency->code . ')';
                }

                return [$currency->id => $label];
            })
            ->all();
    }

    protected static function resolveDefaultCurrencyId(): ?int
    {
        return CurrencyConversionResolver::resolveCurrencyIdByCode('IDR')
            ?? Currency::query()->orderBy('id')->value('id');
    }

    protected static function resolveCurrencySymbol(?int $currencyId): string
    {
        return CurrencyConversionResolver::resolveSymbol($currencyId);
    }

    protected static function resolveExchangeRate(?int $currencyId): float
    {
        return CurrencyConversionResolver::resolveRate($currencyId);
    }

    public static function normalizeFormDataForPersist(array $data): array
    {
        $currencyId = is_numeric($data['currency_id'] ?? null)
            ? (int) $data['currency_id']
            : static::resolveDefaultCurrencyId();

        $data['currency_id'] = $currencyId;
        $data['exchange_rate'] = static::resolveExchangeRate($currencyId);

        $items = [];
        foreach (($data['saleOrderItem'] ?? []) as $item) {
            $taxType = static::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);
            $unitPrice = (float) HelperController::parseIndonesianMoney($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $taxRate = $taxType === 'none' ? 0.0 : (float) \App\Models\TaxSetting::activeRate('PPN');

            $item['tipe_pajak'] = $taxType;
            $item['tax'] = $taxRate;
            $item['subtotal'] = static::formatMoneyState(HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $taxRate, $taxType));
            $item['tax_nominal'] = number_format(HelperController::hitungTaxNominal($quantity, $unitPrice, $discount, $taxRate, $taxType), 0, ',', '.');
            $items[] = $item;
        }

        if (! empty($items)) {
            $data['saleOrderItem'] = $items;
            $total = 0.0;

            foreach ($items as $item) {
                $total += HelperController::hitungSubtotal(
                    (float) ($item['quantity'] ?? 0),
                    (float) HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                    (float) ($item['discount'] ?? 0),
                    (float) ($item['tax'] ?? 0),
                    $item['tipe_pajak'] ?? 'eklusif'
                );
            }

            $data['total_amount'] = static::formatMoneyState($total);
        }

        return $data;
    }

    protected static function formatMoneyState(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format((float) HelperController::parseIndonesianMoney($amount), 0, ',', '.');
    }

    protected static function getWarehouseAllocationOptions(?int $productId, ?int $selectedWarehouseId = null): array
    {
        return WarehouseStockOptions::forProduct($productId, $selectedWarehouseId);
    }

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
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $items = [];
                                $quotation = Quotation::find($state);
                                if ($quotation) {
                                    foreach ($quotation->quotationItem as $item) {
                                        $tipePajak = static::normalizeTaxTypeValue($item->tax_type);
                                        $unitPrice = (float) HelperController::parseIndonesianMoney($item->unit_price);
                                        array_push($items, [
                                            'product_id' => $item->product_id,
                                            'quantity' => $item->quantity,
                                            'unit_price' => number_format($unitPrice, 0, ',', '.'),
                                            'discount' => $item->discount,
                                            'tax' => $item->tax,
                                            'tipe_pajak' => $tipePajak,
                                            'notes' => $item->notes,
                                            'warehouse_id' => null,
                                            'subtotal' => static::formatMoneyState(HelperController::hitungSubtotal($item->quantity, $unitPrice, $item->discount, $item->tax, $tipePajak)),
                                            'tax_nominal' => number_format(HelperController::hitungTaxNominal($item->quantity, $unitPrice, $item->discount, $item->tax, $tipePajak), 0, ',', '.'),
                                            'rak_id' => null,
                                            'unit' => $item->product->uom?->abbreviation ?? '-',
                                            'total' => number_format($item->quantity * $unitPrice, 0, ',', '.'),
                                        ]);
                                    }
                                    $set('total_amount', static::formatMoneyState($quotation->total_amount ?? 0));
                                    $set('customer_id', $quotation->customer_id);
                                    $set('cabang_id', $quotation->cabang_id);
                                    if (! empty($quotation->currency_id)) {
                                        $set('currency_id', $quotation->currency_id);
                                        $set('exchange_rate', static::resolveExchangeRate((int) $quotation->currency_id));
                                    }
                                    $set('shipped_to', $quotation->customer->address);
                                    // Warisi tempo pembayaran dari quotation yang sudah disetujui
                                    if ($quotation->tempo_pembayaran) {
                                        $set('tempo_pembayaran', (int) $quotation->tempo_pembayaran);
                                    }
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
                                        $tipePajak = static::normalizeTaxTypeValue($item->tipe_pajak ?? null);
                                        array_push($items, [
                                            'product_id' => $item->product_id,
                                            'unit_price' => number_format((float) $item->unit_price, 0, ',', '.'),
                                            'quantity' => $item->quantity,
                                            'discount' => $item->discount,
                                            'tax' => $item->tax,
                                            'tipe_pajak' => $tipePajak,
                                            'subtotal' => static::formatMoneyState(HelperController::hitungSubtotal($item->quantity, (float) $item->unit_price, $item->discount, $item->tax, $tipePajak)),
                                            'tax_nominal' => number_format(HelperController::hitungTaxNominal($item->quantity, (float) $item->unit_price, $item->discount, $item->tax, $tipePajak), 0, ',', '.'),
                                            'notes' => $item->notes,
                                        ]);
                                    }
                                    $set('total_amount', static::formatMoneyState($saleOrder->total_amount ?? 0));
                                    $set('customer_id', $saleOrder->customer_id);
                                    $set('cabang_id', $saleOrder->cabang_id);
                                    if (! empty($saleOrder->currency_id)) {
                                        $set('currency_id', $saleOrder->currency_id);
                                        $set('exchange_rate', static::resolveExchangeRate((int) $saleOrder->currency_id));
                                    }
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
                                    // G3: auto-fill tempo pembayaran dari customer
                                    if ($customer->tempo_kredit) {
                                        $set('tempo_pembayaran', (int)$customer->tempo_kredit);
                                    }
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
                                            ->validationMessages([
                                                'required' => 'Email customer tidak boleh kosong',
                                                'email' => 'Format email customer tidak valid',
                                            ])
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('fax')
                                            ->label('Fax')
                                            ->validationMessages([
                                                'required' => 'Fax customer tidak boleh kosong',
                                            ])
                                            ->required(),
                                        TextInput::make('tempo_kredit')
                                            ->numeric()
                                            ->label('Tempo Kredit (Hari)')
                                            ->helperText('Hari')
                                            ->validationMessages([
                                                'required' => 'Tempo kredit customer tidak boleh kosong',
                                                'numeric' => 'Tempo kredit customer harus berupa angka',
                                            ])
                                            ->required()
                                            ->default(0),
                                        TextInput::make('kredit_limit')
                                            ->label('Kredit Limit (Rp.)')
                                            ->default(0)
                                            ->validationMessages([
                                                'required' => 'Kredit limit customer tidak boleh kosong',
                                                'numeric' => 'Kredit limit customer harus berupa angka',
                                            ])
                                            ->required()
                                            ->indonesianMoney(),
                                        Radio::make('tipe_pembayaran')
                                            ->label('Tipe Bayar Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'Bebas' => 'Bebas',
                                                'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                                                'Kredit' => 'Kredit (Bayar Kredit)'
                                            ])->required()
                                            ->validationMessages([
                                                'required' => 'Tipe bayar customer wajib dipilih',
                                            ]),
                                        Radio::make('tipe')
                                            ->label('Tipe Customer')
                                            ->inlineLabel()
                                            ->options([
                                                'PKP' => 'PKP',
                                                'PRI' => 'PRI'
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tipe customer wajib dipilih',
                                            ]),
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
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($cabang) {
                                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                        });
                                }

                                return \App\Models\Cabang::orderBy('kode')->limit(50)->get()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                            })
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
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
                        Select::make('currency_id')
                            ->label('Currency')
                            ->options(static::resolveCurrencyOptions())
                            ->default(static::resolveDefaultCurrencyId())
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state) {
                                $component->state($state ?: static::resolveDefaultCurrencyId());
                            })
                            ->afterStateUpdated(function ($set, $state) {
                                $set('exchange_rate', static::resolveExchangeRate(is_numeric($state) ? (int) $state : null));
                            })
                            ->helperText('Mata uang transaksi. Nilai invoice dan laporan akan dikonversi ke Rupiah menggunakan kurs master.'),
                        Hidden::make('exchange_rate')
                            ->default(fn ($get) => static::resolveExchangeRate(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                            ->dehydrated(true),
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
                            ->helperText(function ($state, $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId || !$state) return null;

                                $customer = Customer::find($customerId);
                                if (!$customer || $customer->tipe_pembayaran !== 'Kredit') return null;

                                $creditService = app(CreditValidationService::class);
                                $validation = $creditService->canCustomerMakePurchase($customer, (float) HelperController::parseIndonesianMoney($state));

                                if (!$validation['can_purchase']) {
                                    return '⚠️ Peringatan: ' . implode(' | ', $validation['messages']);
                                }

                                if (!empty($validation['warnings'])) {
                                    return '⚠️ ' . implode(' | ', $validation['warnings']);
                                }

                                return null;
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
                        \Filament\Forms\Components\TextInput::make('tempo_pembayaran')
                            ->label('Tempo Pembayaran (Hari)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Diisi otomatis dari data customer (tempo kredit). Dapat diubah bila perlu.')
                            ->suffix('Hari'),
                        Repeater::make('saleOrderItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->reactive()
                            ->columns(3)
                            ->minItems(1)
                            ->rules([new \App\Rules\NoDuplicateProducts()])
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
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                        if ($product) {
                                            $set('unit_price', number_format((float)$product->sell_price, 0, ',', '.'));
                                            $set('unit', $product->uom?->abbreviation ?? '-');
                                            $set('subtotal', static::formatMoneyState(HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null)));
                                            $_base = (float)($get('quantity') ?? 0) * (float)HelperController::parseIndonesianMoney($get('unit_price') ?? 0) * (1 - (float)($get('discount') ?? 0) / 100);
                                            try {
                                                $_r = \App\Services\TaxService::compute($_base, (float)($get('tax') ?? 0), $get('tipe_pajak') ?? 'None');
                                                $set('tax_nominal', number_format((float)$_r['ppn'], 0, ',', '.'));
                                            } catch (\Throwable $e) {
                                                $set('tax_nominal', '0');
                                                \Illuminate\Support\Facades\Log::warning('TaxService gagal menghitung pajak: ' . $e->getMessage());
                                                Notification::make()->title('Perhitungan Pajak Gagal')->body('Nilai pajak direset ke 0. Silakan periksa konfigurasi tipe pajak atau hubungi administrator.')->warning()->send();
                                            }
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
                                        $totalStock = InventoryStock::freeQtyFor($get('product_id'));

                                        if ($totalStock > 0) {
                                            // Get stock by warehouses
                                            $stockByWarehouse = InventoryStock::where('product_id', $get('product_id'))
                                                ->with(['warehouse', 'rak'])
                                                ->get()
                                                ->groupBy('warehouse_id')
                                                ->map(function ($items) {
                                                    $warehouseName = $items->first()->warehouse->name ?? 'Unknown';
                                                    $warehouseTotal = $items->sum('free_qty');

                                                    if ($warehouseTotal <= 0) {
                                                        return null;
                                                    }

                                                    return $warehouseName . ': ' . number_format($warehouseTotal, 0, ',', '.');
                                                })
                                                ->filter()
                                                ->values()
                                                ->take(3) // Limit to 3 warehouses for display
                                                ->implode(' | ');

                                            return "📦 Total Stock: " . number_format($totalStock, 0, ',', '.') . " (" . $stockByWarehouse . ")";
                                        }

                                        return "⚠️ Tidak ada stok bebas";
                                    })
                                    ->relationship('product', 'name')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('unit')
                                    ->label('Satuan')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default('-')
                                    ->extraAttributes(['title' => 'Satuan produk (otomatis)'])
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record?->product) {
                                            $component->state($record->product->uom?->abbreviation ?? '-');
                                        }
                                    }),


                                TextInput::make('quantity')
                                    ->label('Quantity')
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
                                                    return;
                                                }

                                                $allocations = collect($get('warehouseAllocations') ?? []);

                                                if ($allocations->isEmpty()) {
                                                    $fail('Wajib mengisi alokasi gudang minimal 1 gudang.');
                                                    return;
                                                }

                                                $allocationQty = (float) $allocations->sum(function ($row) {
                                                    return (float) ($row['quantity'] ?? 0);
                                                });

                                                if (abs($allocationQty - (float) $value) > 0.0001) {
                                                    $fail('Total qty alokasi gudang harus sama dengan quantity item.');
                                                    return;
                                                }

                                                foreach ($allocations as $allocation) {
                                                    $allocationWarehouseId = $allocation['warehouse_id'] ?? null;
                                                    $allocationItemQty = (float) ($allocation['quantity'] ?? 0);

                                                    if (!$allocationWarehouseId || $allocationItemQty <= 0) {
                                                        $fail('Setiap alokasi wajib memiliki gudang dan qty > 0.');
                                                        return;
                                                    }
                                                }
                                            };
                                        }
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $qty = (float)($state ?? 0);
                                        $price = (float)HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                        $set('total', number_format($qty * $price, 0, ',', '.'));
                                        $set('subtotal', static::formatMoneyState(HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null)));
                                        $_base = $qty * $price * (1 - (float)($get('discount') ?? 0) / 100);
                                        try {
                                            $_r = \App\Services\TaxService::compute($_base, (float)($get('tax') ?? 0), $get('tipe_pajak') ?? 'None');
                                            $set('tax_nominal', number_format((float)$_r['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                            \Illuminate\Support\Facades\Log::warning('TaxService gagal menghitung pajak: ' . $e->getMessage());
                                            Notification::make()->title('Perhitungan Pajak Gagal')->body('Nilai pajak direset ke 0. Silakan periksa konfigurasi tipe pajak atau hubungi administrator.')->warning()->send();
                                        }
                                    })
                                    ->helperText(function ($get) {
                                        $productId = $get('product_id');
                                        $quantity = (float) ($get('quantity') ?? 0);

                                        if (!$productId) {
                                            return 'Pilih produk terlebih dahulu';
                                        }

                                        $allocations = collect($get('warehouseAllocations') ?? []);
                                        if ($allocations->isEmpty()) {
                                            $totalStock = InventoryStock::freeQtyFor($productId);
                                            return "📦 Total stok bebas: " . number_format($totalStock, 0, ',', '.') . " | Isi alokasi gudang di atas.";
                                        }

                                        $allocationQty = (float) $allocations->sum(fn($r) => (float) ($r['quantity'] ?? 0));
                                        if ($quantity > 0 && abs($allocationQty - $quantity) > 0.0001) {
                                            return "❌ Total alokasi ({$allocationQty}) tidak sama dengan quantity ({$quantity})";
                                        }

                                        return "✅ Total alokasi gudang: " . number_format($allocationQty, 0, ',', '.');
                                    })
                                    ->required()
                                    ->default(0),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'required' => 'Unit Price harus diisi',
                                        'numeric' => 'Unit Price tidak valid !'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $qty = (float)($get('quantity') ?? 0);
                                        $price = (float)HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                        $set('total', number_format($qty * $price, 0, ',', '.'));
                                        $set('subtotal', static::formatMoneyState(HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null)));
                                        $_base = $qty * $price * (1 - (float)($get('discount') ?? 0) / 100);
                                        try {
                                            $_r = \App\Services\TaxService::compute($_base, (float)($get('tax') ?? 0), $get('tipe_pajak') ?? 'None');
                                            $set('tax_nominal', number_format((float)$_r['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                            \Illuminate\Support\Facades\Log::warning('TaxService gagal menghitung pajak: ' . $e->getMessage());
                                            Notification::make()->title('Perhitungan Pajak Gagal')->body('Nilai pajak direset ke 0. Silakan periksa konfigurasi tipe pajak atau hubungi administrator.')->warning()->send();
                                        }
                                    }),
                                TextInput::make('total')
                                    ->label('Total (Harga × Qty)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $total = (float)$record->quantity * (float)$record->unit_price;
                                            $component->state(number_format($total, 0, ',', '.'));
                                        }
                                    }),
                                TextInput::make('discount')
                                    ->label('Discount (%)')
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
                                        $set('subtotal', static::formatMoneyState(HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null)));
                                        $_base = (float)($get('quantity') ?? 0) * (float)HelperController::parseIndonesianMoney($get('unit_price') ?? 0) * (1 - (float)($state ?? 0) / 100);
                                        try {
                                            $_r = \App\Services\TaxService::compute($_base, (float)($get('tax') ?? 0), $get('tipe_pajak') ?? 'None');
                                            $set('tax_nominal', number_format((float)$_r['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                            \Illuminate\Support\Facades\Log::warning('TaxService gagal menghitung pajak: ' . $e->getMessage());
                                            Notification::make()->title('Perhitungan Pajak Gagal')->body('Nilai pajak direset ke 0. Silakan periksa konfigurasi tipe pajak atau hubungi administrator.')->warning()->send();
                                        }
                                    })
                                    ->suffix('%'),
                                \Filament\Forms\Components\Select::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->options(static::taxTypeOptions())
                                    ->default('eklusif')
                                    ->reactive()
                                    ->afterStateHydrated(function ($component, $state) {
                                        $component->state(static::normalizeTaxTypeValue($state));
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $normalizedState = static::normalizeTaxTypeValue($state);
                                        $defaultTax = \App\Models\TaxSetting::activeRate('PPN');

                                        if ($normalizedState === 'none') {
                                            $set('tax', 0);
                                        } else {
                                            $set('tax', $defaultTax);
                                        }

                                        $set('subtotal', static::formatMoneyState(HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $normalizedState)));
                                        $_base = (float)($get('quantity') ?? 0) * (float)HelperController::parseIndonesianMoney($get('unit_price') ?? 0) * (1 - (float)($get('discount') ?? 0) / 100);
                                        try {
                                            $_r = \App\Services\TaxService::compute($_base, (float)($get('tax') ?? 0), $normalizedState);
                                            $set('tax_nominal', number_format((float)$_r['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                            \Illuminate\Support\Facades\Log::warning('TaxService gagal menghitung pajak: ' . $e->getMessage());
                                            Notification::make()->title('Perhitungan Pajak Gagal')->body('Nilai pajak direset ke 0. Silakan periksa konfigurasi tipe pajak atau hubungi administrator.')->warning()->send();
                                        }
                                    }),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->reactive()
                                    ->disabled()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->helperText('Dihitung otomatis dari setting global PPN dan tidak dapat diedit manual.')
                                    ->validationMessages([
                                        'numeric' => 'Tax harus berupa angka',
                                        'min' => 'Tax minimal 0%',
                                        'max' => 'Tax maksimal 100%'
                                    ])
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $taxType = static::normalizeTaxTypeValue($get('tipe_pajak'));
                                        $set('subtotal', static::formatMoneyState(HelperController::hitungSubtotal($get('quantity'), HelperController::parseIndonesianMoney($get('unit_price')), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null)));
                                        $_base = (float)($get('quantity') ?? 0) * (float)HelperController::parseIndonesianMoney($get('unit_price') ?? 0) * (1 - (float)($get('discount') ?? 0) / 100);
                                        try {
                                            $_r = \App\Services\TaxService::compute($_base, (float)($state ?? 0), $taxType);
                                            $set('tax_nominal', number_format((float)$_r['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                            \Illuminate\Support\Facades\Log::warning('TaxService gagal menghitung pajak: ' . $e->getMessage());
                                            Notification::make()->title('Perhitungan Pajak Gagal')->body('Nilai pajak direset ke 0. Silakan periksa konfigurasi tipe pajak atau hubungi administrator.')->warning()->send();
                                        }
                                    })
                                    ->default(fn(callable $get) => static::normalizeTaxTypeValue($get('tipe_pajak') ?? null) === 'none' ? 0 : \App\Models\TaxSetting::activeRate('PPN'))
                                    ->suffix('%'),
                                TextInput::make('tax_nominal')
                                    ->label('Nominal Pajak')
                                    ->prefix(fn (callable $get) => static::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $base = (float)$record->quantity * (float)$record->unit_price * (1 - (float)$record->discount / 100);
                                            try {
                                                $r = \App\Services\TaxService::compute($base, (float)$record->tax, static::normalizeTaxTypeValue($record->tipe_pajak));
                                                $component->state(number_format($r['ppn'], 0, ',', '.'));
                                            } catch (\Throwable $e) {
                                                $component->state('0');
                                                \Illuminate\Support\Facades\Log::warning('TaxService gagal saat mengisi formulir: ' . $e->getMessage());
                                            }
                                        }
                                    }),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->readOnly()
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $component->state(static::formatMoneyState(HelperController::hitungSubtotal($record->quantity, $record->unit_price, $record->discount, $record->tax, static::normalizeTaxTypeValue($record->tipe_pajak))));
                                        }
                                    })
                                    ->afterStateUpdated(function ($component, $state, $livewire, $get) {
                                        $qty   = $get('quantity') ?? 0;
                                        $price = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                        $disc  = $get('discount') ?? 0;
                                        $tax   = $get('tax') ?? 0;
                                        $type  = static::normalizeTaxTypeValue($get('tipe_pajak'));

                                        $component->state(static::formatMoneyState(HelperController::hitungSubtotal($qty, $price, $disc, $tax, $type)));

                                        // hitung ulang total order
                                        $total = 0;
                                        foreach ($livewire->data['saleOrderItem'] ?? [] as $item) {
                                            $total += HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                static::normalizeTaxTypeValue($item['tipe_pajak'] ?? null)
                                            );
                                        }
                                        $livewire->data['total_amount'] = static::formatMoneyState($total);

                                        // Check credit validation
                                        $customerId = $livewire->data['customer_id'] ?? null;
                                        if ($customerId && $total > 0) {
                                            $customer = Customer::find($customerId);
                                            if ($customer) {
                                                $creditService = app(CreditValidationService::class);
                                                $validation = $creditService->canCustomerMakePurchase($customer, (float)$total);

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
                                    }),
                                Repeater::make('warehouseAllocations')
                                    ->relationship('warehouseAllocations')
                                    ->label('Alokasi Gudang')
                                    ->helperText('Tentukan gudang sumber stok untuk item ini. Dapat dialokasikan ke beberapa gudang sekaligus.')
                                    ->schema([
                                        Select::make('warehouse_id')
                                            ->label('Gudang')
                                            ->options(function ($get) {
                                                return static::getWarehouseAllocationOptions(
                                                    $get('../../product_id'),
                                                    $get('warehouse_id'),
                                                );
                                            })
                                            ->helperText('Hanya menampilkan gudang yang memiliki stok bebas untuk produk ini.')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Gudang alokasi wajib dipilih',
                                            ]),
                                        TextInput::make('quantity')
                                            ->label('Qty Alokasi')
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Qty alokasi wajib diisi',
                                                'numeric' => 'Qty alokasi harus berupa angka',
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->columnspanFull()
                                    ->minItems(1)
                                    ->reactive(),
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
                                $set('total_amount', static::formatMoneyState($totalAmount));
                            })
                    ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                        return match ($state) {
                            'draft'           => 'Draft',
                            'request_approve' => 'Menunggu Persetujuan',
                            'approved'        => 'Disetujui',
                            'confirmed'       => 'Dikonfirmasi',
                            'completed'       => 'Selesai',
                            'request_close'   => 'Minta Ditutup',
                            'closed'          => 'Ditutup',
                            'reject'          => 'Ditolak',
                            'canceled'        => 'Dibatalkan',
                            'received'        => 'Diterima',
                            default           => Str::upper($state),
                        };
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
                    ->rupiah()
                    ->sortable(),
                TextColumn::make('item_units')
                    ->label('Satuan')
                    ->state(function (SaleOrder $record) {
                        return $record->saleOrderItem
                            ->map(fn($item) => $item->product?->uom?->abbreviation ?? '-')
                            ->filter()
                            ->unique()
                            ->implode(', ');
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_status')
                    ->label('Status Stok')
                    ->badge()
                    ->state(function (SaleOrder $record): string {
                        if ($record->status === 'completed') {
                            return 'SELESAI';
                        }

                        return $record->hasInsufficientStock() ? 'STOK KURANG' : 'STOK READY';
                    })
                    ->color(function (SaleOrder $record): string {
                        if ($record->status === 'completed') {
                            return 'gray';
                        }

                        return $record->hasInsufficientStock() ? 'warning' : 'success';
                    })
                    ->size('sm')
                    ->weight('bold')
                    ->tooltip(function (SaleOrder $record): ?string {
                        if ($record->status === 'completed') {
                            return '✅ Sales order sudah selesai';
                        }

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
                TextColumn::make('tempo_pembayaran')
                    ->label('Tempo (Hari)')
                    ->suffix(' hari')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                        return "({$customer->code}) {$customer->name}";
                    }),
                SelectFilter::make('stock_status')
                    ->label('Status Stok')
                    ->options([
                        'sufficient' => 'Stok Bebas Cukup',
                        'insufficient' => 'Kurang Stok'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'insufficient') {
                            return $query->whereHas('saleOrderItem', function (Builder $q) {
                                $q->whereRaw('quantity > (
                                    SELECT COALESCE(SUM(qty_available - qty_reserved), 0) 
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
                                    SELECT COALESCE(SUM(qty_available - qty_reserved), 0) 
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
                // Additional eager loading for table display
                return $query->with(['customer', 'saleOrderItem.product']);
            })
            ->recordClasses(function (SaleOrder $record): string {
                if ($record->status === 'completed') {
                    return '';
                }

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
                            return Auth::user()->hasPermissionTo('request sales order')
                                && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            try {
                                $salesOrderService = app(SalesOrderService::class);
                                $salesOrderService->requestApprove($record);
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order telah diajukan untuk persetujuan. Proses selanjutnya: Persetujuan oleh Manajer Sales.");
                            } catch (ValidationException $e) {
                                $messages = collect($e->errors())->flatten()->implode(' ');

                                HelperController::sendNotification(isSuccess: false, title: "Gagal Mengajukan Persetujuan", message: $messages ?: 'Validasi request approve gagal.');
                            }
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Permintaan penutupan Sales Order telah diajukan. Proses selanjutnya: Konfirmasi penutupan oleh Manajer Sales.");
                        }),
                    Action::make('approve')
                        ->label('Setujui')
                        ->requiresConfirmation()
                        ->modalHeading('Setujui Sales Order')
                        ->modalDescription('Dengan menyetujui Sales Order ini, Anda mengkonfirmasi bahwa persyaratan pembayaran dan pengiriman telah disepakati.')
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response sales order')
                                && $record->status == 'request_approve';
                        })
                        ->action(function ($record) {
                            try {
                                $salesOrderService = app(SalesOrderService::class);
                                $salesOrderService->approve($record);
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order telah disetujui. Proses selanjutnya: Pembuatan Delivery Order oleh Tim Gudang/Logistik.");
                            } catch (ValidationException $e) {
                                $messages = collect($e->errors())->flatten()->implode(' ');

                                HelperController::sendNotification(isSuccess: false, title: "Gagal Menyetujui Sales Order", message: $messages ?: 'Validasi approval gagal.');
                            }
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order telah ditutup. Proses selanjutnya: Tim Finance perlu memastikan semua Invoice terkait telah diselesaikan dan tidak ada pembayaran yang tertunggak.");
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order telah ditolak. Proses selanjutnya: Tim Sales perlu merevisi data pesanan sesuai feedback dan mengajukan kembali untuk disetujui.");
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
                            ])->setPaper('A4', 'portrait');

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

                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order telah selesai. Proses selanjutnya: Penerbitan Invoice oleh Tim Finance.");
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
                                        ->indonesianMoney()
                                        ->required()
                                        ->default(0),
                                    Select::make('coa_id')
                                        ->label('COA')
                                        ->preload()
                                        ->searchable()
                                        ->options(ChartOfAccount::orderBy('code')->get()->mapWithKeys(function ($coa) {
                                            return [$coa->id => "({$coa->code}) {$coa->name}"];
                                        }))
                                        ->required(),
                                    Textarea::make('note')
                                        ->label('Note')
                                        ->nullable()
                                ];
                            } else {
                                return [
                                    TextInput::make('titip_saldo')
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Saldo Titip Customer berhasil disimpan. Proses selanjutnya: Tim Finance perlu memverifikasi saldo titip dan memastikan jurnal keuangan telah dicatat dengan benar.");
                        }),
                    Action::make('create_purchase_order')
                        ->label('Create Purchase Order')
                        ->color('success')
                        ->icon('heroicon-o-document-duplicate')
                        ->visible(function ($record) {
                            if (!Auth::user()->hasPermissionTo('create purchase order')) {
                                return false;
                            }

                            return $record->saleOrderItem()
                                ->whereDoesntHave('purchaseOrderItem')
                                ->exists();
                        })
                        ->form([
                            Fieldset::make("Form")
                                ->schema([
                                    CheckboxList::make('selected_sale_order_item_ids')
                                        ->label('Pilih Item Sales Order')
                                        ->options(function ($record) {
                                            return $record->saleOrderItem()
                                                ->with(['product'])
                                                ->whereDoesntHave('purchaseOrderItem')
                                                ->get()
                                                ->mapWithKeys(function ($item) {
                                                    $productName = $item->product?->name ?? 'Produk';
                                                    $sku = $item->product?->sku ?? '-';
                                                    $unit = $item->product?->uom?->abbreviation ?? '-';

                                                    return [
                                                        $item->id => "({$sku}) {$productName} | Qty: {$item->quantity} {$unit}",
                                                    ];
                                                })
                                                ->toArray();
                                        })
                                        ->required()
                                        ->columns(1)
                                        ->validationMessages([
                                            'required' => 'Minimal satu item Sales Order harus dipilih',
                                        ]),
                                    Select::make('supplier_id')
                                        ->label('Supplier')
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
                                            return Supplier::select(['id', 'perusahaan', 'code', DB::raw("CONCAT('(', code, ') ', perusahaan) as label")])
                                                ->orderBy('perusahaan')
                                                ->limit(50)
                                                ->get()
                                                ->pluck('label', 'id');
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
                                            return Warehouse::select(['id', 'kode', 'name', DB::raw("CONCAT('(', kode, ') ', name) as label")])
                                                ->orderBy('name')
                                                ->limit(50)
                                                ->get()
                                                ->pluck('label', 'id');
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
                            try {
                                $salesOrderService = app(SalesOrderService::class);
                                $salesOrderService->createPurchaseOrder($record, $data);
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Purchase Order berhasil dibuat dari Sales Order. Proses selanjutnya: Persetujuan Purchase Order oleh Manajer Purchasing.");
                            } catch (\Throwable $e) {
                                HelperController::sendNotification(isSuccess: false, title: 'Information', message: $e->getMessage());
                            }
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
                    '<summary class="cursor-pointer font-semibold">Panduan Sales Order</summary>' .
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Informasi Sales Order')
                    ->columns(3)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('so_number')
                            ->label('SO Number'),
                        \Filament\Infolists\Components\TextEntry::make('customer.name')
                            ->label('Customer')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('cabang.nama')
                            ->label('Cabang')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        \Filament\Infolists\Components\TextEntry::make('order_date')
                            ->label('Order Date')
                            ->dateTime('d/m/Y'),
                        \Filament\Infolists\Components\TextEntry::make('delivery_date')
                            ->label('Delivery Date')
                            ->dateTime('d/m/Y')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('tempo_pembayaran')
                            ->label('Tempo Pembayaran')
                            ->formatStateUsing(fn($state) => $state ? $state . ' Hari' : '-'),
                        \Filament\Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->rupiah(),
                        \Filament\Infolists\Components\TextEntry::make('tipe_pengiriman')
                            ->label('Tipe Pengiriman')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('shipped_to')
                            ->label('Shipped To')
                            ->placeholder('-'),
                    ]),
                \Filament\Infolists\Components\Section::make('Item Sales Order')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('saleOrderItem')
                            ->label('')
                            ->columnSpanFull()
                            ->columns(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('product.name')
                                    ->label('Produk')
                                    ->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty'),
                                \Filament\Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Harga Satuan')
                                    ->formatStateUsing(fn($state) => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                                \Filament\Infolists\Components\TextEntry::make('line_total')
                                    ->label('Harga Satuan x Qty')
                                    ->getStateUsing(function ($record) {
                                        $price = (float) ($record->unit_price ?? 0);
                                        $qty = (float) ($record->quantity ?? 0);
                                        return 'Rp ' . number_format($price * $qty, 0, ',', '.');
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('discount')
                                    ->label('Diskon (%)')
                                    ->formatStateUsing(fn($state) => $state . '%'),
                                \Filament\Infolists\Components\TextEntry::make('tax')
                                    ->label('Pajak (%)')
                                    ->getStateUsing(function ($record) {
                                        $taxType = static::normalizeTaxTypeValue($record->tipe_pajak);
                                        return $record->tax . '% (' . $taxType . ')';
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('tax_nominal')
                                    ->label('Nominal Pajak')
                                    ->getStateUsing(function ($record) {
                                        $unitPrice = (float) ($record->unit_price ?? 0);
                                        $qty = (float) ($record->quantity ?? 0);
                                        $discount = (float) ($record->discount ?? 0);
                                        $tax = (float) ($record->tax ?? 0);
                                        $tipePajak = $record->tipe_pajak ?? null;
                                        $taxNominal = \App\Http\Controllers\HelperController::hitungTaxNominal($qty, $unitPrice, $discount, $tax, $tipePajak);
                                        return 'Rp ' . number_format($taxNominal, 0, ',', '.');
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('subtotal_display')
                                    ->label('Sub Total')
                                    ->columnSpan(2)
                                    ->getStateUsing(function ($record) {
                                        $subtotal = \App\Http\Controllers\HelperController::hitungSubtotal(
                                            $record->quantity,
                                            (float) $record->unit_price,
                                            $record->discount,
                                            $record->tax,
                                            $record->tipe_pajak ?? null
                                        );
                                        return 'Rp ' . number_format($subtotal, 0, ',', '.');
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('stock_tersedia')
                                    ->label('Stok Bebas (Semua Gudang)')
                                    ->columnSpan(2)
                                    ->getStateUsing(function ($record) {
                                        $stocks = \App\Models\InventoryStock::where('product_id', $record->product_id)
                                            ->with('warehouse')
                                            ->get();
                                        $total = $stocks->sum('free_qty');
                                        $perWh = $stocks->groupBy('warehouse_id')
                                            ->map(function ($s) {
                                                $available = (float) $s->sum('free_qty');

                                                if ($available <= 0) {
                                                    return null;
                                                }

                                                return ($s->first()->warehouse?->name ?? 'Wh#' . $s->first()->warehouse_id) . ': ' . number_format($available, 0, ',', '.');
                                            })
                                            ->filter()
                                            ->values()->implode(' | ');
                                        return $total > 0
                                            ? '📦 ' . number_format((float) $total, 0, ',', '.') . ($perWh ? " ({$perWh})" : '')
                                            : '⚠️ Habis';
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('warehouse_mode')
                                    ->label('Mode Gudang')
                                    ->columnSpan(2)
                                    ->getStateUsing(function ($record) {
                                        $allocCount = $record->warehouseAllocations()->count();
                                        if ($allocCount > 0) {
                                            return "📦 Multi-Gudang ({$allocCount} gudang)";
                                        }
                                        return $record->warehouse
                                            ? '🏪 Single: ' . $record->warehouse->name
                                            : '⚠️ Belum diset';
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('warehouse_allocations_summary')
                                    ->label('Alokasi Order (Qty per Gudang)')
                                    ->columnSpan(2)
                                    ->getStateUsing(function ($record) {
                                        $allocations = $record->warehouseAllocations()->with('warehouse')->get();
                                        if ($allocations->isEmpty()) {
                                            return $record->warehouse
                                                ? ($record->warehouse->name . ' — qty: ' . $record->quantity)
                                                : '-';
                                        }
                                        return $allocations->map(function ($alloc) {
                                            $wh = $alloc->warehouse?->name ?? "Gudang #{$alloc->warehouse_id}";
                                            return "{$wh}: " . number_format((float) $alloc->quantity, 0, ',', '.');
                                        })->implode(' | ');
                                    }),
                            ]),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('created_at', 'asc')
            ->with([
                'customer',
                'saleOrderItem.product',
                'salesInvoices',
            ])
            ->withCount('saleOrderItem');
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
