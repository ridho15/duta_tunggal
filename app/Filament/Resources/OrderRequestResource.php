<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderRequestResource\Pages;
use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Http\Controllers\HelperController;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Currency;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use App\Support\CurrencyConversionResolver;
use App\Support\ProcurementFailureNotifier;
use App\Support\TaxDefaultResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as FormAction;
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
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Throwable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderRequestResource extends Resource
{
    protected static array $currencyCache = [];

    protected static ?string $model = OrderRequest::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    // Part of the Purchase Order group
    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?string $navigationLabel = 'Permintaan Pembelian';

    protected static ?string $modelLabel = 'Permintaan Pembelian';

    protected static ?string $pluralModelLabel = 'Permintaan Pembelian';

    protected static ?int $navigationSort = 1;

    public static function calculateApprovalItemPreview(
        float $quantity,
        float $unitPrice,
        float $discountPct,
        float $taxPct,
        string $taxType
    ): array {
        $base = $quantity * $unitPrice;
        $afterDisc = $base - $base * ($discountPct / 100);
        $normalizedTaxType = self::normalizeTaxTypeValue($taxType);
        $subtotal = $normalizedTaxType === 'inklusif'
            ? $afterDisc
            : $afterDisc + $afterDisc * ($taxPct / 100);

        try {
            $taxRes = \App\Services\TaxService::compute($afterDisc, $taxPct, self::taxServiceTypeFromTaxType($taxType));
            $taxNominal = (float) ($taxRes['ppn'] ?? 0);
        } catch (\Throwable $e) {
            $taxNominal = 0;
        }

        return [
            'total_cost' => (float) $base,
            'subtotal' => (float) $subtotal,
            'tax_nominal' => (float) $taxNominal,
        ];
    }

    public static function normalizeItemTaxType(?string $itemTaxType): string
    {
        $normalized = strtolower(trim((string) $itemTaxType));

        return match ($normalized) {
            'non pajak', 'none', 'non-pajak', 'nonpajak' => 'none',
            'inklusif', 'included', 'ppn included' => 'inklusif',
            'eksklusif', 'eklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'eklusif',
            default => 'eklusif',
        };
    }

    public static function taxServiceTypeFromItemTaxType(?string $itemTaxType): string
    {
        return match (self::normalizeItemTaxType($itemTaxType)) {
            'none' => 'None',
            'inklusif' => 'PPN Included',
            default => 'PPN Excluded',
        };
    }

    public static function normalizeTaxTypeValue(?string $taxType): string
    {
        $normalized = strtolower(trim((string) $taxType));

        return match ($normalized) {
            'non pajak', 'none', 'non-pajak', 'nonpajak' => 'none',
            'inklusif', 'ppn included', 'included', 'ppn-included' => 'inklusif',
            'eksklusif', 'eklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'eklusif',
            default => 'eklusif',
        };
    }

    public static function taxServiceTypeFromTaxType(?string $taxType): string
    {
        return match (self::normalizeTaxTypeValue($taxType)) {
            'none' => 'None',
            'inklusif' => 'PPN Included',
            default => 'PPN Excluded',
        };
    }

    public static function resolveItemTaxRate(?int $productId, ?string $itemTaxType): float
    {
        $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);

        if ($taxType === 'None') {
            return 0.0;
        }

        return TaxDefaultResolver::resolveForProductId($productId, $taxType);
    }

    public static function resolveCurrency(?int $currencyId): ?Currency
    {
        if (! $currencyId) {
            return null;
        }

        if (! array_key_exists($currencyId, self::$currencyCache)) {
            self::$currencyCache[$currencyId] = Currency::find($currencyId);
        }

        return self::$currencyCache[$currencyId];
    }

    public static function resolveCurrencySymbol(?int $currencyId): string
    {
        return CurrencyConversionResolver::resolveSymbol($currencyId);
    }

    public static function resolveCurrencyRateToRupiah(?int $currencyId): float
    {
        return CurrencyConversionResolver::resolveRate($currencyId);
    }

    public static function convertIdrToCurrency(float $amountInIdr, ?int $currencyId): float
    {
        return CurrencyConversionResolver::convertFromIdr($amountInIdr, $currencyId);
    }

    public static function formatMoneyByCurrency(?int $currencyId, float $amount): string
    {
        return CurrencyConversionResolver::formatAmount($currencyId, $amount);
    }

    public static function resolveProductLabel(?int $productId): ?string
    {
        if (! $productId) {
            return null;
        }

        $product = Product::withoutGlobalScope('product_cabang')->find($productId);

        return $product ? "({$product->sku}) {$product->name}" : null;
    }

    public static function resolveProductOptions(?string $search = null, int $limit = 50): array
    {
        $query = Product::query()->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where(function ($productQuery) use ($search) {
                $productQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)
            ->get()
            ->mapWithKeys(function ($product) {
                return [$product->id => "({$product->sku}) {$product->name}"];
            })
            ->all();
    }

    public static function resolveProductSupplierId(?int $productId): ?int
    {
        if (! $productId) {
            return null;
        }

        $product = Product::withoutGlobalScope('product_cabang')->with('suppliers')->find($productId);

        if (! $product) {
            return null;
        }

        if ($product->supplier_id) {
            return (int) $product->supplier_id;
        }

        return $product->suppliers->first()?->id ? (int) $product->suppliers->first()->id : null;
    }

    public static function resolveSupplierOptions(?int $productId = null, ?string $search = null, int $limit = 50, ?int $currencyId = null): array
    {
        $query = Supplier::query();

        $product = null;
        $linkedSupplierIds = [];
        $linkedPriceMap = [];

        if ($productId) {
            $product = Product::withoutGlobalScope('product_cabang')->find($productId);

            $linkedRows = DB::table('product_supplier')
                ->where('product_id', $productId)
                ->get(['supplier_id', 'supplier_price']);

            foreach ($linkedRows as $row) {
                $supplierId = (int) $row->supplier_id;
                $linkedSupplierIds[] = $supplierId;
                $linkedPriceMap[$supplierId] = $row->supplier_price !== null
                    ? (float) $row->supplier_price
                    : null;
            }

            if ($product?->supplier_id) {
                $linkedSupplierIds[] = (int) $product->supplier_id;
            }

            $linkedSupplierIds = array_values(array_unique(array_map('intval', $linkedSupplierIds)));
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($supplierQuery) use ($search) {
                $supplierQuery->where('perusahaan', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (! empty($linkedSupplierIds)) {
            $placeholders = implode(',', array_fill(0, count($linkedSupplierIds), '?'));
            $query->orderByRaw("CASE WHEN id IN ({$placeholders}) THEN 0 ELSE 1 END", $linkedSupplierIds);
        }

        $query->orderBy('perusahaan');

        return $query->limit($limit)
            ->get()
            ->mapWithKeys(function ($supplier) use ($productId, $linkedPriceMap, $currencyId) {
                $priceLabel = '';
                if ($productId) {
                    $price = $linkedPriceMap[(int) $supplier->id] ?? null;
                    if ($price !== null) {
                        $converted = self::convertIdrToCurrency((float) $price, $currencyId);
                        $priceLabel = ' - ' . self::formatMoneyByCurrency($currencyId, $converted);
                    }
                }

                return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan} {$priceLabel}"];
            })
            ->all();
    }

    public static function resolveSupplierLabel(?int $supplierId, ?int $productId = null, ?int $currencyId = null): ?string
    {
        if (! $supplierId) {
            return null;
        }

        $supplier = Supplier::find($supplierId);

        $priceLabel = '';
        if ($productId && $supplier) {
            $product = Product::withoutGlobalScope('product_cabang')->find($productId);
            $price = $product?->suppliers()->where('suppliers.id', $supplierId)->first()?->pivot?->supplier_price;
            if ($price !== null) {
                $converted = self::convertIdrToCurrency((float) $price, $currencyId);
                $priceLabel = ' - ' . self::formatMoneyByCurrency($currencyId, $converted);
            }
        }

        return $supplier ? trim("({$supplier->code}) {$supplier->perusahaan} {$priceLabel}") : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Order Request')
                    ->schema([
                        TextInput::make('request_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Nomor request wajib diisi.',
                                'unique' => 'Nomor request sudah digunakan, silakan gunakan nomor yang berbeda.',
                                'max' => 'Nomor request maksimal 255 karakter.',
                            ])
                            ->suffixAction(
                                FormAction::make('generateRequestNumber')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function ($set) {
                                        $set('request_number', HelperController::generateRequestNumber());
                                    })
                            ),
                        DatePicker::make('request_date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal request wajib diisi.',
                            ]),
                        Select::make('currency_id')
                            ->label('Mata Uang')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->options(function () {
                                return Currency::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function (Currency $c) {
                                        return [$c->id => "{$c->name} ({$c->symbol})"];
                                    });
                            })
                            ->default(fn() => Currency::where('code', 'IDR')->first()?->id)
                            ->helperText('Pilih mata uang default untuk Order Request ini')
                            ->validationMessages([
                                'required' => 'Mata uang wajib dipilih',
                            ]),
                        Textarea::make('note')
                            ->label('Note')
                            ->nullable(),
                        Repeater::make('orderRequestItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(4)
                            ->hint('Tambahkan item produk yang ingin dipesan')
                            ->minItems(1)
                            ->required()
                            ->validationMessages([
                                'required' => 'Order request harus memiliki setidaknya satu item produk.',
                                'min' => 'Order request harus memiliki setidaknya satu item produk.',
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->reactive()
                                    ->searchable()
                                    ->options(fn() => static::resolveProductOptions(limit: 50))
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                            if ($product) {
                                                $itemCurrencyId = is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);
                                                $currentSupplierId = $get('supplier_id');
                                                $resolvedSupplierId = static::resolveProductSupplierId((int) $state);

                                                if ($currentSupplierId) {
                                                    $supplierMatchesProduct = $product->suppliers()
                                                        ->where('suppliers.id', $currentSupplierId)
                                                        ->exists();

                                                    if (! $supplierMatchesProduct) {
                                                        $currentSupplierId = null;
                                                    }
                                                }

                                                if (! $currentSupplierId && $resolvedSupplierId) {
                                                    $currentSupplierId = $resolvedSupplierId;
                                                }

                                                $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                $taxRate = self::resolveItemTaxRate((int) $state, $itemTaxType);
                                                $set('tax', $taxRate);

                                                // Use item-level supplier price if available
                                                $itemSupplierId = $currentSupplierId;
                                                $unitPrice = self::convertIdrToCurrency((float) $product->cost_price, $itemCurrencyId);
                                                if ($itemSupplierId) {
                                                    $supplierProduct = $product->suppliers()->where('suppliers.id', $itemSupplierId)->first();
                                                    if ($supplierProduct) {
                                                        $supplierPrice = $supplierProduct->pivot->supplier_price;
                                                        $unitPrice = $supplierPrice !== null
                                                            ? self::convertIdrToCurrency((float) $supplierPrice, $itemCurrencyId)
                                                            : self::convertIdrToCurrency((float) $product->cost_price, $itemCurrencyId);
                                                    }
                                                }
                                                $set('supplier_id', $itemSupplierId);
                                                // Store the master price as original_price; user can override unit_price
                                                $set('original_price', $unitPrice);
                                                $set('unit_price', $unitPrice);
                                                $set('unit', $product->uom?->abbreviation ?? '-');
                                                // Autofill cabang item from product if available, but keep editable
                                                $set('cabang_id', $product->cabang_id ?? null);
                                                // Recalculate subtotal
                                                $quantity = (float) ($get('quantity') ?? 0);
                                                $discPct  = (float) ($get('discount') ?? 0);
                                                $taxPct   = $taxRate;
                                                $preview = self::calculateApprovalItemPreview($quantity, $unitPrice, $discPct, $taxPct, $taxType);
                                                $set('total_cost', number_format((float)$preview['total_cost'], 0, ',', '.'));
                                                $set('subtotal', number_format((float)$preview['subtotal'], 0, ',', '.'));
                                                $set('tax_nominal', number_format((float)$preview['tax_nominal'], 0, ',', '.'));
                                            }
                                        }
                                    })
                                    ->getOptionLabelUsing(fn($value): ?string => static::resolveProductLabel(is_numeric($value) ? (int) $value : null))
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        return static::resolveProductOptions($search, 50);
                                    })
                                    ->helperText('Pilih produk yang akan dipesan')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Produk wajib dipilih.',
                                    ]),
                                TextInput::make('unit')
                                    ->label('Satuan')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default('-')
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record?->product) {
                                            $component->state($record->product->uom?->abbreviation ?? '-');
                                        }
                                    }),
                                Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->reactive()
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->options(function (callable $get) {
                                        $productId = $get('product_id');
                                        $itemCurrencyId = is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);

                                        return static::resolveSupplierOptions(is_numeric($productId) ? (int) $productId : null, null, 50, $itemCurrencyId);
                                    })
                                    ->getOptionLabelUsing(function ($value, callable $get): ?string {
                                        $productId = $get('product_id');
                                        $itemCurrencyId = is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);

                                        return static::resolveSupplierLabel(
                                            is_numeric($value) ? (int) $value : null,
                                            is_numeric($productId) ? (int) $productId : null,
                                            $itemCurrencyId
                                        );
                                    })
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        $productId = $get('product_id');
                                        $itemCurrencyId = is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);

                                        return static::resolveSupplierOptions(is_numeric($productId) ? (int) $productId : null, $search, 50, $itemCurrencyId);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $productId = $get('product_id');
                                            if ($productId) {
                                                $product = Product::withoutGlobalScope('product_cabang')->find($productId);
                                                if ($product) {
                                                    $itemCurrencyId = is_numeric($get('currency_id'))
                                                        ? (int) $get('currency_id')
                                                        : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);
                                                    $supplierProduct = $product->suppliers()->where('suppliers.id', $state)->first();
                                                    if ($supplierProduct && $supplierProduct->pivot->supplier_price !== null) {
                                                        $unitPrice = self::convertIdrToCurrency((float) $supplierProduct->pivot->supplier_price, $itemCurrencyId);
                                                    } else {
                                                        $unitPrice = self::convertIdrToCurrency((float) ($product->cost_price ?? 0), $itemCurrencyId);
                                                    }

                                                    $set('original_price', $unitPrice);
                                                    $set('unit_price', $unitPrice);
                                                    // Recalculate subtotal
                                                    $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                    $taxType  = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                    $quantity = (float) ($get('quantity') ?? 0);
                                                    $discPct  = (float) ($get('discount') ?? 0);
                                                    $taxPct   = (float) ($get('tax') ?? 0);
                                                    $preview = self::calculateApprovalItemPreview($quantity, $unitPrice, $discPct, $taxPct, $taxType);
                                                    $set('total_cost', number_format((float)$preview['total_cost'], 0, ',', '.'));
                                                    $set('subtotal', number_format((float)$preview['subtotal'], 0, ',', '.'));
                                                    $set('tax_nominal', number_format((float)$preview['tax_nominal'], 0, ',', '.'));
                                                }
                                            }
                                        }
                                    })
                                    ->helperText('Pilih supplier untuk item ini (opsional, akan memperbarui harga)'),
                                Select::make('currency_id')
                                    ->label('Mata Uang Item')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $old, callable $set, callable $get) {
                                        $newCurrencyId = is_numeric($state)
                                            ? (int) $state
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);
                                        $oldCurrencyId = is_numeric($old)
                                            ? (int) $old
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);

                                        if ($newCurrencyId === $oldCurrencyId) {
                                            return;
                                        }

                                        $oldRateToIdr = self::resolveCurrencyRateToRupiah($oldCurrencyId);
                                        $newRateToIdr = self::resolveCurrencyRateToRupiah($newCurrencyId);

                                        $currentOriginalPrice = (float) \App\Helpers\MoneyHelper::parse($get('original_price') ?? 0);
                                        $currentUnitPrice = (float) \App\Helpers\MoneyHelper::parse($get('unit_price') ?? 0);

                                        $convertedOriginalPrice = ($currentOriginalPrice * $oldRateToIdr) / $newRateToIdr;
                                        $convertedUnitPrice = ($currentUnitPrice * $oldRateToIdr) / $newRateToIdr;

                                        $set('original_price', round($convertedOriginalPrice, 2));
                                        $set('unit_price', round($convertedUnitPrice, 2));

                                        $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                        $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                        $quantity = (float) ($get('quantity') ?? 0);
                                        $discPct = (float) ($get('discount') ?? 0);
                                        $taxPct = (float) ($get('tax') ?? 0);
                                        $preview = self::calculateApprovalItemPreview($quantity, $convertedUnitPrice, $discPct, $taxPct, $taxType);

                                        $set('total', number_format((float)$preview['total_cost'], 0, ',', '.'));
                                        $set('total_cost', number_format((float)$preview['total_cost'], 0, ',', '.'));
                                        $set('subtotal', number_format((float)$preview['subtotal'], 0, ',', '.'));
                                        $set('tax_nominal', number_format((float)$preview['tax_nominal'], 0, ',', '.'));
                                    })
                                    ->options(function () {
                                        return Currency::orderBy('name')
                                            ->get()
                                            ->mapWithKeys(function (Currency $c) {
                                                return [$c->id => "{$c->name} ({$c->symbol})"];
                                            });
                                    })
                                    ->default(fn(Get $get) => $get('../../currency_id') ?? Currency::where('code', 'IDR')->first()?->id)
                                    ->helperText('Inheritance dari OR header currency')
                                    ->validationMessages([
                                        'required' => 'Mata uang item wajib dipilih',
                                    ]),
                                Select::make('cabang_id')
                                    ->label('Cabang Item')
                                    ->options(function (callable $get) {
                                        $user = Auth::user();
                                        $manageType = $user?->manage_type ?? [];
                                        $productId = $get('product_id');
                                        $selectedProductCabangId = $productId
                                            ? Product::withoutGlobalScope('product_cabang')->find($productId)?->cabang_id
                                            : null;

                                        if ($user && is_array($manageType) && in_array('all', $manageType)) {
                                            $cabangs = \App\Models\Cabang::orderBy('kode')->limit(50)->get();

                                            if ($selectedProductCabangId && ! $cabangs->contains('id', $selectedProductCabangId)) {
                                                $selectedCabang = \App\Models\Cabang::find($selectedProductCabangId);
                                                if ($selectedCabang) {
                                                    $cabangs->push($selectedCabang);
                                                }
                                            }

                                            return $cabangs->unique('id')->mapWithKeys(function ($cabang) {
                                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                            });
                                        }

                                        $cabangIds = array_filter([
                                            $user?->cabang_id,
                                            $selectedProductCabangId,
                                        ]);

                                        return \App\Models\Cabang::whereIn('id', $cabangIds)->limit(50)->get()->mapWithKeys(function ($cabang) {
                                            return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                        });
                                    })
                                    ->default(fn() => null)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Cabang per item dipakai untuk memecah Purchase Order bila supplier sama tetapi cabang berbeda.')
                                    ->validationMessages([
                                        'required' => 'Cabang item wajib dipilih.',
                                    ]),
                                Placeholder::make('supplier_recommendation')
                                    ->label('Rekomendasi Supplier')
                                    ->content(function (callable $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return 'Pilih produk untuk melihat rekomendasi supplier.';
                                        }

                                        $product = Product::with('suppliers')->find($productId);
                                        if (!$product || $product->suppliers->isEmpty()) {
                                            return 'Tidak ada supplier terdaftar untuk produk ini.';
                                        }

                                        $recommended = $product->suppliers
                                            ->sortBy(fn($supplier) => (float) ($supplier->pivot->supplier_price ?? PHP_FLOAT_MAX))
                                            ->first();

                                        $price = (float) ($recommended?->pivot->supplier_price ?? 0);
                                        $itemCurrencyId = is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);

                                        $converted = self::convertIdrToCurrency($price, $itemCurrencyId);

                                        return "{$recommended->perusahaan} (" . self::formatMoneyByCurrency($itemCurrencyId, $converted) . ')';
                                    }),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                        $taxType   = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                        $quantity  = (float) ($state ?? 0);
                                        $unitPrice = \App\Helpers\MoneyHelper::parse($get('unit_price') ?? 0);
                                        $discPct   = (float) ($get('discount') ?? 0);
                                        $taxPct    = (float) ($get('tax') ?? 0);
                                        $base      = $quantity * $unitPrice;
                                        $afterDisc = $base - $base * ($discPct / 100);
                                        $subtotal  = $taxType === 'PPN Included'
                                            ? $afterDisc
                                            : $afterDisc + $afterDisc * ($taxPct / 100);
                                        $set('subtotal', number_format((float)$subtotal, 0, ',', '.'));
                                        try {
                                            $taxRes = \App\Services\TaxService::compute($afterDisc, $taxPct, $taxType);
                                            $set('total', number_format($quantity * $unitPrice, 0, ',', '.'));
                                            $set('tax_nominal', number_format((float)$taxRes['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                        }
                                    })
                                    ->required()
                                    ->minValue(0.01)
                                    ->validationMessages([
                                        'required' => 'Quantity wajib diisi.',
                                        'numeric' => 'Quantity harus berupa angka.',
                                        'min' => 'Quantity minimal 0.01.',
                                    ]),
                                TextInput::make('original_price')
                                    ->label('Harga Asli (Master)')
                                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                        is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                    ))
                                    ->default(0)
                                    ->readOnly()
                                    ->dehydrated()
                                    ->dehydrateStateUsing(fn($state) => \App\Helpers\MoneyHelper::parse($state ?? 0))
                                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? number_format(\App\Helpers\MoneyHelper::parse($state), 2, ',', '.') : '')
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record && $record->original_price !== null) {
                                            $formatted = number_format(\App\Helpers\MoneyHelper::parse($record->original_price), 2, ',', '.');
                                            $component->state($formatted);
                                        }
                                    })
                                    ->helperText('Harga dari master produk'),
                                TextInput::make('unit_price')
                                    ->label('Harga Override')
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                        is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                    ))
                                    ->dehydrateStateUsing(fn($state) => \App\Helpers\MoneyHelper::parse($state ?? 0))
                                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? number_format(\App\Helpers\MoneyHelper::parse($state), 2, ',', '.') : '')
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record && $record->unit_price !== null) {
                                            $formatted = number_format(\App\Helpers\MoneyHelper::parse($record->unit_price), 2, ',', '.');
                                            $component->state($formatted);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                        $taxType   = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                        $quantity  = (float) ($get('quantity') ?? 0);
                                        $unitPrice = \App\Helpers\MoneyHelper::parse($state ?? 0);
                                        $discPct   = (float) ($get('discount') ?? 0);
                                        $taxPct    = (float) ($get('tax') ?? 0);
                                        $base      = $quantity * $unitPrice;
                                        $afterDisc = $base - $base * ($discPct / 100);
                                        $subtotal  = $taxType === 'PPN Included'
                                            ? $afterDisc
                                            : $afterDisc + $afterDisc * ($taxPct / 100);
                                        $set('subtotal', number_format((float)$subtotal, 0, ',', '.'));
                                        try {
                                            $taxRes = \App\Services\TaxService::compute($afterDisc, $taxPct, $taxType);
                                            $set('total', number_format($quantity * $unitPrice, 0, ',', '.'));
                                            $set('tax_nominal', number_format((float)$taxRes['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                        }
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Harga satuan wajib diisi.',
                                        'numeric' => 'Harga satuan harus berupa angka.',
                                    ]),
                                TextInput::make('discount')
                                    ->label('Discount (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                        $taxType   = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                        $quantity  = (float) ($get('quantity') ?? 0);
                                        $unitPrice = \App\Helpers\MoneyHelper::parse($get('unit_price') ?? 0);
                                        $discPct   = (float) ($state ?? 0);
                                        $taxPct    = (float) ($get('tax') ?? 0);
                                        $base      = $quantity * $unitPrice;
                                        $afterDisc = $base - $base * ($discPct / 100);
                                        $subtotal  = $taxType === 'PPN Included'
                                            ? $afterDisc
                                            : $afterDisc + $afterDisc * ($taxPct / 100);
                                        $set('subtotal', number_format((float)$subtotal, 0, ',', '.'));
                                        try {
                                            $taxRes = \App\Services\TaxService::compute($afterDisc, $taxPct, $taxType);
                                            $set('tax_nominal', number_format((float)$taxRes['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $set('tax_nominal', '0');
                                        }
                                    })
                                    ->validationMessages([
                                        'numeric' => 'Discount harus berupa angka.',
                                        'min' => 'Discount tidak boleh negatif.',
                                        'max' => 'Discount maksimal 100%.',
                                    ]),
                                Radio::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->inline()
                                    ->required()
                                    ->default('eklusif')
                                    ->options([
                                        'none' => 'Non Pajak',
                                        'inklusif' => 'Inklusif',
                                        'eklusif' => 'Eklusif',
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $itemTaxType = self::normalizeItemTaxType($state);
                                        $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                        $productId = is_numeric($get('product_id')) ? (int) $get('product_id') : null;
                                        $taxPct = self::resolveItemTaxRate($productId, $itemTaxType);

                                        $set('tipe_pajak', $itemTaxType);
                                        $set('tax', $taxPct);

                                        $quantity  = (float) ($get('quantity') ?? 0);
                                        $unitPrice = \App\Helpers\MoneyHelper::parse($get('unit_price') ?? 0);
                                        $discPct   = (float) ($get('discount') ?? 0);
                                        $preview = self::calculateApprovalItemPreview($quantity, $unitPrice, $discPct, $taxPct, $taxType);
                                        $set('total_cost', number_format((float)$preview['total_cost'], 0, ',', '.'));
                                        $set('subtotal', number_format((float)$preview['subtotal'], 0, ',', '.'));
                                        $set('tax_nominal', number_format((float)$preview['tax_nominal'], 0, ',', '.'));
                                    }),
                                TextInput::make('tax')
                                    ->label('Tax (%)')
                                    ->numeric()
                                    ->default(function (callable $get) {
                                        return self::resolveItemTaxRate(
                                            is_numeric($get('product_id')) ? (int) $get('product_id') : null,
                                            $get('tipe_pajak')
                                        );
                                    })
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->validationMessages([
                                        'numeric' => 'Tax harus berupa angka.',
                                        'min' => 'Tax tidak boleh negatif.',
                                        'max' => 'Tax maksimal 100%.',
                                    ]),
                                TextInput::make('total')
                                    ->label('Total (Harga × Qty)')
                                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                        is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                    ))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? number_format(\App\Helpers\MoneyHelper::parse($state), 0, ',', '.') : '')
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $unitPrice = \App\Helpers\MoneyHelper::parse($record->unit_price ?? 0);
                                            $total = (float)$record->quantity * $unitPrice;
                                            $component->state(number_format($total, 0, ',', '.'));
                                        }
                                    }),
                                TextInput::make('tax_nominal')
                                    ->label('Nominal Pajak')
                                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                        is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                    ))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if (! $record) {
                                            return;
                                        }
                                        $taxPct    = (float) ($record->tax ?? 0);
                                        $qty       = (float) ($record->quantity ?? 0);
                                        $unitPrice = \App\Helpers\MoneyHelper::parse($record->unit_price ?? 0);
                                        $discPct   = (float) ($record->discount ?? 0);
                                        $taxType   = \App\Models\OrderRequestItem::taxServiceTypeFromItemTaxType(
                                            $record->tipe_pajak ?? null
                                        );
                                        $base      = $qty * $unitPrice;
                                        $afterDisc = $base - $base * ($discPct / 100);
                                        try {
                                            $result = \App\Services\TaxService::compute($afterDisc, $taxPct, $taxType);
                                            $component->state(number_format((float) $result['ppn'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $component->state('0');
                                        }
                                    }),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->default(0)
                                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                        is_numeric($get('currency_id'))
                                            ? (int) $get('currency_id')
                                            : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                    ))
                                    ->disabled()
                                    ->validationMessages([
                                        'numeric' => 'Subtotal harus berupa angka.',
                                    ])
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if (! $record) {
                                            return;
                                        }
                                        $taxPct    = (float) ($record->tax ?? 0);
                                        $qty       = (float) ($record->quantity ?? 0);
                                        $unitPrice = \App\Helpers\MoneyHelper::parse($record->unit_price ?? 0);
                                        $discPct   = (float) ($record->discount ?? 0);
                                        $taxType   = \App\Models\OrderRequestItem::taxServiceTypeFromItemTaxType(
                                            $record->tipe_pajak ?? null
                                        );
                                        $base      = $qty * $unitPrice;
                                        $afterDisc = $base - $base * ($discPct / 100);
                                        try {
                                            $result = \App\Services\TaxService::compute($afterDisc, $taxPct, $taxType);
                                            $component->state(number_format((float) $result['total'], 0, ',', '.'));
                                        } catch (\Throwable $e) {
                                            $component->state('0');
                                        }
                                    }),
                                Textarea::make('note')
                                    ->nullable()
                                    ->label('Note')
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_number')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->where('request_number', 'like', "%{$search}%")
                                ->orWhereHas('orderRequestItem.product', function (Builder $itemQuery) use ($search) {
                                    $itemQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%");
                                })
                                ->orWhereHas('orderRequestItem.supplier', function (Builder $itemQuery) use ($search) {
                                    $itemQuery->where('perusahaan', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%");
                                });
                        });
                    }),
                TextColumn::make('request_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft'           => 'DRAFT',
                            'request_approve' => 'REQUEST APPROVE',
                            'approved'        => 'APPROVED',
                            'partial'         => 'PARTIAL',
                            'complete'        => 'COMPLETE',
                            'closed'          => 'CLOSED',
                            'rejected'        => 'REJECTED',
                            default           => Str::upper($state),
                        };
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft'           => 'gray',
                            'request_approve' => 'gray',
                            'approved'        => 'info',
                            'partial'         => 'warning',
                            'complete'        => 'success',
                            'closed'          => 'danger',
                            'rejected'        => 'danger',
                            default           => 'gray',
                        };
                    })
                    ->badge(),
                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->getStateUsing(function ($record) {
                        return $record->orderRequestItem;
                    })
                    ->formatStateUsing(function ($state) {
                        $supplierCode = $state->supplier->code ?? null;
                        $supplierName = $state->supplier->perusahaan ?? null;

                        return ($supplierCode && $supplierName)
                            ? "({$supplierCode}) {$supplierName}"
                            : ($supplierName ?? '-');
                    })
                    ->badge(),
                TextColumn::make('product')
                    ->label('Items')
                    ->getStateUsing(function ($record) {
                        return $record->orderRequestItem;
                    })
                    ->formatStateUsing(function ($state) {
                        $productSku = $state->product->sku ?? null;
                        $productName = $state->product->name ?? null;
                        return ($productSku && $productName)
                            ? "({$productSku}) {$productName}"
                            : ($productName ?? '-');
                    })
                    ->badge(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable(),
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
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Order Request</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Order Request adalah permintaan pembelian internal yang dapat di-approve menjadi Purchase Order.</li>' .
                    '<li><strong>Cara Approve:</strong> Gunakan tombol <em>Approve</em> pada baris request. Saat approve, Anda dapat memilih untuk membuat Purchase Order secara langsung.</li>' .
                    '<li><strong>Create PO:</strong> Tombol <em>Create Purchase Order</em> memungkinkan pembuatan PO manual dari request yang telah di-approve.</li>' .
                    '<li><strong>Dampak:</strong> Setelah disetujui, request berubah status menjadi <em>approved</em> dan siap diteruskan ke proses pembelian.</li>' .
                    '<li><strong>Catatan:</strong> Akses tombol approve/create PO bergantung pada hak akses pengguna.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft'           => 'Draft',
                        'request_approve' => 'Request Approve',
                        'approved'        => 'Approved',
                        'partial'         => 'Partial',
                        'complete'        => 'Complete',
                        'rejected'        => 'Rejected',
                        'closed'          => 'Closed',
                    ])
                    ->preload()
                    ->placeholder('All Statuses'),
                SelectFilter::make('supplier_id')
                    ->label('Supplier (per Item)')
                    ->options(function () {
                        return Supplier::select(['id', 'perusahaan', 'code'])->orderBy('perusahaan')->get()
                            ->mapWithKeys(fn($s) => [$s->id => "({$s->code}) {$s->perusahaan}"]);
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): void {
                        if (!empty($data['value'])) {
                            $query->whereHas('orderRequestItem', function ($q) use ($data) {
                                $q->where('supplier_id', $data['value']);
                            });
                        }
                    }),
                // header-level warehouse filter removed; system uses item-level grouping instead
                Filter::make('request_date')
                    ->form([
                        DatePicker::make('request_date_from')
                            ->label('Request Date From'),
                        DatePicker::make('request_date_until')
                            ->label('Request Date Until'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query->when(
                            $data['request_date_from'],
                            function ($query, $date) {
                                $query->whereDate('request_date', '>=', $date);
                            }
                        );
                        $query->when(
                            $data['request_date_until'],
                            function ($query, $date) {
                                $query->whereDate('request_date', '<=', $date);
                            }
                        );
                    }),
            ])
            ->recordClasses(fn($record) => match ($record->status) {
                'draft' => '',
                'request_approve' => 'bg-gray-100',
                'approved' => 'bg-blue-100',
                'partial' => 'bg-yellow-100',
                'complete' => 'bg-green-100',
                'closed' => 'bg-red-100',
                'rejected' => 'bg-red-100',
                default => '',
            })
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-document')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status == 'approved';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.order-request', [
                                'orderRequest' => $record
                            ])->setPaper('A4', 'portrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Order_Request_' . $record->request_number . '.pdf');
                        }),
                    Action::make('create_purchase_order')
                        ->label('Create Purchase Order')
                        ->color('primary')
                        ->icon('heroicon-o-plus')
                        ->modalWidth('6xl')
                        ->modalHeading('Buat Purchase Order')
                        ->modalDescription('Isi form berikut untuk membuat Purchase Order dari Order Request yang telah disetujui.')
                        ->fillForm(function ($record) {
                            $items = $record->orderRequestItem->map(function ($item) use ($record) {
                                $remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0);
                                // Use item-level supplier only
                                $supplierId = $item->supplier_id;
                                $cabangId = $item->cabang_id;
                                // Priority: item->unit_price (user override) > catalog supplier_price > cost_price
                                $itemUnitPrice = (float)($item->unit_price ?? 0);
                                if ($itemUnitPrice > 0) {
                                    $supplierPrice = $itemUnitPrice;
                                } elseif ($supplierId && $item->product) {
                                    $sp = $item->product->suppliers()->where('suppliers.id', $supplierId)->first();
                                    $supplierPrice = ($sp && $sp->pivot->supplier_price > 0) ? (float)$sp->pivot->supplier_price : (float)($item->product->cost_price ?? 0);
                                } else {
                                    $supplierPrice = (float)($item->product->cost_price ?? 0);
                                }
                                $taxPct = (float)($item->tax ?? 0);
                                try {
                                    $taxRes = \App\Services\TaxService::compute(
                                        max(0, $remainingQty) * $supplierPrice,
                                        $taxPct,
                                        self::taxServiceTypeFromItemTaxType($item->tipe_pajak ?? null)
                                    );
                                    $taxNom = number_format($taxRes['ppn'], 0, ',', '.');
                                    $subtotal = number_format($taxRes['total'], 0, ',', '.');
                                } catch (\Throwable $e) {
                                    $taxNom = '0';
                                    $subtotal = '0';
                                }

                                $supplierName = $supplierId
                                    ? (function () use ($supplierId) {
                                        $s = \App\Models\Supplier::find($supplierId);
                                        return $s ? "({$s->code}) {$s->perusahaan}" : '-';
                                    })()
                                    : '-';
                                $cabangName = $cabangId
                                    ? (function () use ($cabangId) {
                                        $c = \App\Models\Cabang::find($cabangId);
                                        return $c ? "({$c->kode}) {$c->nama}" : '-';
                                    })()
                                    : '-';
                                $uom = $item->product->uom->abbreviation ?? $item->product->uom->name ?? '-';

                                return [
                                    'item_id'          => $item->id,
                                    'item_supplier_id' => $supplierId,
                                    'item_cabang_id'   => $cabangId,
                                    'currency_id'      => $item->currency_id ?? $record->currency_id,
                                    'product_name'     => "({$item->product->sku}) {$item->product->name}",
                                    'supplier_name'    => $supplierName,
                                    'cabang_name'      => $cabangName,
                                    'uom'              => $uom,
                                    'quantity'         => max(0, $remainingQty),
                                    'unit_price'       => $supplierPrice,
                                    'tax'              => $taxPct,
                                    'tax_nominal'      => $taxNom,
                                    'subtotal'         => $subtotal,
                                    'max_quantity'     => max(0, $remainingQty),
                                    'include'          => $remainingQty > 0,
                                ];
                            })->values()->toArray();

                            $groups = collect($items)
                                ->map(fn($item) => implode('|', [
                                    (string) ($item['item_supplier_id'] ?? ''),
                                    (string) ($item['item_cabang_id'] ?? ''),
                                ]))
                                ->filter(fn($key) => trim($key, '|') !== '')
                                ->unique();

                            // Pre-fill supplier from first item that has one
                            $firstSupplierId = $record->orderRequestItem->firstWhere('supplier_id', '!=', null)?->supplier_id;
                            $firstCabangId = $items[0]['item_cabang_id'] ?? null;

                            return [
                                'supplier_id'           => $groups->count() === 1 ? ($firstSupplierId ?? null) : null,
                                'cabang_id'             => $groups->count() === 1 ? $firstCabangId : null,
                                'create_purchase_order' => true,
                                'multi_supplier'        => $groups->count() > 1,
                                'tax_type'              => self::normalizeTaxTypeValue($record->tax_type ?? null),
                                'selected_items'        => $items,
                            ];
                        })
                        ->form([
                            Section::make('Informasi Purchase Order')
                                ->icon('heroicon-o-document-text')
                                ->columns(2)
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->schema([
                                    Hidden::make('tax_type'),
                                    // header-level cabang removed; per-item cabang used instead
                                    Select::make('supplier_id')
                                        ->label('Supplier (untuk PO)')
                                        ->helperText('Supplier utama untuk Purchase Order. Setiap item memiliki supplier masing-masing (lihat di tabel item).')
                                        ->searchable()
                                        ->columnSpanFull()
                                        ->options(function () {
                                            return Supplier::select(['id', 'perusahaan', 'code'])->get()->mapWithKeys(function ($supplier) {
                                                return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                            });
                                        })
                                        ->getSearchResultsUsing(function (string $search) {
                                            return Supplier::where('perusahaan', 'like', "%{$search}%")
                                                ->orWhere('code', 'like', "%{$search}%")
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(function ($supplier) {
                                                    return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                                });
                                        })
                                        ->required(fn(Get $get) => $get('create_purchase_order'))
                                        ->validationMessages([
                                            'required' => 'Supplier wajib dipilih.',
                                        ]),
                                    TextInput::make('po_number')
                                        ->label('PO Number')
                                        ->string()
                                        ->maxLength(255)
                                        ->required(fn(Get $get) => $get('create_purchase_order'))
                                        ->suffixAction(
                                            FormAction::make('generatePoNumber')
                                                ->icon('heroicon-o-arrow-path')
                                                ->tooltip('Generate PO Number')
                                                ->action(function ($set) {
                                                    $set('po_number', HelperController::generatePoNumber());
                                                })
                                        )
                                        ->validationMessages([
                                            'required' => 'Nomor PO wajib diisi.',
                                            'max' => 'Nomor PO maksimal 255 karakter.',
                                        ]),
                                    DatePicker::make('order_date')
                                        ->label('Order Date')
                                        ->required(fn(Get $get) => $get('create_purchase_order'))
                                        ->native(false)
                                        ->displayFormat('d M Y')
                                        ->validationMessages([
                                            'required' => 'Tanggal order wajib diisi.',
                                        ]),
                                    DatePicker::make('expected_date')
                                        ->label('Expected Delivery Date')
                                        ->native(false)
                                        ->displayFormat('d M Y')
                                        ->nullable(),
                                    Textarea::make('note')
                                        ->label('Catatan')
                                        ->placeholder('Catatan tambahan untuk Purchase Order ini...')
                                        ->rows(3)
                                        ->columnSpanFull()
                                        ->nullable(),
                                ]),
                            Section::make('Pilih Item yang Akan Dibeli')
                                ->description('Centang item yang akan dimasukkan ke dalam Purchase Order. Anda dapat mengubah quantity dan harga sebelum menyetujui.')
                                ->icon('heroicon-o-shopping-cart')
                                ->collapsible()
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->schema([
                                    Repeater::make('selected_items')
                                        ->label('')
                                        ->columns(4)
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->schema([
                                            Hidden::make('item_id'),
                                            Hidden::make('max_quantity'),
                                            Hidden::make('item_supplier_id'),
                                            Hidden::make('item_cabang_id'),
                                            Hidden::make('currency_id'),
                                            TextInput::make('product_name')
                                                ->label('Nama Produk')
                                                ->readOnly(),
                                            TextInput::make('supplier_name')
                                                ->label('Supplier')
                                                ->readOnly(),
                                            TextInput::make('cabang_name')
                                                ->label('Cabang')
                                                ->readOnly(),
                                            TextInput::make('uom')
                                                ->label('Satuan')
                                                ->readOnly(),
                                            TextInput::make('quantity')
                                                ->label('Qty')
                                                ->numeric()
                                                ->minValue(0)
                                                ->reactive()
                                                ->live()
                                                ->required()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $taxType = self::normalizeTaxTypeValue($get('../../tax_type') ?? null);
                                                    $preview = self::calculateApprovalItemPreview(
                                                        (float) ($state ?? 0),
                                                        (float) \App\Helpers\MoneyHelper::parse($get('unit_price') ?? 0),
                                                        (float) ($get('discount') ?? 0),
                                                        (float) ($get('tax') ?? 0),
                                                        $taxType
                                                    );

                                                    $set('total_cost', $preview['total_cost']);
                                                    $set('subtotal', $preview['subtotal']);
                                                    $set('tax_nominal', $preview['tax_nominal']);
                                                })
                                                ->helperText(fn($get) => 'Maks qty: ' . ($get('max_quantity') ?? '-'))
                                                ->rules([
                                                    fn($get) => function ($attribute, $value, $fail) use ($get) {
                                                        $max = $get('max_quantity');
                                                        if ($max !== null && $max !== '' && (float) $value > (float) $max) {
                                                            $fail("Qty tidak boleh melebihi {$max}.");
                                                        }
                                                    },
                                                ])
                                                ->validationMessages([
                                                    'required' => 'Qty wajib diisi.',
                                                    'numeric' => 'Qty harus berupa angka.',
                                                    'min' => 'Qty minimal 0.',
                                                ]),
                                            TextInput::make('unit_price')
                                                ->label('Harga Satuan')
                                                ->required()
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 2)
        JS))
                                                ->formatStateUsing(function ($state) {
                                                    if ($state === null || $state === '') {
                                                        return '';
                                                    }

                                                    return number_format(\App\Helpers\MoneyHelper::parse($state), 2, ',', '.');
                                                })
                                                ->dehydrateStateUsing(function ($state) {
                                                    if ($state === null || $state === '') {
                                                        return null;
                                                    }

                                                    return \App\Helpers\MoneyHelper::parse($state);
                                                })
                                                ->reactive()
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $taxType = self::normalizeTaxTypeValue($get('../../tax_type') ?? null);
                                                    $preview = self::calculateApprovalItemPreview(
                                                        (float) ($get('quantity') ?? 0),
                                                        (float) \App\Helpers\MoneyHelper::parse($state ?? 0),
                                                        (float) ($get('discount') ?? 0),
                                                        (float) ($get('tax') ?? 0),
                                                        $taxType
                                                    );

                                                    $set('total_cost', number_format((float)$preview['total_cost'], 2, ',', '.'));
                                                    $set('subtotal', number_format((float)$preview['subtotal'], 2, ',', '.'));
                                                    $set('tax_nominal', number_format((float)$preview['tax_nominal'], 2, ',', '.'));
                                                })
                                                ->rules([
                                                    'required',
                                                    'regex:/^[0-9\.,]+$/',
                                                ])
                                                ->validationMessages([
                                                    'required' => 'Harga satuan wajib diisi.',
                                                    'regex' => 'Harga satuan harus berupa angka (contoh: 12.000.000).',
                                                ]),
                                            TextInput::make('tax')
                                                ->label('Pajak (%)')
                                                ->numeric()
                                                ->readOnly()
                                                ->suffix('%'),
                                            TextInput::make('tax_nominal')
                                                ->label('Nominal Pajak')
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->readOnly(),
                                            TextInput::make('subtotal')
                                                ->label('Subtotal')
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->readOnly(),
                                            Checkbox::make('include')
                                                ->label('Sertakan')
                                                ->default(true),
                                        ]),
                                ]),
                        ])
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            if (!$user || !$user->hasPermissionTo('approve order request') || $record->status !== 'approved') {
                                return false;
                            }
                            // Allow creating a new PO as long as some items still have unfulfilled quantity
                            return $record->orderRequestItem->contains(
                                fn($item) => ($item->quantity - ($item->fulfilled_quantity ?? 0)) > 0
                            );
                        })
                        ->action(function (array $data, $record) {
                            try {
                                $orderRequestService = app(OrderRequestService::class);

                                $includedItems = collect($data['selected_items'] ?? [])->filter(fn($i) => $i['include'] ?? false);
                                $groups = $includedItems->groupBy(function ($item) {
                                    return implode('|', [
                                        (string) ($item['item_supplier_id'] ?? ''),
                                        (string) ($item['item_cabang_id'] ?? ''),
                                    ]);
                                });

                                if (! empty($data['multi_supplier']) || $groups->count() > 1) {
                                    // Multi-group mode: group included items by supplier + cabang and create one PO each
                                    $includedItems = collect($data['selected_items'])->filter(fn($i) => $i['include'] ?? false);
                                    if ($includedItems->isEmpty()) {
                                        HelperController::sendNotification(isSuccess: false, title: 'Perhatian', message: 'Pilih minimal satu item.');
                                        return;
                                    }
                                    $created = 0;
                                    foreach ($groups as $groupItems) {
                                        $firstItem = $groupItems->first();
                                        $supplierId = $firstItem['item_supplier_id'] ?? null;
                                        $cabangId = $firstItem['item_cabang_id'] ?? null;

                                        if (empty($supplierId) || empty($cabangId)) {
                                            continue;
                                        }

                                        $poNumber = HelperController::generatePoNumber();
                                        // Make sure it's unique
                                        while (PurchaseOrder::where('po_number', $poNumber)->exists()) {
                                            $poNumber = HelperController::generatePoNumber();
                                        }
                                        $poData = array_merge($data, [
                                            'supplier_id'    => $supplierId,
                                            'cabang_id'      => $cabangId,
                                            'po_number'      => $poNumber,
                                            'selected_items' => $groupItems->values()->toArray(),
                                            'multi_supplier' => false,
                                        ]);
                                        $orderRequestService->createPurchaseOrder($record, $poData);
                                        $created++;
                                    }
                                    HelperController::sendNotification(isSuccess: true, title: 'Berhasil', message: "{$created} Purchase Order berhasil dibuat. Proses selanjutnya: Persetujuan Purchase Order oleh Manajer Purchasing.");
                                } else {
                                    // Single supplier mode (existing behaviour)
                                    $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                                    if ($purchaseOrder) {
                                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                        return;
                                    }
                                    $orderRequestService->createPurchaseOrder($record, $data);
                                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order berhasil dibuat. Proses selanjutnya: Persetujuan Purchase Order oleh Manajer Purchasing.");
                                }
                            } catch (Throwable $exception) {
                                ProcurementFailureNotifier::danger(
                                    'Gagal Memproses Order Request',
                                    $exception,
                                    'Order request belum dapat diproses. Periksa data yang dipilih lalu coba lagi.'
                                );
                            }
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->modalWidth('6xl')
                        ->modalHeading('Approve Order Request')
                        ->modalDescription('Tinjau dan setujui Order Request ini. Anda dapat memilih item yang akan dibuatkan Purchase Order.')
                        ->modalSubmitActionLabel('Approve')
                        ->fillForm(function ($record) {
                            $items = $record->orderRequestItem->map(function ($item) use ($record) {
                                $remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0);
                                // Use item-level supplier only (no OR-level fallback)
                                $supplierId = $item->supplier_id;
                                $cabangId = $item->cabang_id;
                                // Priority: item->unit_price (user override) > catalog supplier_price > cost_price
                                $itemUnitPrice = (float)($item->unit_price ?? 0);
                                if ($itemUnitPrice > 0) {
                                    $supplierPrice = $itemUnitPrice;
                                } elseif ($supplierId && $item->product) {
                                    $sp = $item->product->suppliers()->where('suppliers.id', $supplierId)->first();
                                    $supplierPrice = ($sp && $sp->pivot->supplier_price > 0) ? (float)$sp->pivot->supplier_price : (float)($item->product->cost_price ?? 0);
                                } else {
                                    $supplierPrice = (float)($item->product->cost_price ?? 0);
                                }
                                $taxPct = (float)($item->tax ?? 0);
                                try {
                                    $taxRes = \App\Services\TaxService::compute(
                                        max(0, $remainingQty) * $supplierPrice,
                                        $taxPct,
                                        self::taxServiceTypeFromItemTaxType($item->tipe_pajak ?? null)
                                    );
                                    $taxNom = number_format($taxRes['ppn'], 0, ',', '.');
                                    $subtotal = number_format($taxRes['total'], 0, ',', '.');
                                } catch (\Throwable $e) {
                                    $taxNom = '0';
                                    $subtotal = '0';
                                }

                                $supplierName = $supplierId
                                    ? (function () use ($supplierId) {
                                        $s = \App\Models\Supplier::find($supplierId);
                                        return $s ? "({$s->code}) {$s->perusahaan}" : '-';
                                    })()
                                    : '-';
                                $cabangName = $cabangId
                                    ? (function () use ($cabangId) {
                                        $c = \App\Models\Cabang::find($cabangId);
                                        return $c ? "({$c->kode}) {$c->nama}" : '-';
                                    })()
                                    : '-';
                                $uom = $item->product->uom->abbreviation ?? $item->product->uom->name ?? '-';

                                return [
                                    'item_id'          => $item->id,
                                    'item_supplier_id' => $supplierId,
                                    'item_cabang_id'   => $cabangId,
                                    'currency_id'      => $item->currency_id ?? $record->currency_id,
                                    'product_name'     => "({$item->product->sku}) {$item->product->name}",
                                    'supplier_name'    => $supplierName,
                                    'cabang_name'      => $cabangName,
                                    'uom'              => $uom,
                                    'quantity'         => max(0, $remainingQty),
                                    'original_price'   => number_format((float)($item->original_price ?? $supplierPrice), 0, ',', '.'),
                                    'unit_price'       => $supplierPrice,
                                    'tax'              => $taxPct,
                                    'tax_nominal'      => $taxNom,
                                    'total_cost'       => number_format(max(0, $remainingQty) * $supplierPrice, 0, ',', '.'),
                                    'subtotal'         => $subtotal,
                                    'max_quantity'     => max(0, $remainingQty),
                                    'include'          => $remainingQty > 0,
                                ];
                            })->values()->toArray();

                            $groups = collect($items)
                                ->map(fn($item) => implode('|', [
                                    (string) ($item['item_supplier_id'] ?? ''),
                                    (string) ($item['item_cabang_id'] ?? ''),
                                ]))
                                ->filter(fn($key) => trim($key, '|') !== '')
                                ->unique();

                            // Detect multi-supplier: items have different item_supplier_id values
                            $isMultiSupplier = $groups->count() > 1;
                            $firstCabangId = $items[0]['item_cabang_id'] ?? null;

                            return [
                                'supplier_id'           => $isMultiSupplier ? null : ($items[0]['item_supplier_id'] ?? null),
                                'cabang_id'             => $isMultiSupplier ? null : $firstCabangId,
                                'create_purchase_order' => true,
                                'multi_supplier'        => $isMultiSupplier,
                                'tax_type'              => self::normalizeTaxTypeValue($record->tax_type ?? null),
                                'selected_items'        => $items,
                            ];
                        })
                        ->form([
                            Section::make('Opsi Persetujuan')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([
                                    Hidden::make('tax_type'),
                                    // header-level cabang removed; per-item cabang used instead
                                    \Filament\Forms\Components\Toggle::make('create_purchase_order')
                                        ->label('Buat Purchase Order secara otomatis?')
                                        ->helperText('Aktifkan untuk langsung membuat PO setelah approval.')
                                        ->default(true)
                                        ->live()
                                        ->columnSpanFull(),
                                    \Filament\Forms\Components\Placeholder::make('multi_supplier_notice')
                                        ->label('')
                                        ->content('Item dalam OR ini memiliki beberapa kombinasi supplier dan cabang berbeda. Sistem akan membuat satu PO per kombinasi secara otomatis.')
                                        ->visible(fn(Get $get) => $get('create_purchase_order') && $get('multi_supplier'))
                                        ->columnSpanFull(),
                                    Hidden::make('multi_supplier'),
                                ]),
                            Section::make('Informasi Purchase Order')
                                ->icon('heroicon-o-document-text')
                                ->columns(2)
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->schema([
                                    Select::make('supplier_id')
                                        ->label('Supplier')
                                        ->searchable()
                                        ->columnSpanFull()
                                        ->options(function () {
                                            return Supplier::select(['id', 'perusahaan', 'code'])->get()->mapWithKeys(function ($supplier) {
                                                return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                            });
                                        })
                                        ->getSearchResultsUsing(function (string $search) {
                                            return Supplier::where('perusahaan', 'like', "%{$search}%")
                                                ->orWhere('code', 'like', "%{$search}%")
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(function ($supplier) {
                                                    return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                                });
                                        })
                                        ->visible(fn(Get $get) => !$get('multi_supplier'))
                                        ->required(fn(Get $get) => $get('create_purchase_order') && !$get('multi_supplier'))
                                        ->validationMessages([
                                            'required' => 'Supplier wajib dipilih.',
                                        ]),
                                    TextInput::make('po_number')
                                        ->label('PO Number')
                                        ->string()
                                        ->maxLength(255)
                                        ->visible(fn(Get $get) => !$get('multi_supplier'))
                                        ->required(fn(Get $get) => $get('create_purchase_order') && !$get('multi_supplier'))
                                        ->suffixAction(
                                            FormAction::make('generatePoNumber')
                                                ->icon('heroicon-o-arrow-path')
                                                ->tooltip('Generate PO Number')
                                                ->action(function ($set) {
                                                    $set('po_number', HelperController::generatePoNumber());
                                                })
                                        )
                                        ->validationMessages([
                                            'required' => 'Nomor PO wajib diisi.',
                                            'max' => 'Nomor PO maksimal 255 karakter.',
                                        ]),
                                    DatePicker::make('order_date')
                                        ->label('Order Date')
                                        ->required(fn(Get $get) => $get('create_purchase_order'))
                                        ->native(false)
                                        ->displayFormat('d M Y')
                                        ->validationMessages([
                                            'required' => 'Tanggal order wajib diisi.',
                                        ]),
                                    DatePicker::make('expected_date')
                                        ->label('Expected Delivery Date')
                                        ->native(false)
                                        ->displayFormat('d M Y')
                                        ->nullable(),
                                    Textarea::make('note')
                                        ->label('Catatan')
                                        ->placeholder('Catatan tambahan untuk Purchase Order ini...')
                                        ->rows(3)
                                        ->columnSpanFull()
                                        ->nullable(),
                                ]),
                            Section::make('Pilih Item yang Akan Dibeli')
                                ->description('Centang item yang akan dimasukkan ke dalam Purchase Order. Anda dapat mengubah quantity dan harga sebelum menyetujui.')
                                ->icon('heroicon-o-shopping-cart')
                                ->collapsible()
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->schema([
                                    Repeater::make('selected_items')
                                        ->label('')
                                        ->columns(4)
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->schema([
                                            Hidden::make('item_id'),
                                            Hidden::make('max_quantity'),
                                            Hidden::make('item_supplier_id'),
                                            Hidden::make('item_cabang_id'),
                                            Hidden::make('currency_id'),
                                            TextInput::make('product_name')
                                                ->label('Nama Produk')
                                                ->readOnly(),
                                            TextInput::make('supplier_name')
                                                ->label('Supplier')
                                                ->readOnly(),
                                            TextInput::make('cabang_name')
                                                ->label('Cabang')
                                                ->readOnly(),
                                            TextInput::make('uom')
                                                ->label('Satuan')
                                                ->readOnly(),
                                            TextInput::make('quantity')
                                                ->label('Qty')
                                                ->numeric()
                                                ->minValue(0)
                                                ->reactive()
                                                ->live()
                                                ->required()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $taxType = self::normalizeTaxTypeValue($get('../../tax_type') ?? null);
                                                    $preview = self::calculateApprovalItemPreview(
                                                        (float) ($state ?? 0),
                                                        (float) \App\Helpers\MoneyHelper::parse($get('unit_price') ?? 0),
                                                        (float) ($get('discount') ?? 0),
                                                        (float) ($get('tax') ?? 0),
                                                        $taxType
                                                    );

                                                    $set('total_cost', $preview['total_cost']);
                                                    $set('subtotal', $preview['subtotal']);
                                                    $set('tax_nominal', $preview['tax_nominal']);
                                                })
                                                ->helperText(fn($get) => 'Maks qty: ' . ($get('max_quantity') ?? '-'))
                                                ->rules([
                                                    fn($get) => function ($attribute, $value, $fail) use ($get) {
                                                        $max = $get('max_quantity');
                                                        if ($max !== null && $max !== '' && (float) $value > (float) $max) {
                                                            $fail("Qty tidak boleh melebihi {$max}.");
                                                        }
                                                    },
                                                ])
                                                ->validationMessages([
                                                    'required' => 'Qty wajib diisi.',
                                                    'numeric' => 'Qty harus berupa angka.',
                                                    'min' => 'Qty minimal 0.',
                                                ]),
                                            TextInput::make('original_price')
                                                ->label('Original Price')
                                                ->required()
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 2)
        JS))
                                                ->formatStateUsing(function ($state) {
                                                    if ($state === null || $state === '') {
                                                        return '';
                                                    }

                                                    return number_format(\App\Helpers\MoneyHelper::parse($state), 2, ',', '.');
                                                })
                                                ->dehydrateStateUsing(function ($state) {
                                                    if ($state === null || $state === '') {
                                                        return null;
                                                    }

                                                    return \App\Helpers\MoneyHelper::parse($state);
                                                })
                                                ->rules([
                                                    'required',
                                                    'regex:/^[0-9\.,]+$/',
                                                ])
                                                ->validationMessages([
                                                    'required' => 'Harga asli wajib diisi.',
                                                    'regex' => 'Harga asli harus berupa angka (contoh: 12.000.000).',
                                                ]),
                                            TextInput::make('unit_price')
                                                ->label('Harga Satuan')
                                                ->required()
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 2)
        JS))
                                                ->formatStateUsing(function ($state) {
                                                    if ($state === null || $state === '') {
                                                        return '';
                                                    }

                                                    return number_format(\App\Helpers\MoneyHelper::parse($state), 2, ',', '.');
                                                })
                                                ->dehydrateStateUsing(function ($state) {
                                                    if ($state === null || $state === '') {
                                                        return null;
                                                    }

                                                    return \App\Helpers\MoneyHelper::parse($state);
                                                })
                                                ->reactive()
                                                ->live()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $taxType = self::normalizeTaxTypeValue($get('../../tax_type') ?? null);
                                                    $preview = self::calculateApprovalItemPreview(
                                                        (float) ($get('quantity') ?? 0),
                                                        (float) \App\Helpers\MoneyHelper::parse($state ?? 0),
                                                        (float) ($get('discount') ?? 0),
                                                        (float) ($get('tax') ?? 0),
                                                        $taxType
                                                    );

                                                    $set('total_cost', number_format((float)$preview['total_cost'], 0, ',', '.'));
                                                    $set('subtotal', number_format((float)$preview['subtotal'], 0, ',', '.'));
                                                    $set('tax_nominal', number_format((float)$preview['tax_nominal'], 0, ',', '.'));
                                                })
                                                ->rules([
                                                    'required',
                                                    'regex:/^[0-9\.,]+$/',
                                                ])
                                                ->validationMessages([
                                                    'required' => 'Harga satuan wajib diisi.',
                                                    'regex' => 'Harga satuan harus berupa angka (contoh: 12.000.000).',
                                                ]),
                                            TextInput::make('tax')
                                                ->label('Pajak (%)')
                                                ->numeric()
                                                ->readOnly()
                                                ->suffix('%'),
                                            TextInput::make('tax_nominal')
                                                ->label('Nominal Pajak')
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->readOnly(),
                                            TextInput::make('total_cost')
                                                ->label('Total (Harga × Qty)')
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->rules([
                                                    'regex:/^[0-9\.,]+$/',
                                                ])
                                                ->validationMessages([
                                                    'regex' => 'Total harus berupa angka (contoh: 12.000.000).',
                                                ])
                                                ->readOnly(),
                                            TextInput::make('subtotal')
                                                ->label('Subtotal')
                                                ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                                                ->readOnly(),
                                            Checkbox::make('include')
                                                ->label('Sertakan')
                                                ->default(true),
                                        ]),
                                ]),
                        ])
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user && $user->hasPermissionTo('approve order request') && $record->status == 'request_approve';
                        })
                        ->action(function (array $data, $record) {
                            $orderRequestService = app(OrderRequestService::class);

                            if ($data['create_purchase_order']) {
                                $includedItems = collect($data['selected_items'] ?? [])->filter(fn($i) => $i['include'] ?? false);
                                $groups = $includedItems->groupBy(function ($item) {
                                    return implode('|', [
                                        (string) ($item['item_supplier_id'] ?? ''),
                                        (string) ($item['item_cabang_id'] ?? ''),
                                    ]);
                                });

                                if (!empty($data['multi_supplier']) || $groups->count() > 1) {
                                    // Multi-group: group items by supplier + cabang and create one PO each
                                    $includedItems = collect($data['selected_items'])->filter(fn($i) => $i['include'] ?? false);
                                    if ($includedItems->isEmpty()) {
                                        HelperController::sendNotification(isSuccess: false, title: 'Perhatian', message: 'Pilih minimal satu item.');
                                        return;
                                    }
                                    $created = 0;
                                    foreach ($groups as $groupItems) {
                                        $firstItem = $groupItems->first();
                                        $supplierId = $firstItem['item_supplier_id'] ?? null;
                                        $cabangId = $firstItem['item_cabang_id'] ?? null;

                                        if (empty($supplierId) || empty($cabangId)) {
                                            continue;
                                        }

                                        $poNumber = HelperController::generatePoNumber();
                                        while (PurchaseOrder::where('po_number', $poNumber)->exists()) {
                                            $poNumber = HelperController::generatePoNumber();
                                        }
                                        $poData = array_merge($data, [
                                            'supplier_id'    => $supplierId,
                                            'cabang_id'      => $cabangId,
                                            'po_number'      => $poNumber,
                                            'selected_items' => $groupItems->values()->toArray(),
                                            'multi_supplier' => false,
                                        ]);
                                        $orderRequestService->createPurchaseOrder($record, $poData);
                                        $created++;
                                    }
                                    $record->refresh();
                                    $record->update(['status' => 'approved']);
                                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. {$created} Purchase Order berhasil dibuat per supplier.");
                                    return;
                                }

                                // Single supplier mode — check PO number uniqueness
                                $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                                if ($purchaseOrder) {
                                    HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                    return;
                                }
                            }

                            $orderRequestService->approve($record, $data);
                            $record->refresh();
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. Proses selanjutnya: Pembuatan Purchase Order oleh Tim Purchasing.");
                        }),
                    Action::make('request_approve')
                        ->label('Request Approve')
                        ->color('gray')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Ajukan Persetujuan')
                        ->modalDescription('Apakah Anda yakin ingin mengajukan order request ini untuk disetujui?')
                        ->visible(function ($record) {
                            return $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            $record->update(['status' => 'request_approve']);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah diajukan untuk persetujuan.");
                        }),
                    Action::make('close')
                        ->label('Close')
                        ->color('warning')
                        ->icon('heroicon-o-lock-closed')
                        ->requiresConfirmation()
                        ->modalHeading('Close Order Request')
                        ->modalDescription('Are you sure you want to close this order request? This action cannot be undone.')
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user && $user->hasPermissionTo('approve order request') && in_array($record->status, ['draft', 'request_approve', 'approved']);
                        })
                        ->action(function ($record) {
                            $record->update(['status' => 'closed']);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah ditutup. Proses pembelian tidak akan dilanjutkan.");
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

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Informasi Order Request')
                    ->columns(3)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('request_number')
                            ->label('Request Number'),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        // header-level warehouse removed; per-item/PO selection determines warehouse assignment
                        \Filament\Infolists\Components\TextEntry::make('request_date')
                            ->label('Request Date')
                            ->date('d/m/Y'),
                    ]),
                \Filament\Infolists\Components\Section::make('Ringkasan Quantity')
                    ->description('Nilai dihitung dari qty accepted pada penerimaan barang, bukan dari approval PO.')
                    ->columns(2)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('items_count')
                            ->label('Jumlah Item')
                            ->getStateUsing(fn($record) => $record->orderRequestItem->count()),
                        \Filament\Infolists\Components\TextEntry::make('total_quantity')
                            ->label('Total Qty')
                            ->getStateUsing(fn($record) => (float) $record->orderRequestItem->sum('quantity')),
                        \Filament\Infolists\Components\TextEntry::make('fulfilled_quantity')
                            ->label('Qty Diterima (Penerimaan Barang)')
                            ->getStateUsing(fn($record) => (float) $record->orderRequestItem->sum('fulfilled_quantity')),
                        \Filament\Infolists\Components\TextEntry::make('remaining_quantity')
                            ->label('Sisa Qty Belum Diterima')
                            ->getStateUsing(fn($record) => (float) $record->orderRequestItem->sum(
                                fn($item) => max(0, (float) $item->quantity - (float) ($item->fulfilled_quantity ?? 0))
                            )),
                    ]),
                \Filament\Infolists\Components\Section::make('Detail Item Order Request')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('orderRequestItem')
                            ->label('')
                            ->columnSpanFull()
                            ->columns(6)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('product.name')
                                    ->label('Produk')
                                    ->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('supplier_display')
                                    ->label('Supplier')
                                    ->getStateUsing(function ($record) {
                                        if (! $record->supplier_id) {
                                            return '-';
                                        }

                                        $code = $record->supplier?->code ?? '-';
                                        $name = $record->supplier?->perusahaan ?? '-';

                                        return "({$code}) {$name}";
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('cabang.nama')
                                    ->label('Cabang Item')
                                    ->getStateUsing(function ($record) {
                                        $code = $record->cabang?->kode ?? null;
                                        $name = $record->cabang?->nama ?? null;

                                        if (! $name) {
                                            return '-';
                                        }

                                        return $code ? "({$code}) {$name}" : $name;
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty'),
                                \Filament\Infolists\Components\TextEntry::make('fulfilled_quantity')
                                    ->label('Qty Diterima (Penerimaan Barang)')
                                    ->getStateUsing(fn($record) => (float) ($record->fulfilled_quantity ?? 0)),
                                \Filament\Infolists\Components\TextEntry::make('remaining_quantity')
                                    ->label('Sisa Qty Belum Diterima')
                                    ->getStateUsing(fn($record) => max(0, (float) $record->quantity - (float) ($record->fulfilled_quantity ?? 0))),
                                \Filament\Infolists\Components\TextEntry::make('original_price')
                                    ->label('Harga Supplier')
                                    ->getStateUsing(function ($record) {
                                        $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;

                                        return self::resolveCurrencySymbol($currencyId) . ' ' . number_format((float) ($record->original_price ?? 0), 2, ',', '.');
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Harga Override')
                                    ->getStateUsing(function ($record) {
                                        $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;

                                        return self::resolveCurrencySymbol($currencyId) . ' ' . number_format((float) ($record->unit_price ?? 0), 2, ',', '.');
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->getStateUsing(function ($record) {
                                        return $record->tipe_pajak ?? '-';
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('tax')
                                    ->label('Pajak (%)')
                                    ->getStateUsing(fn($record) => number_format((float) ($record->tax ?? 0), 0, ',', '.') . '%'),
                                \Filament\Infolists\Components\TextEntry::make('total_cost')
                                    ->label('Total (Harga x Qty)')
                                    ->getStateUsing(function ($record) {
                                        $totalCost = (float) ($record->quantity ?? 0) * (float) ($record->unit_price ?? 0);
                                        $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;

                                        return self::resolveCurrencySymbol($currencyId) . ' ' . number_format($totalCost, 2, ',', '.');
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('tax_nominal')
                                    ->label('Nominal Pajak')
                                    ->getStateUsing(function ($record) {
                                        $taxType = self::taxServiceTypeFromItemTaxType($record->tipe_pajak ?? null);
                                        $base = (float) ($record->quantity ?? 0) * (float) ($record->unit_price ?? 0);
                                        $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;

                                        try {
                                            $taxResult = \App\Services\TaxService::compute($base, (float) ($record->tax ?? 0), $taxType);
                                            return self::resolveCurrencySymbol($currencyId) . ' ' . number_format((float) ($taxResult['ppn'] ?? 0), 2, ',', '.');
                                        } catch (\Throwable $e) {
                                            return self::resolveCurrencySymbol($currencyId) . ' ' . number_format(0, 2, ',', '.');
                                        }
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->getStateUsing(function ($record) {
                                        $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                        return self::resolveCurrencySymbol($currencyId) . ' ' . number_format((float) ($record->subtotal ?? 0), 2, ',', '.');
                                    }),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderRequests::route('/'),
            'create' => Pages\CreateOrderRequest::route('/create'),
            'view' => ViewOrderRequest::route('/{record}'),
            'edit' => Pages\EditOrderRequest::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // Recalculate subtotals server-side (same as mutateFormDataBeforeSave)
        if (isset($data['orderRequestItem']) && is_array($data['orderRequestItem'])) {
            foreach ($data['orderRequestItem'] as &$item) {
                $qty   = (float) ($item['quantity'] ?? 0);
                $price = \App\Helpers\MoneyHelper::parse($item['unit_price'] ?? 0);
                $disc  = (float) ($item['discount'] ?? 0);
                $itemTaxType = self::normalizeItemTaxType($item['tipe_pajak'] ?? null);
                $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                $productId = is_numeric($item['product_id'] ?? null) ? (int) $item['product_id'] : null;
                $tax = self::resolveItemTaxRate($productId, $itemTaxType);

                $item['tipe_pajak'] = $itemTaxType;
                $item['tax'] = $tax;

                $base      = $qty * $price;
                $afterDisc = $base - $base * ($disc / 100);

                try {
                    $taxResult        = \App\Services\TaxService::compute($afterDisc, $tax, $taxType);
                    $item['subtotal'] = $taxResult['total'];
                } catch (\Throwable $e) {
                    $item['subtotal'] = $afterDisc;
                }
            }
            unset($item);
        }

        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        // Recalculate subtotals server-side and ignore any client-provided values
        if (isset($data['orderRequestItem']) && is_array($data['orderRequestItem'])) {
            foreach ($data['orderRequestItem'] as &$item) {
                $qty   = (float) ($item['quantity'] ?? 0);
                $price = \App\Helpers\MoneyHelper::parse($item['unit_price'] ?? 0);
                $disc  = (float) ($item['discount'] ?? 0);
                $itemTaxType = self::normalizeItemTaxType($item['tipe_pajak'] ?? null);
                $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                $productId = is_numeric($item['product_id'] ?? null) ? (int) $item['product_id'] : null;
                $tax = self::resolveItemTaxRate($productId, $itemTaxType);

                $item['tipe_pajak'] = $itemTaxType;
                $item['tax'] = $tax;

                $base      = $qty * $price;
                $afterDisc = $base - $base * ($disc / 100);

                try {
                    $taxResult        = \App\Services\TaxService::compute($afterDisc, $tax, $taxType);
                    $item['subtotal'] = $taxResult['total'];
                } catch (\Throwable $e) {
                    $item['subtotal'] = $afterDisc;
                }
            }
            unset($item);
        }
        return $data;
    }
}
