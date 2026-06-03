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
use App\Support\OrderRequestQuantityLock;
use App\Helpers\MoneyHelper;
use App\Support\TaxTypeHelper;
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

        // Nominal pajak ALWAYS calculated from base amount (quantity × unitPrice)
        // This should NOT change when switching tax type
        $taxNominal = round($afterDisc * ($taxPct / 100), 2);

        // Subtotal is what differs between tax types
        $subtotal = $normalizedTaxType === 'inklusif'
            ? $afterDisc  // Price already includes tax
            : $afterDisc + $taxNominal;  // Price excludes tax, so add it

        return [
            'total_cost' => (float) $base,
            'subtotal' => (float) $subtotal,
            'tax_nominal' => (float) $taxNominal,
        ];
    }

    public static function normalizeItemTaxType(?string $itemTaxType): string
    {
        return TaxTypeHelper::normalize($itemTaxType);
    }

    public static function taxServiceTypeFromItemTaxType(?string $itemTaxType): string
    {
        return TaxTypeHelper::serviceType($itemTaxType);
    }

    public static function normalizeTaxTypeValue(?string $taxType): string
    {
        return TaxTypeHelper::normalize($taxType);
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

    public static function convertIdrToCurrency(float $amountInIdr, ?int $currencyId, bool $round = true): float
    {
        return CurrencyConversionResolver::convertFromIdr($amountInIdr, $currencyId, $round);
    }

    public static function formatMoneyByCurrency(?int $currencyId, float $amount): string
    {
        return CurrencyConversionResolver::formatAmount($currencyId, $amount);
    }

    public static function formatMoneyInputState(mixed $amount): string
    {
        return number_format(self::parseCurrencyState($amount ?? 0), 2, ',', '.');
    }

    public static function formatMoneyPreviewState(mixed $amount): string
    {
        return number_format(MoneyHelper::safeParse($amount ?? 0), 2, ',', '.');
    }

    public static function normalizeCurrencyDisplayValue(float $amount, ?int $currencyId): float
    {
        $currencyCode = Currency::find($currencyId)?->code;

        return $currencyCode === 'IDR' ? round($amount, 2) : $amount;
    }

    public static function isIdrCurrency(?int $currencyId): bool
    {
        $currencyCode = Currency::find($currencyId)?->code;

        return strtoupper((string) $currencyCode) === 'IDR';
    }

    public static function resolveIdrAnchorFromDisplay(mixed $anchor, mixed $displayValue, ?int $currencyId): string
    {
        $anchorValue = MoneyHelper::parseHighPrecision($anchor ?? 0);
        if ((float) $anchorValue > 0) {
            return number_format((float) $anchorValue, 2, '.', '');
        }

        return CurrencyConversionResolver::convertToIdrHighPrecision(
            MoneyHelper::parseHighPrecision($displayValue ?? 0),
            $currencyId
        );
    }

    public static function convertIdrAnchorToCurrency(string|float $anchorIdr, ?int $currencyId): string
    {
        if (static::isIdrCurrency($currencyId)) {
            return bcadd((string) $anchorIdr, '0', 2);
        }

        return CurrencyConversionResolver::convertFromIdrHighPrecision($anchorIdr, $currencyId);
    }

    public static function resolveOverrideAnchorFromInput(mixed $displayValue, ?int $currencyId): string
    {
        $price = MoneyHelper::parseHighPrecision($displayValue ?? 0);

        if (static::isIdrCurrency($currencyId)) {
            return number_format((float) $price, 2, '.', '');
        }

        return CurrencyConversionResolver::convertToIdrHighPrecision($price, $currencyId);
    }

    public static function normalizeOrderRequestItemMoneyForSave(array $item, ?int $defaultCurrencyId = null): array
    {
        $currencyId = is_numeric($item['currency_id'] ?? null)
            ? (int) $item['currency_id']
            : $defaultCurrencyId;

        $unitAnchor = static::resolveIdrAnchorFromDisplay(
            $item['unit_price_idr'] ?? 0,
            $item['unit_price'] ?? 0,
            $currencyId
        );
        $originalAnchor = static::resolveIdrAnchorFromDisplay(
            $item['original_price_idr'] ?? 0,
            $item['original_price'] ?? ($item['unit_price'] ?? 0),
            $currencyId
        );

        $item['currency_id'] = $currencyId;
        $item['unit_price_idr'] = $unitAnchor;
        $item['original_price_idr'] = $originalAnchor;
        $item['unit_price'] = static::convertIdrAnchorToCurrency($unitAnchor, $currencyId);
        $item['original_price'] = static::convertIdrAnchorToCurrency($originalAnchor, $currencyId);

        $qty = (float) ($item['quantity'] ?? 0);
        $price = (float) $item['unit_price'];
        $disc = (float) ($item['discount'] ?? 0);
        $itemTaxType = self::normalizeItemTaxType($item['tipe_pajak'] ?? null);
        $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
        $productId = is_numeric($item['product_id'] ?? null) ? (int) $item['product_id'] : null;
        $tax = self::resolveItemTaxRate($productId, $itemTaxType);

        $item['tipe_pajak'] = $itemTaxType;
        $item['tax'] = $tax;

        $preview = self::calculateApprovalItemPreview($qty, $price, $disc, $tax, $taxType);
        $item['subtotal'] = round((float) $preview['subtotal'], 2);

        return $item;
    }

    public static function parseCurrencyState(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $cleaned = trim((string) $value);
        $cleaned = preg_replace('/[^0-9,\.\-]/u', '', $cleaned) ?? '';

        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }

        if (! str_contains($cleaned, ',') && ! str_contains($cleaned, '.')) {
            return (float) $cleaned;
        }

        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $lastCommaPos = strrpos($cleaned, ',');
            $lastDotPos = strrpos($cleaned, '.');

            if ($lastDotPos !== false && $lastCommaPos !== false && $lastDotPos > $lastCommaPos) {
                return (float) str_replace(',', '', $cleaned);
            }

            return (float) str_replace(',', '.', str_replace('.', '', $cleaned));
        }

        if (str_contains($cleaned, ',')) {
            $parts = explode(',', $cleaned);
            $lastPart = end($parts) ?: '';

            if (count($parts) === 2 && preg_match('/^\d{1,2}$/', $lastPart)) {
                return (float) (str_replace('.', '', $parts[0]) . '.' . $lastPart);
            }

            return (float) str_replace(',', '', $cleaned);
        }

        if (substr_count($cleaned, '.') === 1) {
            if (preg_match('/^\d+\.\d{1,2}$/', $cleaned)) {
                return (float) $cleaned;
            }

            if (preg_match('/^\d+\.\d{3}$/', $cleaned)) {
                return (float) str_replace('.', '', $cleaned);
            }

            return (float) $cleaned;
        }

        return (float) str_replace('.', '', $cleaned);
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

    public static function buildPurchaseOrderSelectedItemsRepeater(): Repeater
    {
        return Repeater::make('selected_items')
            ->label('')
            ->columns(4)
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->collapsed()
            ->itemLabel(function (array $state): string {
                $productName = $state['product_name'] ?? '-';
                $qty = $state['quantity'] ?? '0';
                $supplierName = $state['supplier_name'] ?? '-';
                $subtotal = $state['subtotal'] ?? '0';
                $includeStatus = ($state['include'] ?? true) ? 'Disertakan' : 'Tidak disertakan';

                return "Product: {$productName} | Qty: {$qty} | Supplier: {$supplierName} | Subtotal: {$subtotal} | {$includeStatus}";
            })
            ->schema([
                Hidden::make('item_id'),
                Hidden::make('item_supplier_id'),
                Hidden::make('item_cabang_id'),
                Hidden::make('max_quantity'),
                Hidden::make('currency_id'),
                TextInput::make('product_name')
                    ->label('Nama Produk')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('supplier_name')
                    ->label('Supplier')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('cabang_name')
                    ->label('Cabang')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('uom')
                    ->label('Satuan')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->minValue(0)
                    ->reactive()
                    ->live()
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
                    ->label('Harga Asli')
                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('unit_price')
                    ->label('Harga Override')
                    ->required()
                    ->disabled(fn(Get $get) => (bool) ($get('../../create_purchase_order') ?? $get('create_purchase_order') ?? false))
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                    ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 2)
        JS))
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '';
                        }

                        return self::formatMoneyPreviewState($state);
                    })
                    ->dehydrateStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return null;
                        }

                        return self::parseCurrencyState($state);
                    })
                    ->reactive()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $taxType = self::normalizeTaxTypeValue($get('tipe_pajak') ?? null);
                        $preview = self::calculateApprovalItemPreview(
                            (float) ($get('quantity') ?? 0),
                            self::parseCurrencyState($state ?? 0),
                            0,
                            (float) ($get('tax') ?? 0),
                            $taxType
                        );

                        $set('total_cost', self::formatMoneyPreviewState($preview['total_cost']));
                        $set('subtotal', self::formatMoneyPreviewState($preview['subtotal']));
                        $set('tax_nominal', self::formatMoneyPreviewState($preview['tax_nominal']));
                    })
                    ->rules([
                        'required',
                        'regex:/^[0-9\.,]+$/',
                    ])
                    ->validationMessages([
                        'required' => 'Harga override wajib diisi.',
                        'regex' => 'Harga override harus berupa angka (contoh: 12.000.000).',
                    ]),
                Radio::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->options(TaxTypeHelper::options())
                    ->default('eklusif')
                    ->disabled()
                    ->dehydrated(true)
                    ->extraAttributes(['class' => 'data-[disabled=true]:opacity-75']),
                TextInput::make('tax')
                    ->label('Pajak (%)')
                    ->numeric()
                    ->readOnly()
                    ->suffix('%')
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('tax_nominal')
                    ->label('Nominal Pajak')
                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('total_cost')
                    ->label('Total (Harga × Qty)')
                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->prefix(fn(Get $get) => self::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null))
                    ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                    ->readOnly()
                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']),
                Checkbox::make('include')
                    ->label('Sertakan')
                    ->live()
                    ->default(true),
            ]);
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
                        $converted = self::convertIdrToCurrency((float) $price, $currencyId, false);
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
                $converted = self::convertIdrToCurrency((float) $price, $currencyId, false);
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
                        Hidden::make('currency_id')
                            ->default(fn() => CurrencyConversionResolver::resolveCurrencyIdByCode('IDR'))
                            ->dehydrated(true),
                        Hidden::make('tax_type')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $normalized = self::normalizeTaxTypeValue($state ?? null);
                                $items = $get('orderRequestItem') ?? [];
                                foreach (array_keys($items) as $idx) {
                                    $new = $normalized === 'inklusif' ? 'inklusif' : ($normalized === 'none' ? 'none' : 'eklusif');
                                    $set("orderRequestItem.{$idx}.tipe_pajak", $new);
                                    $productId = $get("orderRequestItem.{$idx}.product_id") ?? null;
                                    $rate = self::resolveItemTaxRate($productId, $new ?? null);
                                    $set("orderRequestItem.{$idx}.tax", $rate);
                                }
                            }),
                        Textarea::make('note')
                            ->label('Note')
                            ->nullable(),
                        Repeater::make('orderRequestItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->hint('Tambahkan item produk yang ingin dipesan')
                            ->minItems(1)
                            ->required()
                            ->collapsed(function (?string $operation, ?\Filament\Forms\ComponentContainer $item, \Filament\Forms\Components\Repeater $component): bool {
                                if (! $item) {
                                    return false;
                                }

                                $state = $component->getState() ?? [];
                                if (empty($state)) {
                                    return false;
                                }

                                $keys = array_keys($state);
                                $lastKey = end($keys);

                                $statePathParts = explode('.', $item->getStatePath());
                                $itemKey = end($statePathParts);

                                $itemState = $state[$itemKey] ?? [];
                                if ($operation !== 'create' && filled($itemState['id'] ?? null)) {
                                    return true;
                                }

                                // Collapse all items except the last newly added row.
                                return $itemKey !== $lastKey;
                            })
                            ->addAction(function (\Filament\Forms\Components\Actions\Action $action) {
                                return $action
                                    ->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Items')
                                    ->extraAttributes(fn($component) => [
                                        'onclick' => (function () use ($component) {
                                            $event = 'repeater-collapse';
                                            $statePath = $component->getStatePath();
                                            $eventJs = 'String.fromCharCode(' . implode(',', array_map('ord', str_split($event))) . ')';
                                            $statePathJs = 'String.fromCharCode(' . implode(',', array_map('ord', str_split($statePath))) . ')';
                                            return "window.dispatchEvent(new CustomEvent({$eventJs}, { detail: {$statePathJs} }))";
                                        })(),
                                    ])
                                    ->action(function (\Filament\Forms\Components\Repeater $component): void {
                                        $newUuid = $component->generateUuid();

                                        $items = $component->getState();

                                        if ($newUuid) {
                                            $items[$newUuid] = [];
                                        } else {
                                            $items[] = [];
                                        }

                                        $component->state($items);

                                        $component->getChildComponentContainer($newUuid ?? array_key_last($items))->fill();

                                        // We deliberately do NOT call $component->collapsed(false) here.
                                        // This keeps our custom dynamic collapsed evaluation intact!

                                        $component->callAfterStateUpdated();
                                    });
                            })
                            ->itemLabel(function (array $state) {
                                $productName = '-';
                                if (!empty($state['product_id'])) {
                                    $product = \App\Models\Product::withoutGlobalScope('product_cabang')->find($state['product_id']);
                                    $productName = $product ? "({$product->sku}) {$product->name}" : '-';
                                }

                                $qty = $state['quantity'] ?? '0';

                                $supplierName = '-';
                                if (!empty($state['supplier_id'])) {
                                    $supplier = \App\Models\Supplier::find($state['supplier_id']);
                                    $supplierName = $supplier ? "({$supplier->code}) {$supplier->perusahaan}" : '-';
                                }

                                $subtotal = $state['subtotal'] ?? '0';
                                $currencyId = isset($state['currency_id']) && is_numeric($state['currency_id'])
                                    ? (int) $state['currency_id']
                                    : null;
                                $currencySymbol = \App\Support\CurrencyConversionResolver::resolveSymbol($currencyId);

                                return "Product: {$productName} | Qty: {$qty} | Supplier: {$supplierName} | Subtotal: {$currencySymbol} {$subtotal}";
                            })
                            ->validationMessages([
                                'required' => 'Order request harus memiliki setidaknya satu item produk.',
                                'min' => 'Order request harus memiliki setidaknya satu item produk.',
                            ])
                            ->schema([
                                \Filament\Forms\Components\Grid::make(5)
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->columnSpan(2)
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
                                                        $unitPrice = self::convertIdrToCurrency((float) $product->cost_price, $itemCurrencyId, false);
                                                        if ($itemSupplierId) {
                                                            $supplierProduct = $product->suppliers()->where('suppliers.id', $itemSupplierId)->first();
                                                            if ($supplierProduct) {
                                                                $supplierPrice = $supplierProduct->pivot->supplier_price;
                                                                $unitPrice = $supplierPrice !== null
                                                                    ? self::convertIdrToCurrency((float) $supplierPrice, $itemCurrencyId, false)
                                                                    : self::convertIdrToCurrency((float) $product->cost_price, $itemCurrencyId, false);
                                                            }
                                                        }
                                                        $set('supplier_id', $itemSupplierId);
                                                        // Store the master price as original_price; user can override unit_price
                                                        $set('original_price', self::formatMoneyInputState($unitPrice));
                                                        $set('unit_price', self::formatMoneyInputState($unitPrice));

                                                        // Persist IDR anchor so currency round-trips stay lossless
                                                        // Always convert the raw IDR cost_price (not the rounded foreign display value)
                                                        $rawIdrPrice = (float) ($supplierProduct?->pivot->supplier_price ?? $product->cost_price ?? 0);
                                                        $set('unit_price_idr', $rawIdrPrice);
                                                        $set('original_price_idr', $rawIdrPrice);
                                                        $set('unit', $product->uom?->abbreviation ?? '-');
                                                        // Autofill cabang item from product if available, but keep editable
                                                        $set('cabang_id', $product->cabang_id ?? Supplier::find($itemSupplierId)?->cabang_id ?? auth()->user()?->cabang_id);
                                                        // Recalculate subtotal
                                                        $quantity = (float) ($get('quantity') ?? 0);
                                                        $discPct  = (float) ($get('discount') ?? 0);
                                                        $taxPct   = $taxRate;
                                                        $preview = self::calculateApprovalItemPreview($quantity, $unitPrice, $discPct, $taxPct, $taxType);
                                                        $set('total', self::formatMoneyPreviewState($preview['total_cost']));
                                                        $set('total_cost', self::formatMoneyPreviewState($preview['total_cost']));
                                                        $set('subtotal', self::formatMoneyPreviewState($preview['subtotal']));
                                                        $set('tax_nominal', self::formatMoneyPreviewState($preview['tax_nominal']));
                                                        $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * $unitPrice) * ($discPct / 100), 2)));
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
                                            ->columnSpan(1)
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->default('-')
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                            ->afterStateHydrated(function ($component, $record) {
                                                if ($record?->product) {
                                                    $component->state($record->product->uom?->abbreviation ?? '-');
                                                }
                                            }),
                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->columnSpan(1)
                                            ->numeric()
                                            ->default(0)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                $taxType   = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                $quantity  = (float) ($state ?? 0);
                                                $unitPrice = self::parseCurrencyState($get('unit_price') ?? 0);
                                                $discPct   = (float) ($get('discount') ?? 0);
                                                $taxPct    = (float) ($get('tax') ?? 0);
                                                $base      = $quantity * $unitPrice;
                                                $afterDisc = $base - $base * ($discPct / 100);
                                                // Nominal pajak ALWAYS calculated from afterDisc * taxPct, independent of tax type
                                                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                                                // Subtotal varies based on tax type interpretation
                                                $subtotal  = $taxType === 'PPN Included'
                                                    ? $afterDisc
                                                    : $afterDisc + $taxNominal;
                                                $set('subtotal', self::formatMoneyPreviewState($subtotal));
                                                $set('total', self::formatMoneyPreviewState($quantity * $unitPrice));
                                                $set('tax_nominal', self::formatMoneyPreviewState($taxNominal));
                                                $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * $unitPrice) * ($discPct / 100), 2)));
                                            })
                                            ->required()
                                            ->minValue(0.01)
                                            ->validationMessages([
                                                'required' => 'Quantity wajib diisi.',
                                                'numeric' => 'Quantity harus berupa angka.',
                                                'min' => 'Quantity minimal 0.01.',
                                            ]),
                                        Select::make('cabang_id')
                                            ->label('Cabang')
                                            ->columnSpan(1)
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
                                                'required' => 'Cabang wajib dipilih.',
                                            ]),
                                    ]),
                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        Select::make('supplier_id')
                                            ->label('Supplier')
                                            ->columnSpan(1)
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
                                                                $unitPrice = self::convertIdrToCurrency((float) $supplierProduct->pivot->supplier_price, $itemCurrencyId, false);
                                                            } else {
                                                                $unitPrice = self::convertIdrToCurrency((float) ($product->cost_price ?? 0), $itemCurrencyId, false);
                                                            }

                                                            $set('original_price', self::formatMoneyInputState($unitPrice));
                                                            $set('unit_price', self::formatMoneyInputState($unitPrice));

                                                            // Persist IDR anchor
                                                            $rawIdrPrice = (float) ($supplierProduct?->pivot->supplier_price ?? $product->cost_price ?? 0);
                                                            $set('unit_price_idr', $rawIdrPrice);
                                                            $set('original_price_idr', $rawIdrPrice);
                                                            // Recalculate subtotal
                                                            $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                            $taxType  = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                            $quantity = (float) ($get('quantity') ?? 0);
                                                            $discPct  = (float) ($get('discount') ?? 0);
                                                            $taxPct   = (float) ($get('tax') ?? 0);
                                                            $preview = self::calculateApprovalItemPreview($quantity, $unitPrice, $discPct, $taxPct, $taxType);
                                                            $set('total', self::formatMoneyPreviewState($preview['total_cost']));
                                                            $set('total_cost', self::formatMoneyPreviewState($preview['total_cost']));
                                                            $set('subtotal', self::formatMoneyPreviewState($preview['subtotal']));
                                                            $set('tax_nominal', self::formatMoneyPreviewState($preview['tax_nominal']));
                                                            $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * $unitPrice) * ($discPct / 100), 2)));
                                                        }
                                                    }
                                                }
                                            })
                                            ->helperText('Pilih supplier untuk item ini (opsional, akan memperbarui harga)'),
                                        Placeholder::make('supplier_recommendation')
                                            ->label('Rekomendasi Supplier')
                                            ->columnSpan(1)
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

                                                $converted = self::convertIdrToCurrency($price, $itemCurrencyId, false);

                                                return "{$recommended->perusahaan} (" . self::formatMoneyByCurrency($itemCurrencyId, $converted) . ')';
                                            }),
                                    ]),
                                \Filament\Forms\Components\Grid::make(6)
                                    ->schema([
                                        Select::make('currency_id')
                                            ->label('Mata Uang Item')
                                            ->columnSpan(1)
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

                                                // ── IDR Anchor Strategy ─────────────────────────────────────────────────
                                                // PROBLEM: reading the current display value (already rounded to 2 dp) and
                                                // re-converting it loses precision:
                                                //   1.000.000 ÷ 15.000 = 66.6666 → rounded to 66.67
                                                //   66.67 × 15.000 = 1.000.050 (wrong!)
                                                //
                                                // FIX: always convert from the stored IDR anchor value so the source
                                                // precision is never truncated by the intermediate display rounding.
                                                // ────────────────────────────────────────────────────────────────────────

                                                // Read IDR anchor (hidden field populated when product/supplier is set)
                                                $unitPriceIdrRaw      = MoneyHelper::parseHighPrecision($get('unit_price_idr') ?? 0);
                                                $originalPriceIdrRaw  = MoneyHelper::parseHighPrecision($get('original_price_idr') ?? 0);

                                                // If anchor is zero/missing, fall back to converting the current display value
                                                // through the old currency as a best-effort (handles pre-migration rows)
                                                if ((float) $unitPriceIdrRaw <= 0) {
                                                    $currentUnitPrice = MoneyHelper::parseHighPrecision($get('unit_price') ?? 0);
                                                    $unitPriceIdrRaw  = \App\Support\CurrencyConversionResolver::convertToIdrHighPrecision(
                                                        $currentUnitPrice,
                                                        $oldCurrencyId
                                                    );
                                                }
                                                if ((float) $originalPriceIdrRaw <= 0) {
                                                    $currentOriginalPrice = MoneyHelper::parseHighPrecision($get('original_price') ?? 0);
                                                    $originalPriceIdrRaw  = \App\Support\CurrencyConversionResolver::convertToIdrHighPrecision(
                                                        $currentOriginalPrice,
                                                        $oldCurrencyId
                                                    );
                                                }

                                                // Convert directly from IDR anchor to new currency (no intermediate rounding)
                                                $convertedUnitPrice = \App\Support\CurrencyConversionResolver::convertFromIdrHighPrecision(
                                                    $unitPriceIdrRaw,
                                                    $newCurrencyId
                                                );
                                                $convertedOriginalPrice = \App\Support\CurrencyConversionResolver::convertFromIdrHighPrecision(
                                                    $originalPriceIdrRaw,
                                                    $newCurrencyId
                                                );

                                                // For IDR display: round to whole number (no cents in Rupiah)
                                                // For foreign currency: keep 2 decimal places
                                                $newCode = \App\Models\Currency::find($newCurrencyId)?->code;
                                                if (strtoupper((string) $newCode) === 'IDR') {
                                                    $convertedUnitPrice     = bcadd($unitPriceIdrRaw, '0', 2);
                                                    $convertedOriginalPrice = bcadd($originalPriceIdrRaw, '0', 2);
                                                }

                                                // Update hidden IDR anchor fields with the now-resolved IDR values
                                                $set('unit_price_idr', $unitPriceIdrRaw);
                                                $set('original_price_idr', $originalPriceIdrRaw);

                                                $set('original_price', self::formatMoneyInputState($convertedOriginalPrice));
                                                $set('unit_price', self::formatMoneyInputState($convertedUnitPrice));

                                                $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                $quantity = (float) ($get('quantity') ?? 0);
                                                $discPct = (float) ($get('discount') ?? 0);
                                                $taxPct = (float) ($get('tax') ?? 0);
                                                $preview = self::calculateApprovalItemPreview($quantity, (float) $convertedUnitPrice, $discPct, $taxPct, $taxType);

                                                $set('total', self::formatMoneyPreviewState($preview['total_cost']));
                                                $set('total_cost', self::formatMoneyPreviewState($preview['total_cost']));
                                                $set('subtotal', self::formatMoneyPreviewState($preview['subtotal']));
                                                $set('tax_nominal', self::formatMoneyPreviewState($preview['tax_nominal']));
                                                $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * (float) $convertedUnitPrice) * ($discPct / 100), 2)));
                                            })
                                            ->options(function () {
                                                return Currency::orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(function (Currency $c) {
                                                        return [$c->id => "{$c->name} ({$c->symbol})"];
                                                    });
                                            })
                                            ->default(fn() => CurrencyConversionResolver::resolveCurrencyIdByCode('IDR'))
                                            ->helperText('Mata uang item')
                                            ->validationMessages([
                                                'required' => 'Mata uang item wajib dipilih',
                                            ]),
                                        TextInput::make('original_price')
                                            ->label('Harga Asli (Master)')
                                            ->columnSpan(1)
                                            ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                                is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                            ))
                                            ->default(0)
                                            ->readOnly()
                                            ->dehydrated()
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                            ->mask(\Filament\Support\RawJs::make(<<<'JS'
                                                $money($input, ',', '.', 2)
                                            JS))
                                            ->dehydrateStateUsing(fn($state) => self::parseCurrencyState($state ?? 0))
                                            ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                                            ->afterStateHydrated(function ($component, $record) {
                                                if ($record && $record->original_price !== null) {
                                                    $component->state(self::formatMoneyPreviewState($record->original_price));
                                                }
                                            })
                                            ->helperText('Harga dari master produk'),
                                        TextInput::make('unit_price')
                                            ->label('Harga Override')
                                            ->columnSpan(1)
                                            ->reactive()
                                            ->live()
                                            ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                                is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                            ))
                                            ->mask(\Filament\Support\RawJs::make(<<<'JS'
                                                $money($input, ',', '.', 2)
                                            JS))
                                            ->dehydrateStateUsing(fn($state) => self::parseCurrencyState($state ?? 0))
                                            ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? number_format(self::parseCurrencyState($state), 2, ',', '.') : '')
                                            ->afterStateHydrated(function ($component, $record) {
                                                if ($record && $record->unit_price !== null) {
                                                    $formatted = number_format(self::parseCurrencyState($record->unit_price), 2, ',', '.');
                                                    $component->state($formatted);
                                                }
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                $taxType   = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                $quantity  = (float) ($get('quantity') ?? 0);
                                                $unitPrice = self::parseCurrencyState($state ?? 0);
                                                $itemCurrencyId = is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null);
                                                $set('unit_price_idr', self::resolveOverrideAnchorFromInput($state, $itemCurrencyId));
                                                $discPct   = (float) ($get('discount') ?? 0);
                                                $taxPct    = (float) ($get('tax') ?? 0);
                                                $base      = $quantity * $unitPrice;
                                                $afterDisc = $base - $base * ($discPct / 100);
                                                // Nominal pajak ALWAYS calculated from afterDisc * taxPct, independent of tax type
                                                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                                                // Subtotal varies based on tax type interpretation
                                                $subtotal  = $taxType === 'PPN Included'
                                                    ? $afterDisc
                                                    : $afterDisc + $taxNominal;
                                                $set('subtotal', self::formatMoneyPreviewState($subtotal));
                                                $set('total', self::formatMoneyPreviewState($quantity * $unitPrice));
                                                $set('tax_nominal', self::formatMoneyPreviewState($taxNominal));
                                                $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * $unitPrice) * ($discPct / 100), 2)));
                                            })
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Harga override wajib diisi.',
                                                'numeric' => 'Harga override harus berupa angka.',
                                            ]),
                                        TextInput::make('discount')
                                            ->label('Discount (%)')
                                            ->columnSpan(1)
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $itemTaxType = $get('tipe_pajak') ?? 'eklusif';
                                                $taxType   = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                $quantity  = (float) ($get('quantity') ?? 0);
                                                $unitPrice = self::parseCurrencyState($get('unit_price') ?? 0);
                                                $discPct   = (float) ($state ?? 0);
                                                $taxPct    = (float) ($get('tax') ?? 0);
                                                $base      = $quantity * $unitPrice;
                                                $afterDisc = $base - $base * ($discPct / 100);
                                                // Nominal pajak ALWAYS calculated from afterDisc * taxPct, independent of tax type
                                                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                                                // Subtotal varies based on tax type interpretation
                                                $subtotal  = $taxType === 'PPN Included'
                                                    ? $afterDisc
                                                    : $afterDisc + $taxNominal;
                                                $set('subtotal', self::formatMoneyPreviewState($subtotal));
                                                $set('tax_nominal', self::formatMoneyPreviewState($taxNominal));
                                                $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * $unitPrice) * ($discPct / 100), 2)));
                                            })
                                            ->validationMessages([
                                                'numeric' => 'Discount harus berupa angka.',
                                                'min' => 'Discount tidak boleh negatif.',
                                                'max' => 'Discount maksimal 100%.',
                                            ]),
                                        TextInput::make('discount_nominal')
                                            ->label('Discount (Nominal)')
                                            ->columnSpan(1)
                                            ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                                is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                            ))
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->default(0)
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                            ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                                            ->afterStateHydrated(function ($component, $record) {
                                                if ($record) {
                                                    $unitPrice = self::parseCurrencyState($record->unit_price ?? 0);
                                                    $qty = (float) ($record->quantity ?? 0);
                                                    $discPct = (float) ($record->discount ?? 0);
                                                    $nominal = round(($qty * $unitPrice) * ($discPct / 100), 2);
                                                    $component->state(self::formatMoneyPreviewState($nominal));
                                                }
                                            }),
                                        TextInput::make('total')
                                            ->label('Total (Harga × Qty)')
                                            ->columnSpan(1)
                                            ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                                is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                            ))
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->default(0)
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                            ->formatStateUsing(fn($state) => $state !== null && $state !== '' ? self::formatMoneyPreviewState($state) : '')
                                            ->afterStateHydrated(function ($component, $record) {
                                                if ($record) {
                                                    $unitPrice = self::parseCurrencyState($record->unit_price ?? 0);
                                                    $total = (float)$record->quantity * $unitPrice;
                                                    $component->state(self::formatMoneyPreviewState($total));
                                                }
                                            }),
                                    ]),
                                \Filament\Forms\Components\Grid::make(4)
                                    ->schema([
                                        Radio::make('tipe_pajak')
                                            ->label('Tipe Pajak')
                                            ->columnSpan(2)
                                            ->inline()
                                            ->required()
                                            ->default('eklusif')
                                            ->options(TaxTypeHelper::options())
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $itemTaxType = self::normalizeItemTaxType($state);
                                                $taxType = self::taxServiceTypeFromItemTaxType($itemTaxType);
                                                $productId = is_numeric($get('product_id')) ? (int) $get('product_id') : null;
                                                $taxPct = self::resolveItemTaxRate($productId, $itemTaxType);

                                                $set('tipe_pajak', $itemTaxType);
                                                $set('tax', $taxPct);

                                                $quantity  = (float) ($get('quantity') ?? 0);
                                                $unitPrice = self::parseCurrencyState($get('unit_price') ?? 0);
                                                $discPct   = (float) ($get('discount') ?? 0);
                                                $preview = self::calculateApprovalItemPreview($quantity, $unitPrice, $discPct, $taxPct, $taxType);
                                                $set('total', self::formatMoneyPreviewState($preview['total_cost']));
                                                $set('total_cost', self::formatMoneyPreviewState($preview['total_cost']));
                                                $set('subtotal', self::formatMoneyPreviewState($preview['subtotal']));
                                                $set('tax_nominal', self::formatMoneyPreviewState($preview['tax_nominal']));
                                                $set('discount_nominal', self::formatMoneyPreviewState(round(($quantity * $unitPrice) * ($discPct / 100), 2)));
                                            }),
                                        TextInput::make('tax')
                                            ->label('Tax (%)')
                                            ->columnSpan(1)
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
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                            ->validationMessages([
                                                'numeric' => 'Tax harus berupa angka.',
                                                'min' => 'Tax tidak boleh negatif.',
                                                'max' => 'Tax maksimal 100%.',
                                            ]),
                                        TextInput::make('tax_nominal')
                                            ->label('Nominal Pajak')
                                            ->columnSpan(1)
                                            ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                                is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                            ))
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->default(0)
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                            ->afterStateHydrated(function ($component, $record) {
                                                if (! $record) {
                                                    return;
                                                }
                                                $taxPct    = (float) ($record->tax ?? 0);
                                                $qty       = (float) ($record->quantity ?? 0);
                                                $unitPrice = self::parseCurrencyState($record->unit_price ?? 0);
                                                $discPct   = (float) ($record->discount ?? 0);
                                                $base      = $qty * $unitPrice;
                                                $afterDisc = $base - $base * ($discPct / 100);
                                                // Nominal pajak ALWAYS calculated from afterDisc * taxPct, independent of tax type
                                                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                                                $component->state(self::formatMoneyPreviewState($taxNominal));
                                            }),
                                    ]),
                                \Filament\Forms\Components\Grid::make(3)
                                    ->schema([
                                        TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->columnSpan(1)
                                            ->default(0)
                                            ->prefix(fn(Get $get) => self::resolveCurrencySymbol(
                                                is_numeric($get('currency_id'))
                                                    ? (int) $get('currency_id')
                                                    : (is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : null)
                                            ))
                                            ->disabled()
                                            ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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
                                                $unitPrice = self::parseCurrencyState($record->unit_price ?? 0);
                                                $discPct   = (float) ($record->discount ?? 0);
                                                $taxType   = \App\Models\OrderRequestItem::taxServiceTypeFromItemTaxType(
                                                    $record->tipe_pajak ?? null
                                                );
                                                $base      = $qty * $unitPrice;
                                                $afterDisc = $base - $base * ($discPct / 100);
                                                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                                                // Subtotal varies based on tax type
                                                $subtotal  = $taxType === 'PPN Included'
                                                    ? $afterDisc
                                                    : $afterDisc + $taxNominal;
                                                $component->state(self::formatMoneyPreviewState($subtotal));
                                            }),
                                        Textarea::make('note')
                                            ->nullable()
                                            ->label('Note')
                                            ->columnSpan(2)
                                            ->rows(1),
                                    ]),
                                Hidden::make('unit_price_idr')
                                    ->default(fn(Get $get) => \App\Helpers\MoneyHelper::parseHighPrecision($get('unit_price') ?? 0))
                                    ->dehydrated(true),
                                Hidden::make('original_price_idr')
                                    ->default(fn(Get $get) => \App\Helpers\MoneyHelper::parseHighPrecision($get('original_price') ?? 0))
                                    ->dehydrated(true),
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
            ->description(new \Illuminate\Support\HtmlString('
                <style>.fi-ta-header:has(.dt-table-description-full-width){align-items:stretch}.fi-ta-header>.grid:has(.dt-table-description-full-width){width:100%;max-width:none;flex:1 1 100%;}.dt-table-description-full-width{width:100%;min-width:100%;max-width:none;box-sizing:border-box;}</style>
                <div class="dt-table-description-full-width space-y-4 mb-6 w-full min-w-full max-w-none" style="width: 100%; min-width: 100%; max-width: none; box-sizing: border-box;">
                    <!-- Panduan Expandable -->
                    <details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff; transition: all 0.2s;">
                        <summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; color: #374151;">
                            <span class="flex items-center gap-2" style="display: flex; align-items: center; gap: 8px;">
                                <svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; color: #3b82f6;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Panduan Order Request (Permintaan Pembelian)
                            </span>
                            <span class="transition group-open:rotate-180">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </summary>
                        <div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top: 12px; font-size: 14px; color: #4b5563; padding-left: 28px; border-left: 2px solid rgba(59, 130, 246, 0.3); display: flex; flex-direction: column; gap: 8px;">
                            <p><strong>Apa ini:</strong> Order Request adalah dokumen permintaan pembelian internal sebelum diterbitkan menjadi Purchase Order resmi.</p>
                            <p><strong>Cara Approve:</strong> Pengguna dengan akses persetujuan dapat menekan tombol <em style="color: #2563eb; font-style: normal; font-weight: 600;">Approve</em> pada baris data untuk menyetujui permintaan.</p>
                            <p><strong>Dampak:</strong> Setelah disetujui, Purchase Order (PO) dapat dibuat secara otomatis atau manual dari item-item request tersebut.</p>
                        </div>
                    </details>

                    <!-- Informasi Warna Status (Legend) -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff;">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 16px; height: 16px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            Legenda Warna Status Baris Data
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                            <!-- Biru -->
                            <div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>
                                <div class="leading-tight">
                                    <span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (Approved)</span>
                                    <span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Request disetujui</span>
                                </div>
                            </div>
                            <!-- Kuning -->
                            <div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 243, 199, 0.4); border: 1px solid rgba(253, 230, 138, 0.8);">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background-color: #eab308; box-shadow: 0 1px 3px rgba(234, 179, 8, 0.4); flex-shrink: 0;"></div>
                                <div class="leading-tight">
                                    <span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #854d0e;">Kuning (Partial)</span>
                                    <span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">PO sebagian</span>
                                </div>
                            </div>
                            <!-- Hijau -->
                            <div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(220, 252, 231, 0.4); border: 1px solid rgba(187, 247, 208, 0.8);">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background-color: #22c55e; box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4); flex-shrink: 0;"></div>
                                <div class="leading-tight">
                                    <span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #166534;">Hijau (Complete)</span>
                                    <span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">PO lengkap selesai</span>
                                </div>
                            </div>
                            <!-- Merah -->
                            <div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 226, 226, 0.4); border: 1px solid rgba(254, 202, 202, 0.8);">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background-color: #ef4444; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4); flex-shrink: 0;"></div>
                                <div class="leading-tight">
                                    <span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #991b1b;">Merah (Closed/Rejected)</span>
                                    <span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Ditolak / ditutup</span>
                                </div>
                            </div>
                            <!-- Abu-abu -->
                            <div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(243, 244, 246, 0.6); border: 1px solid rgba(229, 231, 235, 0.9);">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background-color: #6b7280; box-shadow: 0 1px 3px rgba(107, 114, 128, 0.4); flex-shrink: 0;"></div>
                                <div class="leading-tight">
                                    <span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #374151;">Abu (Req Approve)</span>
                                    <span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Menunggu review</span>
                                </div>
                            </div>
                            <!-- Putih/Default -->
                            <div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: #ffffff; border: 1px solid #edf2f7;">
                                <div style="width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid #9ca3af; background-color: #ffffff; flex-shrink: 0;"></div>
                                <div class="leading-tight">
                                    <span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #4b5563;">Putih (Draft)</span>
                                    <span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Draft baru</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            '))
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
                    Action::make('preview_pdf')
                        ->label('Preview / Download PDF')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->visible(fn($record) => $record->status === 'approved')
                        ->url(fn($record) => route('pdf-stream', ['type' => 'order-request', 'id' => $record->id]))
                        ->openUrlInNewTab(),
                    Action::make('create_purchase_order')
                        ->label('Create Purchase Order')
                        ->color('primary')
                        ->icon('heroicon-o-plus')
                        ->modalWidth('6xl')
                        ->modalHeading('Buat Purchase Order')
                        ->modalDescription('Isi form berikut untuk membuat Purchase Order dari Order Request yang telah disetujui.')
                        ->fillForm(function ($record) {
                            $items = $record->orderRequestItem->map(function ($item) use ($record) {
                                $remainingQty = OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'];
                                if ($remainingQty <= 0) {
                                    return null;
                                }
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
                                $base = max(0, $remainingQty) * $supplierPrice;
                                // No discount at PO level; calculate tax and subtotal
                                $taxNominal = round($base * ($taxPct / 100), 2);
                                $itemTaxType = \App\Models\OrderRequestItem::taxServiceTypeFromItemTaxType($item->tipe_pajak ?? null);
                                $subtotal = $itemTaxType === 'PPN Included'
                                    ? $base
                                    : $base + $taxNominal;
                                $taxNom = self::formatMoneyPreviewState($taxNominal);
                                $subtotal = self::formatMoneyPreviewState($subtotal);

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
                                    'original_price'   => self::formatMoneyPreviewState($item->original_price ?? $supplierPrice),
                                    'unit_price'       => $supplierPrice,
                                    'tax'              => $taxPct,
                                    'tax_nominal'      => $taxNom,
                                    'total_cost'       => self::formatMoneyPreviewState($base),
                                    'subtotal'         => $subtotal,
                                    'max_quantity'     => max(0, $remainingQty),
                                    'include'          => $remainingQty > 0,
                                    'tipe_pajak'       => self::normalizeItemTaxType($item->tipe_pajak ?? null),
                                ];
                            })->filter()->values()->toArray();

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
                                'selected_items'        => $items,
                            ];
                        })
                        ->form([
                            Section::make('Informasi Purchase Order')
                                ->icon('heroicon-o-calendar')
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->columns(2)
                                ->schema([
                                    DatePicker::make('order_date')
                                        ->label('Tanggal Pembelian')
                                        ->required()
                                        ->native(false)
                                        ->displayFormat('d M Y'),
                                    DatePicker::make('expected_date')
                                        ->label('Tanggal Diharapkan')
                                        ->nullable()
                                        ->native(false)
                                        ->displayFormat('d M Y'),
                                    Textarea::make('note')
                                        ->label('Catatan')
                                        ->nullable()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                            Section::make('Pilih Item yang Akan Dibeli')
                                ->description('Centang item yang akan dimasukkan ke dalam Purchase Order. Anda dapat mengubah quantity dan harga sebelum menyetujui.')
                                ->icon('heroicon-o-shopping-cart')
                                ->collapsible()
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->schema([
                                    self::buildPurchaseOrderSelectedItemsRepeater(),
                                ]),
                        ])
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            if (!$user || !$user->hasPermissionTo('approve order request') || !in_array($record->status, ['approved', 'partial'], true)) {
                                return false;
                            }
                            // Allow creating a new PO as long as some items still have unfulfilled quantity
                            return $record->orderRequestItem->contains(
                                fn($item) => OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'] > 0
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
                                    HelperController::sendNotification(isSuccess: true, title: 'Berhasil', message: "{$created} Purchase Order berhasil dibuat dan otomatis disetujui.");
                                } else {
                                    // Single supplier mode
                                    // Generate PO number automatically if not provided
                                    $poNumber = $data['po_number'] ?? null;
                                    if (empty($poNumber)) {
                                        $poNumber = HelperController::generatePoNumber();
                                        while (PurchaseOrder::where('po_number', $poNumber)->exists()) {
                                            $poNumber = HelperController::generatePoNumber();
                                        }
                                    }
                                    // Get supplier_id from first selected item if not provided
                                    if (empty($data['supplier_id']) && !empty($data['selected_items'])) {
                                        $firstItem = collect($data['selected_items'])
                                            ->filter(fn($i) => $i['include'] ?? false)
                                            ->first();
                                        $data['supplier_id'] = $firstItem['item_supplier_id'] ?? null;
                                    }
                                    $purchaseOrder = PurchaseOrder::where('po_number', $poNumber)->first();
                                    if ($purchaseOrder) {
                                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                        return;
                                    }
                                    $poData = array_merge($data, ['po_number' => $poNumber]);
                                    $orderRequestService->createPurchaseOrder($record, $poData);
                                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order berhasil dibuat dan otomatis disetujui.");
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
                                $remainingQty = OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'];
                                if ($remainingQty <= 0) {
                                    return null;
                                }
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
                                $base = max(0, $remainingQty) * $supplierPrice;
                                // No discount at approval level; calculate tax and subtotal
                                $taxNominal = round($base * ($taxPct / 100), 2);
                                $itemTaxType = \App\Models\OrderRequestItem::taxServiceTypeFromItemTaxType($item->tipe_pajak ?? null);
                                $subtotal = $itemTaxType === 'PPN Included'
                                    ? $base
                                    : $base + $taxNominal;
                                $taxNom = self::formatMoneyPreviewState($taxNominal);
                                $subtotal = self::formatMoneyPreviewState($subtotal);

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
                                    'original_price'   => self::formatMoneyPreviewState($item->original_price ?? $supplierPrice),
                                    'unit_price'       => $supplierPrice,
                                    'tax'              => $taxPct,
                                    'tax_nominal'      => $taxNom,
                                    'total_cost'       => self::formatMoneyPreviewState(max(0, $remainingQty) * $supplierPrice),
                                    'subtotal'         => $subtotal,
                                    'max_quantity'     => max(0, $remainingQty),
                                    'include'          => $remainingQty > 0,
                                    'tipe_pajak'       => self::normalizeItemTaxType($item->tipe_pajak ?? null),
                                ];
                            })->filter()->values()->toArray();

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
                                'selected_items'        => $items,
                            ];
                        })
                        ->form([
                            Section::make('Opsi Persetujuan')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([

                                    // header-level cabang removed; per-item cabang used instead
                                    \Filament\Forms\Components\Toggle::make('create_purchase_order')
                                        ->label('Buat Purchase Order secara otomatis?')
                                        ->helperText('Aktifkan untuk langsung membuat PO setelah approval.')
                                        ->default(true)
                                        ->live()
                                        ->columnSpanFull(),
                                    Hidden::make('multi_supplier'),
                                    \Filament\Forms\Components\Placeholder::make('multi_supplier_notice')
                                        ->label('')
                                        ->content('Item dalam OR ini memiliki beberapa supplier dan cabang berbeda. Sistem akan membuat satu PO per supplier secara otomatis.')
                                        ->visible(fn(Get $get) => $get('create_purchase_order') && $get('multi_supplier'))
                                        ->columnSpanFull(),
                                ]),
                            Section::make('Informasi Purchase Order')
                                ->icon('heroicon-o-calendar')
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->columns(2)
                                ->schema([
                                    DatePicker::make('order_date')
                                        ->label('Tanggal Pembelian')
                                        ->required()
                                        ->native(false)
                                        ->displayFormat('d M Y'),
                                    DatePicker::make('expected_date')
                                        ->label('Tanggal Diharapkan')
                                        ->nullable()
                                        ->native(false)
                                        ->displayFormat('d M Y'),
                                    Textarea::make('note')
                                        ->label('Catatan')
                                        ->nullable()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                            Section::make('Pilih Item yang Akan Dibeli')
                                ->description('Centang item yang akan dimasukkan ke dalam Purchase Order. Anda dapat mengubah quantity dan harga sebelum menyetujui.')
                                ->icon('heroicon-o-shopping-cart')
                                ->collapsible()
                                ->visible(fn(Get $get) => $get('create_purchase_order'))
                                ->schema([
                                    self::buildPurchaseOrderSelectedItemsRepeater(),
                                ]),
                        ])
                        ->visible(function ($record) {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user && $user->hasPermissionTo('approve order request') && $record->status == 'request_approve';
                        })
                        ->action(function (array $data, $record) {
                            try {
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

                                    // Generate PO number automatically if not provided and create_purchase_order is enabled
                                    if (!empty($data['create_purchase_order']) && empty($data['po_number'])) {
                                        $data['po_number'] = HelperController::generatePoNumber();
                                        while (PurchaseOrder::where('po_number', $data['po_number'])->exists()) {
                                            $data['po_number'] = HelperController::generatePoNumber();
                                        }
                                    }
                                    // Get supplier_id from first selected item if not provided
                                    if (empty($data['supplier_id']) && !empty($data['selected_items'])) {
                                        $firstItem = collect($data['selected_items'])
                                            ->filter(fn($i) => $i['include'] ?? false)
                                            ->first();
                                        $data['supplier_id'] = $firstItem['item_supplier_id'] ?? null;
                                    }
                                    $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'] ?? '')->first();
                                    if ($purchaseOrder) {
                                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                        return;
                                    }
                                }

                                $orderRequestService->approve($record, $data);
                                $record->refresh();
                                HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request telah disetujui. Purchase Order dari proses ini otomatis disetujui jika dibuat.");
                            } catch (Throwable $exception) {
                                ProcurementFailureNotifier::danger(
                                    'Gagal Memproses Order Request',
                                    $exception,
                                    'Order request belum dapat diproses. Periksa data yang dipilih lalu coba lagi.'
                                );
                            }
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

    public static function resolveRemainingReceiptQuantity(\App\Models\OrderRequestItem $item): float
    {
        return OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_receipt'];
    }

    protected static function detailLineEntry(string $name, string $label, \Closure $state, int $columnSpan = 1): \Filament\Infolists\Components\TextEntry
    {
        return \Filament\Infolists\Components\TextEntry::make($name)
            ->label('')
            ->getStateUsing(fn($record) => self::detailLineHtml($label, $state($record)))
            ->html()
            ->columnSpan($columnSpan);
    }

    protected static function detailLineHtml(string $label, mixed $value): string
    {
        $display = $value;

        if ($display === null || $display === '') {
            $display = '-';
        }

        return '<div class="flex gap-2 py-0.5 text-sm">'
            . '<span class="w-44 shrink-0 font-medium text-gray-600 dark:text-gray-400">' . e($label) . ' :</span>'
            . '<span class="min-w-0 flex-1 text-gray-950 dark:text-white">' . nl2br(e((string) $display)) . '</span>'
            . '</div>';
    }

    protected static function detailColumnEntry(string $name, string $heading, array $rows): \Filament\Infolists\Components\TextEntry
    {
        return \Filament\Infolists\Components\TextEntry::make($name)
            ->label('')
            ->getStateUsing(function ($record) use ($heading, $rows) {
                $html = '<div class="space-y-1">';
                $html .= '<div class="mb-2 text-base font-semibold text-gray-950 dark:text-white">' . e($heading) . '</div>';

                foreach ($rows as [$label, $state]) {
                    $html .= self::detailLineHtml($label, $state($record));
                }

                $html .= '</div>';

                return $html;
            })
            ->html();
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
                                fn($item) => self::resolveRemainingReceiptQuantity($item)
                            )),
                    ]),
                \Filament\Infolists\Components\Section::make('Detail Item Order Request')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('orderRequestItem')
                            ->label('')
                            ->columnSpanFull()
                            ->schema([
                                \Filament\Infolists\Components\Section::make(function ($record) {
                                    $productName = $record->product ? "({$record->product->sku}) {$record->product->name}" : '-';
                                    $qty = (float) ($record->quantity ?? 0);
                                    $supplierName = $record->supplier ? "({$record->supplier->code}) {$record->supplier->perusahaan}" : '-';
                                    $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                    $preview = self::calculateApprovalItemPreview(
                                        (float) ($record->quantity ?? 0),
                                        (float) ($record->unit_price ?? 0),
                                        (float) ($record->discount ?? 0),
                                        (float) ($record->tax ?? 0),
                                        self::taxServiceTypeFromItemTaxType($record->tipe_pajak ?? null)
                                    );
                                    $subtotal = self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($preview['subtotal']);

                                    return "Product: {$productName} | Qty: {$qty} | Supplier: {$supplierName} | Subtotal: {$subtotal}";
                                })
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        \Filament\Infolists\Components\Grid::make(2)
                                            ->schema([
                                                \Filament\Infolists\Components\Group::make([
                                                    self::detailColumnEntry(
                                                        'product_column',
                                                        'Produk',
                                                        [
                                                            ['Product', function ($record) {
                                                                if (! $record->product) {
                                                                    return '-';
                                                                }

                                                                return $record->product->sku
                                                                    ? "({$record->product->sku}) {$record->product->name}"
                                                                    : ($record->product->name ?? '-');
                                                            }],
                                                            ['Satuan', function ($record) {
                                                                return $record->product?->uom?->abbreviation
                                                                    ?? $record->product?->uom?->name
                                                                    ?? '-';
                                                            }],
                                                            ['Qty', fn($record) => $record->quantity],
                                                            ['Sisa Qty Belum Diterima', fn($record) => self::resolveRemainingReceiptQuantity($record)],
                                                            ['Cabang', function ($record) {
                                                                $code = $record->cabang?->kode ?? null;
                                                                $name = $record->cabang?->nama ?? null;
                                                                if (! $name) {
                                                                    return '-';
                                                                }
                                                                return $code ? "({$code}) {$name}" : $name;
                                                            }],
                                                            ['Supplier', function ($record) {
                                                                if (! $record->supplier_id) {
                                                                    return '-';
                                                                }
                                                                $code = $record->supplier?->code ?? '-';
                                                                $name = $record->supplier?->perusahaan ?? '-';
                                                                return "({$code}) {$name}";
                                                            }],
                                                            ['Rekomendasi Supplier', function ($record) {
                                                                $productId = $record->product_id;
                                                                if (!$productId) {
                                                                    return '-';
                                                                }
                                                                $product = Product::withoutGlobalScope('product_cabang')->with('suppliers')->find($productId);
                                                                if (!$product || $product->suppliers->isEmpty()) {
                                                                    return 'Tidak ada supplier terdaftar untuk produk ini.';
                                                                }
                                                                $recommended = $product->suppliers
                                                                    ->sortBy(fn($supplier) => (float) ($supplier->pivot->supplier_price ?? PHP_FLOAT_MAX))
                                                                    ->first();
                                                                $price = (float) ($recommended?->pivot->supplier_price ?? 0);
                                                                $itemCurrencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                $converted = self::convertIdrToCurrency($price, $itemCurrencyId, false);
                                                                return "{$recommended->perusahaan} (" . self::resolveCurrencySymbol($itemCurrencyId) . ' ' . self::formatMoneyPreviewState($converted) . ')';
                                                            }],
                                                            ['Note', fn($record) => $record->note ?? '-'],
                                                        ]
                                                    ),
                                                ])
                                                    ->columnSpan(1)
                                                    ->columns(1),
                                                \Filament\Infolists\Components\Group::make([
                                                    self::detailColumnEntry(
                                                        'price_column',
                                                        'Price',
                                                        [
                                                            ['Mata Uang', fn($record) => $record->currency?->code ?? $record->orderRequest?->currency?->code ?? '-'],
                                                            ['Harga Asli (Master)', function ($record) {
                                                                $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                return self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($record->original_price ?? 0);
                                                            }],
                                                            ['Harga Override', function ($record) {
                                                                $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                return self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($record->unit_price ?? 0);
                                                            }],
                                                            ['Discount', fn($record) => number_format((float) ($record->discount ?? 0), 0, ',', '.') . '%'],
                                                            ['Discount (Nominal)', function ($record) {
                                                                $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                $qty = (float) ($record->quantity ?? 0);
                                                                $unitPrice = (float) ($record->unit_price ?? 0);
                                                                $discPct = (float) ($record->discount ?? 0);
                                                                $nominal = round(($qty * $unitPrice) * ($discPct / 100), 2);
                                                                return self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($nominal);
                                                            }],
                                                            ['Total (Harga x Qty)', function ($record) {
                                                                $totalCost = (float) ($record->quantity ?? 0) * (float) ($record->unit_price ?? 0);
                                                                $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                return self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($totalCost);
                                                            }],
                                                            ['Tipe Pajak', function ($record) {
                                                                return $record->tipe_pajak ?? '-';
                                                            }],
                                                            ['Tax (%)', fn($record) => number_format((float) ($record->tax ?? 0), 0, ',', '.') . '%'],
                                                            ['Nominal Pajak', function ($record) {
                                                                $taxType = self::taxServiceTypeFromItemTaxType($record->tipe_pajak ?? null);
                                                                $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                $preview = self::calculateApprovalItemPreview(
                                                                    (float) ($record->quantity ?? 0),
                                                                    (float) ($record->unit_price ?? 0),
                                                                    (float) ($record->discount ?? 0),
                                                                    (float) ($record->tax ?? 0),
                                                                    $taxType
                                                                );
                                                                return self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($preview['tax_nominal']);
                                                            }],
                                                            ['Subtotal', function ($record) {
                                                                $currencyId = $record->currency_id ?? $record->orderRequest?->currency_id;
                                                                $preview = self::calculateApprovalItemPreview(
                                                                    (float) ($record->quantity ?? 0),
                                                                    (float) ($record->unit_price ?? 0),
                                                                    (float) ($record->discount ?? 0),
                                                                    (float) ($record->tax ?? 0),
                                                                    self::taxServiceTypeFromItemTaxType($record->tipe_pajak ?? null)
                                                                );
                                                                return self::resolveCurrencySymbol($currencyId) . ' ' . self::formatMoneyPreviewState($preview['subtotal']);
                                                            }],
                                                        ]
                                                    ),
                                                ])
                                                    ->columnSpan(1)
                                                    ->columns(1),
                                            ]),
                                    ]),
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
        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');

        // Recalculate subtotals server-side (same as mutateFormDataBeforeSave)
        if (isset($data['orderRequestItem']) && is_array($data['orderRequestItem'])) {
            foreach ($data['orderRequestItem'] as &$item) {
                $item = self::normalizeOrderRequestItemMoneyForSave($item, $defaultCurrencyId);
            }
            unset($item);
        }

        $data['currency_id'] = collect($data['orderRequestItem'] ?? [])
            ->pluck('currency_id')
            ->filter()
            ->first() ?? $defaultCurrencyId;

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');

        // Recalculate subtotals server-side and ignore any client-provided values
        if (isset($data['orderRequestItem']) && is_array($data['orderRequestItem'])) {
            foreach ($data['orderRequestItem'] as &$item) {
                $item = self::normalizeOrderRequestItemMoneyForSave($item, $defaultCurrencyId);
            }
            unset($item);
        }

        $data['currency_id'] = collect($data['orderRequestItem'] ?? [])
            ->pluck('currency_id')
            ->filter()
            ->first() ?? $defaultCurrencyId;

        return $data;
    }
}
