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
use App\Models\OrderRequestItem;
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
use App\Support\CurrencyConversionResolver;
use App\Helpers\MoneyHelper;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as ActionsAction;
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
use Filament\Notifications\Notification;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\DB;
use Saade\FilamentAutograph\Forms\Components\SignaturePad as ComponentsSignaturePad;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    // Group label updated to include English hint per request
    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?string $navigationLabel = 'Pesanan Pembelian';

    protected static ?string $modelLabel = 'Pesanan Pembelian';

    protected static ?string $pluralModelLabel = 'Pesanan Pembelian';

    protected static ?int $navigationSort = 2;

    public static function formatMoneyState(mixed $value, ?int $currencyId = null): string
    {
        return self::formatCurrencyPreviewState($value, $currencyId);
    }

    protected static function topTypeOptions(): array
    {
        return [
            'cod' => 'COD',
            'advance_before_delivery' => 'Advance Before Delivery',
            'deposit_balance' => 'Deposit + Balance',
            'credit_days' => 'Credit ... Days',
        ];
    }

    protected static function normalizeTopTypeValue(?string $topType): string
    {
        $normalized = strtolower(trim((string) $topType));

        return match ($normalized) {
            'cod' => 'cod',
            'advance before delivery', 'advance_before_delivery', 'advance-before-delivery', 'advance' => 'advance_before_delivery',
            'deposit + balance', 'deposit_balance', 'deposit-balance', 'deposit balance' => 'deposit_balance',
            'credit', 'credit_days', 'credit days', 'days' => 'credit_days',
            default => 'credit_days',
        };
    }

    public static function syncPurchaseOrderCurrencyData(array $data): array
    {
        $existingRates = collect($data['purchaseOrderCurrency'] ?? [])
            ->filter(fn ($row) => is_numeric($row['currency_id'] ?? null))
            ->mapWithKeys(fn ($row) => [(int) $row['currency_id'] => $row['nominal'] ?? null]);

        $currencyIds = collect($data['purchaseOrderItem'] ?? [])
            ->pluck('currency_id')
            ->merge(collect($data['purchaseOrderBiaya'] ?? [])->pluck('currency_id'))
            ->filter(fn ($currencyId) => is_numeric($currencyId))
            ->map(fn ($currencyId) => (int) $currencyId)
            ->unique()
            ->values();

        $data['purchaseOrderCurrency'] = $currencyIds
            ->map(function (int $currencyId) use ($existingRates) {
                $nominal = $existingRates->get($currencyId);

                return [
                    'currency_id' => $currencyId,
                    'nominal' => is_numeric($nominal) && (float) $nominal > 0
                        ? (float) $nominal
                        : CurrencyConversionResolver::resolveRate($currencyId),
                ];
            })
            ->all();

        return $data;
    }

    protected static function parseCurrencyState(mixed $value): float
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

            if (count($parts) === 2 && preg_match('/^\d+$/', $lastPart)) {
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

    protected static function currencyInputDecimals(?int $currencyId): int
    {
        return self::isIdrCurrency($currencyId) ? 2 : 10;
    }

    protected static function currencyPreviewDecimals(?int $currencyId): int
    {
        return 2;
    }

    protected static function isIdrCurrency(?int $currencyId): bool
    {
        if ($currencyId === null) {
            return true;
        }

        return Currency::find($currencyId)?->code === 'IDR';
    }

    protected static function formatCurrencyInputState(mixed $amount, ?int $currencyId): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $decimals = self::currencyInputDecimals($currencyId);
        $formatted = number_format(self::parseCurrencyState($amount), $decimals, ',', '.');

        if ($decimals <= 2) {
            return $formatted;
        }

        [$whole, $fraction] = explode(',', $formatted, 2);
        $fraction = rtrim($fraction, '0');
        $fraction = strlen($fraction) < 2 ? str_pad($fraction, 2, '0') : $fraction;

        return "{$whole},{$fraction}";
    }

    public static function formatCurrencyPreviewState(mixed $amount, ?int $currencyId): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format(self::parseCurrencyState($amount), self::currencyPreviewDecimals($currencyId), ',', '.');
    }

    public static function calculateCurrencyPreview(float $quantity, float $unitPrice, float $discount, float $tax, ?string $taxType, ?int $currencyId): array
    {
        $normalizedTaxType = self::normalizeTaxTypeValue($taxType);
        $base = $quantity * $unitPrice;
        $afterDiscount = $base - ($base * ($discount / 100));
        $taxNominal = round($afterDiscount * ($tax / 100), 2);

        return [
            'total' => $base,
            'tax_nominal' => $normalizedTaxType === 'none' ? 0.0 : $taxNominal,
            'subtotal' => $normalizedTaxType === 'inklusif' ? $afterDiscount : $afterDiscount + $taxNominal,
        ];
    }

    protected static function formatSupplierLabel(?Supplier $supplier): string
    {
        if (! $supplier || ! $supplier->exists) {
            return '-';
        }

        $code = trim((string) ($supplier->code ?? ''));
        $name = trim((string) ($supplier->perusahaan ?? ''));

        if ($code === '' && $name === '') {
            return '-';
        }

        if ($code === '') {
            return $name;
        }

        if ($name === '') {
            return "({$code})";
        }

        return "({$code}) {$name}";
    }

    public static function formatStatusLabel(?string $status): string
    {
        if (! $status) {
            return '-';
        }

        return Str::of($status)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    public static function getStatusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'approved' => 'info',
            'partially_received' => 'warning',
            'request_close' => 'danger',
            'closed' => 'danger',
            'completed', 'paid' => 'success',
            default => 'gray',
        };
    }

    public static function normalizeTaxTypeValue(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'none', 'non pajak', 'non-pajak', 'nonpajak' => 'none',
            'inklusif', 'ppn included', 'included', 'ppn-included' => 'inklusif',
            'eklusif', 'eksklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'eklusif',
            default => 'eklusif',
        };
    }

    public static function resolveOrderRequestItemReference(
        ?int $orderRequestId,
        ?int $productId,
        ?int $supplierId = null,
        ?int $cabangId = null
    ): ?OrderRequestItem {
        if (! $orderRequestId || ! $productId) {
            return null;
        }

        $query = OrderRequestItem::query()
            ->where('order_request_id', $orderRequestId)
            ->where('product_id', $productId)
            ->whereRaw('quantity > COALESCE(fulfilled_quantity, 0)');

        if ($supplierId) {
            $query->orderByRaw('CASE WHEN supplier_id = ? THEN 0 WHEN supplier_id IS NULL THEN 1 ELSE 2 END', [$supplierId]);
        }

        if ($cabangId) {
            $query->orderByRaw('CASE WHEN cabang_id = ? THEN 0 WHEN cabang_id IS NULL THEN 1 ELSE 2 END', [$cabangId]);
        }

        return $query->orderBy('id')->first();
    }

    public static function resolveOrderRequestItemCabangId(OrderRequestItem $orderRequestItem, ?OrderRequest $orderRequest = null): ?int
    {
        if (! empty($orderRequestItem->cabang_id)) {
            return (int) $orderRequestItem->cabang_id;
        }

        return null;
    }

    /**
     * Compute the quantity already locked in approved/active PO items for a given OrderRequestItem.
     * This is needed because fulfilled_quantity is only updated when goods are received,
     * not when a PO is approved. Without this, the system would think all qty is still
     * available for new POs right after approving the first PO.
     */
    public static function getLockedQuantityForOrderRequestItem(int $orderRequestItemId): float
    {
        return (float) \App\Models\PurchaseOrderItem::query()
            ->where('refer_item_model_type', OrderRequestItem::class)
            ->where('refer_item_model_id', $orderRequestItemId)
            ->whereHas('purchaseOrder', function ($q) {
                // Count qty locked in POs that are approved or in-progress (not draft/closed/rejected)
                $q->whereNotIn('status', ['draft', 'closed', 'cancelled', 'rejected']);
            })
            ->sum('quantity');
    }

    public static function getAvailableOrderRequestItemGroups(OrderRequest $orderRequest): array
    {
        return $orderRequest->orderRequestItem
            ->map(function (OrderRequestItem $orderRequestItem) use ($orderRequest) {
                // fulfilled_quantity reflects receipts; lockedQty reflects approved POs not yet received.
                // Use the max of both to avoid double-counting when receipts already exceed locked qty.
                $fulfilledQty  = (float) ($orderRequestItem->fulfilled_quantity ?? 0);
                $lockedQty     = static::getLockedQuantityForOrderRequestItem((int) $orderRequestItem->id);
                $accountedQty  = max($fulfilledQty, $lockedQty);
                $remainingQuantity = max(0, (float) $orderRequestItem->quantity - $accountedQty);

                if ($remainingQuantity <= 0) {
                    return null;
                }

                $supplierId = $orderRequestItem->supplier_id ? (int) $orderRequestItem->supplier_id : null;
                $cabangId = static::resolveOrderRequestItemCabangId($orderRequestItem, $orderRequest);
                if (! $supplierId || ! $cabangId) {
                    return null;
                }

                return [
                    'group_key'   => implode('|', [$supplierId, $cabangId]),
                    'supplier_id' => $supplierId,
                    'cabang_id'   => $cabangId,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function getAvailableOrderRequestCabangIds(OrderRequest $orderRequest, ?int $supplierId = null): array
    {
        $groups = static::getAvailableOrderRequestItemGroups($orderRequest);

        return collect($groups)
            ->filter(function ($g) use ($supplierId) {
                if ($supplierId === null) return true;
                return isset($g['supplier_id']) && (int) $g['supplier_id'] === (int) $supplierId;
            })
            ->pluck('cabang_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function getOrderRequestOptions(?int $currentOrderRequestId = null): array
    {
        $options = OrderRequest::query()
            ->whereIn('status', ['approved', 'partial'])
            ->select(['id', 'request_number'])
            ->orderBy('request_number')
            ->get()
            ->filter(fn (OrderRequest $orderRequest) => static::hasAvailableOrderRequestSupplier($orderRequest))
            ->pluck('request_number', 'id')
            ->all();

        if ($currentOrderRequestId && ! array_key_exists($currentOrderRequestId, $options)) {
            $currentOrderRequest = OrderRequest::query()
                ->select(['id', 'request_number'])
                ->find($currentOrderRequestId);

            if ($currentOrderRequest) {
                $options[$currentOrderRequest->id] = $currentOrderRequest->request_number;
            }
        }

        return $options;
    }

    public static function hasAvailableOrderRequestSupplier(OrderRequest $orderRequest): bool
    {
        return count(static::getAvailableOrderRequestItemGroups($orderRequest)) > 0;
    }

    /**
     * Build supplier_id => supplier_price map for the given product IDs.
     * For multi-product context, the first encountered linked price is used.
     *
     * @param array<int, int|string|null> $productIds
     * @return array<int, float>
     */
    public static function resolveLinkedSupplierPriceMap(array $productIds): array
    {
        $normalizedProductIds = collect($productIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedProductIds)) {
            return [];
        }

        $rows = DB::table('product_supplier')
            ->whereIn('product_id', $normalizedProductIds)
            ->get(['supplier_id', 'supplier_price']);

        $linkedPrices = [];
        foreach ($rows as $row) {
            if (! isset($linkedPrices[$row->supplier_id])) {
                $linkedPrices[$row->supplier_id] = (float) $row->supplier_price;
            }
        }

        return $linkedPrices;
    }

    /**
     * Resolve searchable supplier options with linked suppliers ranked first.
     * If exactly one product is in context, linked supplier prices are shown in labels.
     *
     * @param array<int, int|string|null> $productIds
     * @param array<int, int>|null $allowedSupplierIds
     * @return array<int, string>
     */
    public static function resolveSupplierSearchOptions(
        array $productIds,
        string $search = '',
        ?array $allowedSupplierIds = null,
        int $limit = 50
    ): array {
        $normalizedProductIds = collect($productIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $linkedPrices = self::resolveLinkedSupplierPriceMap($normalizedProductIds);

        $query = Supplier::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('perusahaan', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (is_array($allowedSupplierIds)) {
            if (empty($allowedSupplierIds)) {
                return [];
            }
            $query->whereIn('id', array_values(array_unique(array_map('intval', $allowedSupplierIds))));
        }

        if (! empty($linkedPrices)) {
            $linkedIds = array_map('intval', array_keys($linkedPrices));
            $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));
            $query->orderByRaw("CASE WHEN id IN ({$placeholders}) THEN 0 ELSE 1 END", $linkedIds);
        }

        $showPrice = count($normalizedProductIds) === 1;

        return $query
            ->orderBy('perusahaan')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function (Supplier $supplier) use ($linkedPrices, $showPrice) {
                $label = "({$supplier->code}) {$supplier->perusahaan}";
                if ($showPrice && isset($linkedPrices[$supplier->id]) && $linkedPrices[$supplier->id] > 0) {
                    $label .= ' — Rp ' . number_format($linkedPrices[$supplier->id], 2, ',', '.');
                }

                return [$supplier->id => $label];
            })
            ->all();
    }

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
                                            return self::getOrderRequestOptions(
                                                is_numeric($state) ? (int) $state : (int) ($get('refer_model_id') ?? 0)
                                            );
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $items = [];
                                        $defaultCurrencyId = Currency::query()->first()?->id;

                                        if ($get('refer_model_type') == 'App\Models\SaleOrder') {
                                            $saleOrder = SaleOrder::with(['saleOrderItem.product.uom', 'saleOrderItem.product.suppliers'])->find($state);
                                            if ($saleOrder) {
                                                foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                                    // Calculate subtotal using HelperController for consistency
                                                    $subtotal = HelperController::hitungSubtotal($saleOrderItem->quantity, $saleOrderItem->unit_price, $saleOrderItem->discount, $saleOrderItem->tax, null);
                                                    array_push($items, [
                                                        'product_id' => $saleOrderItem->product_id,
                                                        'quantity' => $saleOrderItem->quantity,
                                                        'unit_price' => (function () use ($saleOrderItem, $get) {
                                                            $supplierId = $get('supplier_id');
                                                            if ($supplierId) {
                                                                $sp = $saleOrderItem->product->suppliers()->where('suppliers.id', $supplierId)->first();
                                                                if ($sp) return (float) $sp->pivot->supplier_price;
                                                            }
                                                            return (float) $saleOrderItem->product->cost_price;
                                                        })(),
                                                        'discount' => 0,
                                                        'tax' => 0,
                                                        'subtotal' => $subtotal,
                                                        'unit' => $saleOrderItem->product->uom?->abbreviation ?? '-',
                                                    ]);
                                                }
                                                $set('cabang_id', $saleOrder->cabang_id ?? null);
                                            }
                                        } elseif ($get('refer_model_type') == 'App\Models\OrderRequest') {
                                            $orderRequest = OrderRequest::with(['orderRequestItem.product.uom', 'orderRequestItem.product.suppliers'])->find($state);
                                            if ($orderRequest) {
                                                // header-level warehouse removed from OrderRequest; do not inherit
                                                $ppnOption = match (self::normalizeTaxTypeValue($orderRequest->tax_type ?? null)) {
                                                    'none'      => 'non_ppn',
                                                    'inklusif'  => 'inklusif',
                                                    default     => 'eklusif',
                                                };
                                                $set('ppn_option', $ppnOption);

                                                $availableGroups = self::getAvailableOrderRequestItemGroups($orderRequest);

                                                if (count($availableGroups) > 1) {
                                                    // Multi-group OR: clear supplier field so user must choose one.
                                                    // Items will be populated automatically via supplier_id->afterStateUpdated.
                                                    $set('supplier_id', null);
                                                    // $items stays [] — will be set by the final $set('purchaseOrderItem', $items) below
                                                } else {
                                                    // Single group: auto-select and populate all items immediately
                                                    $autoSupplierId = $availableGroups[0]['supplier_id'] ?? null;
                                                    $autoCabangId = $availableGroups[0]['cabang_id'] ?? null;
                                                    if ($autoSupplierId) {
                                                        $set('supplier_id', $autoSupplierId);
                                                        $set('cabang_id', $autoCabangId);
                                                        $autoSupplier = Supplier::find($autoSupplierId);
                                                        if ($autoSupplier) {
                                                            $set('tempo_hutang', $autoSupplier->tempo_hutang);
                                                            $set('top_type', $autoSupplier->tempo_hutang > 0 ? 'credit_days' : 'cod');
                                                        }
                                                    } else {
                                                        $set('supplier_id', null);
                                                        $set('purchaseOrderItem', []);
                                                    }
                                                    $items = self::buildOrderRequestItems(
                                                        $orderRequest,
                                                        $autoSupplierId ? (int) $autoSupplierId : null,
                                                        $autoCabangId ? (int) $autoCabangId : null,
                                                        $defaultCurrencyId
                                                    );
                                                }
                                            }
                                        }
                                        $set('currency_id', $defaultCurrencyId);
                                        $set('purchaseOrderItem', $items);
                                    })
                                    ->nullable(),
                            ]),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->reactive()
                            ->relationship(
                                name: 'supplier',
                                titleAttribute: 'perusahaan',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    // When an Order Request is selected, restrict options to only that OR's suppliers
                                    $referType    = $get('refer_model_type');
                                    $referModelId = $get('refer_model_id');
                                    if ($referType === 'App\\Models\\OrderRequest' && $referModelId) {
                                        $or = OrderRequest::with('orderRequestItem')->find($referModelId);
                                        if ($or && $or->orderRequestItem->isNotEmpty()) {
                                            $supplierIds = self::getAvailableOrderRequestSupplierIds($or);
                                            $currentSupplierId = (int) ($get('supplier_id') ?? 0);

                                            if ($currentSupplierId > 0) {
                                                $supplierIds[] = $currentSupplierId;
                                                $supplierIds = array_values(array_unique(array_map('intval', $supplierIds)));
                                            }

                                            if (! empty($supplierIds)) {
                                                $query->whereIn('id', $supplierIds);
                                            } else {
                                                $query->whereRaw('1 = 0');
                                            }
                                        }
                                    }

                                    // Rank suppliers linked to products in current cart first, then limit 50
                                    $productIds = collect($get('purchaseOrderItem') ?? [])
                                        ->pluck('product_id')
                                        ->filter()
                                        ->map(fn ($id) => (int) $id)
                                        ->unique()
                                        ->values()
                                        ->all();

                                    if (! empty($productIds)) {
                                        $linkedSupplierIds = DB::table('product_supplier')
                                            ->whereIn('product_id', $productIds)
                                            ->pluck('supplier_id')
                                            ->unique()
                                            ->values()
                                            ->all();

                                        if (! empty($linkedSupplierIds)) {
                                            $placeholders = implode(',', array_fill(0, count($linkedSupplierIds), '?'));
                                            $query->orderByRaw(
                                                "CASE WHEN id IN ({$placeholders}) THEN 0 ELSE 1 END",
                                                $linkedSupplierIds
                                            );
                                        }
                                    }

                                    $query->orderBy('perusahaan')->limit(50);
                                }
                            )
                            ->validationMessages([
                                'required' => 'Supplier belum dipilih',
                            ])
                            ->helperText(function (Get $get) {
                                $supplierId = $get('supplier_id');

                                if (! $supplierId) {
                                    return null;
                                }

                                return 'Supplier saat ini: ' . static::formatSupplierLabel(Supplier::find($supplierId));
                            })
                            ->disabled(function (Get $get) {
                                if ($get('refer_model_type') !== 'App\\Models\\OrderRequest' || !$get('refer_model_id')) {
                                    return false;
                                }

                                $or = OrderRequest::with('orderRequestItem')->find($get('refer_model_id'));
                                if (! $or) {
                                    return false;
                                }

                                $availableSupplierIds = self::getAvailableOrderRequestSupplierIds($or);
                                $currentSupplierId = (int) ($get('supplier_id') ?? 0);

                                return count($availableSupplierIds) === 0 && $currentSupplierId === 0;
                            })
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $productIds = collect($get('purchaseOrderItem') ?? [])
                                    ->pluck('product_id')
                                    ->filter()
                                    ->map(fn ($id) => (int) $id)
                                    ->unique()
                                    ->values()
                                    ->all();

                                $referType    = $get('refer_model_type');
                                $referModelId = $get('refer_model_id');

                                $allowedSupplierIds = null;

                                if ($referType === 'App\\Models\\OrderRequest' && $referModelId) {
                                    $or = OrderRequest::with('orderRequestItem')->find($referModelId);
                                    if ($or && $or->orderRequestItem->isNotEmpty()) {
                                        $allowedSupplierIds = self::getAvailableOrderRequestSupplierIds($or);
                                        $currentSupplierId = (int) ($get('supplier_id') ?? 0);
                                        if ($currentSupplierId > 0) {
                                            $allowedSupplierIds[] = $currentSupplierId;
                                            $allowedSupplierIds = array_values(array_unique(array_map('intval', $allowedSupplierIds)));
                                        }
                                    }
                                }

                                return self::resolveSupplierSearchOptions($productIds, $search, $allowedSupplierIds, 50);
                            })
                            ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                                return "({$supplier->code}) {$supplier->perusahaan}";
                            })
                            ->helperText(function (Get $get) {
                                if ($get('refer_model_type') !== 'App\\Models\\OrderRequest' || !$get('refer_model_id')) {
                                    return null;
                                }
                                $or = OrderRequest::with('orderRequestItem')->find($get('refer_model_id'));
                                if (! $or) {
                                    return null;
                                }
                                $availableSupplierCount = count(self::getAvailableOrderRequestSupplierIds($or));
                                $currentSupplierId = (int) ($get('supplier_id') ?? 0);

                                if ($currentSupplierId > 0 && $availableSupplierCount === 0) {
                                    return 'Semua kuantitas item Order Request ini sudah tercakup oleh PO yang ada. Tidak ada sisa item yang perlu dibuatkan PO.';
                                }

                                if ($availableSupplierCount > 1) {
                                    return "Order Request ini memiliki {$availableSupplierCount} supplier dengan item yang belum sepenuhnya dibuatkan PO. Pilih satu supplier — item akan diisi otomatis sesuai supplier terpilih.";
                                }

                                if ($availableSupplierCount === 0) {
                                    return 'Semua item Order Request ini sudah sepenuhnya tercakup oleh PO yang ada. Tidak ada sisa kuantitas yang perlu dibuatkan PO baru.';
                                }
                                return null;
                            })
                            ->afterStateUpdated(function ($state, $set, Get $get) {
                                $supplier = Supplier::find($state);
                                if ($supplier) {
                                    $set('tempo_hutang', $supplier->tempo_hutang);
                                    $set('top_type', $supplier->tempo_hutang > 0 ? 'credit_days' : 'cod');
                                }
                                // When referring to a multisupplier Order Request, rebuild items for the chosen supplier only
                                if ($get('refer_model_type') === 'App\\Models\\OrderRequest' && $get('refer_model_id') && $state) {
                                    $orderRequest = OrderRequest::with(['orderRequestItem.product.uom', 'orderRequestItem.product.suppliers'])
                                        ->find($get('refer_model_id'));
                                    if ($orderRequest) {
                                        $defaultCurrencyId = Currency::query()->first()?->id;
                                        $items = self::buildOrderRequestItems(
                                            $orderRequest,
                                            (int) $state,
                                            is_numeric($get('cabang_id')) ? (int) $get('cabang_id') : null,
                                            $defaultCurrencyId
                                        );
                                        $set('purchaseOrderItem', $items);
                                    }
                                }
                            })
                            // Task 13: Add link to create new supplier
                            ->createOptionForm([
                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Supplier')
                                    ->required()
                                    ->unique('suppliers', 'code'),
                                Forms\Components\TextInput::make('perusahaan')
                                    ->label('Nama Perusahaan')
                                    ->required(),
                                Forms\Components\TextInput::make('npwp')
                                    ->label('NPWP')
                                    ->maxLength(20),
                                Forms\Components\Textarea::make('address')
                                    ->label('Alamat')
                                    ->rows(3),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telepon')
                                    ->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email(),
                                Forms\Components\TextInput::make('tempo_hutang')
                                    ->label('Tempo Hutang (hari)')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Select::make('cabang_id')
                                    ->label('Cabang')
                                    ->options(\App\Models\Cabang::pluck('nama', 'id'))
                                    ->default(fn() => Auth::user()?->cabang_id)
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return Supplier::create($data)->id;
                            })
                            ->required(),
                        Select::make('top_type')
                            ->label('TOP / Term of Payment')
                            ->options(self::topTypeOptions())
                            ->default('credit_days')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (self::normalizeTopTypeValue($state) !== 'credit_days') {
                                    $set('tempo_hutang', 0);
                                }
                            })
                            ->dehydrated(),
                        TextInput::make('tempo_hutang')
                            ->label('Credit Days')
                            ->helperText('Dipakai hanya jika TOP = Credit ... Days')
                            ->numeric()
                            ->default(0)
                            ->visible(fn (Get $get) => self::normalizeTopTypeValue($get('top_type') ?? null) === 'credit_days')
                            ->reactive()
                            ->dehydrated(),
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
                        Toggle::make('is_import')
                            ->label('Pembelian Import?')
                            ->helperText('Aktifkan untuk menandai pembelian impor sehingga pajak impor dicatat saat pembayaran')
                            ->reactive(),
                        Repeater::make('purchaseOrderItem')
                            ->relationship()
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                                $currencyId = is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;
                                $preview = self::calculateCurrencyPreview(
                                    (float) ($data['quantity'] ?? 0),
                                    (float) ($data['unit_price'] ?? 0),
                                    (float) ($data['discount'] ?? 0),
                                    (float) ($data['tax'] ?? 0),
                                    self::normalizeTaxTypeValue($data['tipe_pajak'] ?? null),
                                    $currencyId
                                );

                                $data['subtotal'] = self::formatCurrencyPreviewState($preview['subtotal'], $currencyId);

                                return $data;
                            })
                            ->columnSpanFull()
                            ->columns(4)
                            ->hint('Tambahkan item pembelian yang akan diinput')
                            ->defaultItems(0)
                            ->addAction(function (ActionsAction $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Order Items');
                            })
                            ->schema([
                                Hidden::make('refer_item_model_type')
                                    ->dehydrated(true),
                                Hidden::make('refer_item_model_id')
                                    ->dehydrated(true),
                                Select::make('product_id')
                                    ->label('Product')
                                     ->options(function (Get $get) {
                                        return Product::orderBy('name')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($product) {
                                                return [$product->id => "({$product->sku}) {$product->name}"];
                                            });
                                    })
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return Product::where(function ($q) use ($search) {
                                            $q->where('name', 'like', "%{$search}%")
                                                ->orWhere('sku', 'like', "%{$search}%");
                                        })
                                            ->orderBy('name')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($product) {
                                                return [$product->id => "({$product->sku}) {$product->name}"];
                                            });
                                    })
                                    ->helperText('Menampilkan semua produk. Harga otomatis terisi dari harga supplier jika terhubung, atau Rp 0 jika tidak terhubung.')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                        if ($product) {
                                            $rawTipePajak = $product->tipe_pajak ?? 'inklusif';
                                            $newTipePajak = self::normalizeTaxTypeValue($rawTipePajak);
                                            $taxType = match ($newTipePajak) {
                                                'none' => 'None',
                                                'inklusif' => 'PPN Included',
                                                default => 'PPN Excluded',
                                            };
                                            $newTax = \App\Support\TaxDefaultResolver::resolveForProductId((int) $product->id, $taxType);
                                            // Use supplier price from product_supplier pivot; Rp 0 if supplier not linked
                                            $supplierId = $get('../../supplier_id');
                                                $newUnitPrice = 0.0;
                                            if ($supplierId) {
                                                $supplierProduct = $product->suppliers()->where('suppliers.id', $supplierId)->first();
                                                if ($supplierProduct) {
                                                    $newUnitPrice = MoneyHelper::parseHighPrecision($supplierProduct->pivot->supplier_price);
                                                }
                                            }
                                            $itemCurrencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                            $newUnitPrice = CurrencyConversionResolver::convertFromIdr($newUnitPrice, $itemCurrencyId, false);
                                            $newUnitPrice = self::isIdrCurrency($itemCurrencyId)
                                                ? round((float) $newUnitPrice, 2)
                                                : (float) $newUnitPrice;
                                            $set('unit_price', self::formatCurrencyInputState($newUnitPrice, $itemCurrencyId));
                                            $set('unit', $product->uom?->abbreviation ?? '-');
                                            $set('discount', 0);
                                            $set('tax', $newTax);
                                            $set('tipe_pajak', $newTipePajak);
                                            $referItem = null;
                                            if ($get('../../refer_model_type') === 'App\\Models\\OrderRequest') {
                                                $referItem = self::resolveOrderRequestItemReference(
                                                    (int) $get('../../refer_model_id'),
                                                    (int) $state,
                                                    $supplierId ? (int) $supplierId : null
                                                );
                                            }

                                            $set('refer_item_model_type', $referItem ? OrderRequestItem::class : null);
                                            $set('refer_item_model_id', $referItem?->id);
                                            // Use local variables (not $get) to avoid stale state after $set
                                            $preview = self::calculateCurrencyPreview((float)$get('quantity'), $newUnitPrice, 0, $newTax, $newTipePajak, $itemCurrencyId);
                                            $set('total', self::formatCurrencyPreviewState($preview['total'], $itemCurrencyId));
                                            $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $itemCurrencyId));
                                        } else {
                                            $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                            $preview = self::calculateCurrencyPreview((float)$get('quantity'), self::parseCurrencyState($get('unit_price')), (float)$get('discount'), (float)$get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null), $currencyId);
                                            $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                            $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                            $set('refer_item_model_type', null);
                                            $set('refer_item_model_id', null);
                                        }
                                    })
                                    ->required(),
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
                                    ->afterStateUpdated(function (Set $set, Get $get, $state, $old) {
                                        $newCurrencyId = is_numeric($state) ? (int) $state : null;
                                        $oldCurrencyId = is_numeric($old) ? (int) $old : null;

                                        if ($newCurrencyId !== $oldCurrencyId) {
                                            $currentUnitPrice = MoneyHelper::parseHighPrecision($get('unit_price') ?? 0);
                                            $convertedUnitPrice = CurrencyConversionResolver::convertBetweenCurrencies(
                                                $currentUnitPrice,
                                                $oldCurrencyId,
                                                $newCurrencyId,
                                                false
                                            );
                                            $convertedUnitPrice = self::isIdrCurrency($newCurrencyId)
                                                ? round((float) $convertedUnitPrice, 2)
                                                : (float) $convertedUnitPrice;

                                            $preview = self::calculateCurrencyPreview((float) $get('quantity'), $convertedUnitPrice, (float) $get('discount'), (float) $get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null), $newCurrencyId);
                                            $set('unit_price', self::formatCurrencyInputState($convertedUnitPrice, $newCurrencyId));
                                            $set('total', self::formatCurrencyPreviewState($preview['total'], $newCurrencyId));
                                            $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $newCurrencyId));
                                        }

                                        // Ensure this currency is added to purchaseOrderCurrency if not already present
                                        $currencies = $get('../../purchaseOrderCurrency') ?? [];

                                        $currencyExists = false;

                                        foreach ($currencies as $currency) {
                                            if (($currency['currency_id'] ?? null) == $state) {
                                                $currencyExists = true;
                                                break;
                                            }
                                        }

                                        if (!$currencyExists && $state) {
                                            $currencies[] = [
                                                'currency_id' => $state,
                                                'nominal' => 0
                                            ];
                                            $set('../../purchaseOrderCurrency', $currencies);
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->default(0)
                                    ->reactive()
                                    ->helperText(function (Get $get) {
                                        $orItemId = $get('refer_item_model_id');
                                        if (!$orItemId) return null;
                                        $orItem = \App\Models\OrderRequestItem::find($orItemId);
                                        if (!$orItem) return null;
                                        $max = max(0, $orItem->quantity - ($orItem->fulfilled_quantity ?? 0));
                                        return "Maks: {$max} (sisa OR)";
                                    })
                                    ->rules([function (Get $get, $record) {
                                        return function ($attribute, $value, $fail) use ($get, $record) {
                                            $orItemId = $get('refer_item_model_id');
                                            if (!$orItemId) return;
                                            $orItem = \App\Models\OrderRequestItem::find($orItemId);
                                            if (!$orItem) return;
                                            $existing = $record?->quantity ?? 0; // qty already on this PO item
                                            $max = max(0, $orItem->quantity - ($orItem->fulfilled_quantity ?? 0) + (float) $existing);
                                            if ((float) $value > $max) {
                                                $fail("Qty tidak boleh melebihi sisa Order Request ({$max}).");
                                            }
                                        };
                                    }])
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                        $qty = (float)$get('quantity');
                                        $price = self::parseCurrencyState($get('unit_price'));
                                        $preview = self::calculateCurrencyPreview($qty, $price, (float)$get('discount'), (float)$get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null), $currencyId);
                                        $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    }),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->reactive()
                                    ->required()
                                    ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 10)
        JS))
                                    ->formatStateUsing(function ($state, Get $get) {
                                        if ($state === null || $state === '') {
                                            return '';
                                        }
                                        return self::formatCurrencyInputState($state, is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        if ($state === null || $state === '') {
                                            return null;
                                        }
                                        return self::parseCurrencyState($state);
                                    })
                                    ->validationMessages([
                                        'required' => 'Unit price tidak boleh kosong',
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                        $qty = (float)$get('quantity');
                                        $price = self::parseCurrencyState($get('unit_price'));
                                        $preview = self::calculateCurrencyPreview($qty, $price, (float)$get('discount'), (float)$get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null), $currencyId);
                                        $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    }),
                                TextInput::make('total')
                                    ->label('Total (Harga × Qty)')
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->formatStateUsing(function ($state, Get $get) {
                                        if ($state === null || $state === '') {
                                            return '';
                                        }

                                        return self::formatCurrencyPreviewState($state, is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $currencyId = $record->currency_id ?? null;
                                            $total = (float)$record->quantity * (float)$record->unit_price;
                                            $component->state(self::formatCurrencyPreviewState($total, $currencyId));
                                        }
                                    }),
                                TextInput::make('discount')
                                    ->label('Discount (%)')
                                    ->reactive()
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->validationMessages([
                                        'required' => 'Discount tidak boleh kosong. Minimal 0',
                                        'numeric' => 'Discount harus berupa angka.',
                                        'min' => 'Discount tidak boleh negatif.',
                                        'max' => 'Discount maksimal 100%.',
                                    ])
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                        $preview = self::calculateCurrencyPreview((float)$get('quantity'), self::parseCurrencyState($get('unit_price')), (float)$get('discount'), (float)$get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null), $currencyId);
                                        $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->suffix('%')
                                    ->default(0),
                                TextInput::make('tax')
                                    ->label('Tax (%)')
                                    ->reactive()
                                    ->numeric()
                                    ->maxValue(100)
                                    ->required()
                                    ->disabled()
                                    ->helperText(fn(Get $get) => match ($get('tipe_pajak')) {
                                        'inklusif' => 'Pajak sudah termasuk dalam harga satuan',
                                        'eklusif' => 'Pajak akan ditambahkan ke harga satuan',
                                        'none' => 'Non Pajak — otomatis 0',
                                        default     => 'Pilih Tipe Pajak terlebih dahulu',
                                    })
                                    ->validationMessages([
                                        'required' => 'Tax tidak boleh kosong, Minimal 0'
                                    ])
                                    ->dehydrated(true)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $tipePajak = self::normalizeTaxTypeValue($get('tipe_pajak') ?? null);
                                        $effectiveTax = $tipePajak === 'none' ? 0 : (float)$get('tax');
                                        $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                        $preview = self::calculateCurrencyPreview((float)$get('quantity'), self::parseCurrencyState($get('unit_price')), (float)$get('discount'), $effectiveTax, $tipePajak, $currencyId);
                                        $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->suffix('%')
                                    ->default(fn() => \App\Models\TaxSetting::activeRate('PPN')),
                                TextInput::make('subtotal')
                                    ->label('Sub Total (termasuk pajak)')
                                    ->reactive()
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->default(0)
                                    ->readOnly()
                                    ->formatStateUsing(function ($state, Get $get) {
                                        if ($state === null || $state === '') {
                                            return '';
                                        }

                                        return self::formatCurrencyPreviewState($state, is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->afterStateHydrated(function ($component, $record) {
                                        if (! $record) {
                                            return;
                                        }

                                        $currencyId = is_numeric($record->currency_id ?? null) ? (int) $record->currency_id : null;
                                        $preview = self::calculateCurrencyPreview(
                                            (float) ($record->quantity ?? 0),
                                            (float) ($record->unit_price ?? 0),
                                            (float) ($record->discount ?? 0),
                                            (float) ($record->tax ?? 0),
                                            self::normalizeTaxTypeValue($record->tipe_pajak ?? null),
                                            $currencyId
                                        );

                                        $component->state(self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
                                        $items = $livewire->data['purchaseOrderItem'] ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $itemSubtotal = \App\Http\Controllers\HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                self::parseCurrencyState($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                            // Convert item subtotal to IDR using purchaseOrderCurrency nominal
                                            $itemNominal = 1.0;
                                            if (!empty($item['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $item['currency_id']) {
                                                        $itemNominal = (float)($c['nominal'] ?? 1.0);
                                                        if ($itemNominal <= 0) $itemNominal = 1.0;
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += $itemSubtotal * $itemNominal;
                                        }

                                        // Add biaya amounts converted using purchaseOrderCurrency.nominal
                                        $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
                                        foreach ($biayas as $biaya) {
                                            $nominal = 1.0;
                                            if (isset($biaya['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                                                        $nominal = (float)($c['nominal'] ?? 1.0);
                                                        if ($nominal <= 0) $nominal = 1.0;
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += self::parseCurrencyState($biaya['total'] ?? 0) * $nominal;
                                        }

                                        $livewire->data['total_amount'] = self::formatMoneyState($total);
                                    }),
                                Radio::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->inline()
                                    ->reactive()
                                    ->required()
                                    ->default('inklusif')
                                    ->dehydrated(true)
                                    ->disabled(fn(Get $get) => ($get('../../ppn_option') ?? 'standard') === 'non_ppn')
                                    ->options([
                                        'none' => 'Non Pajak',
                                        'inklusif'  => 'Inklusif (PPN termasuk)',
                                        'eklusif' => 'Eksklusif (PPN ditambahkan)',
                                    ])
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $defaultTax = \App\Models\TaxSetting::activeRate('PPN');
                                        $normalizedState = self::normalizeTaxTypeValue($state);

                                        if ($normalizedState === 'none') {
                                            $set('tax', 0);
                                        } else {
                                            $set('tax', $defaultTax);
                                        }
                                        $effectiveTax = $normalizedState === 'none' ? 0 : (float)$get('tax');
                                        // Recalculate subtotal when tax type changes
                                        $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                        $preview = self::calculateCurrencyPreview((float)$get('quantity'), self::parseCurrencyState($get('unit_price')), (float)$get('discount'), $effectiveTax, $normalizedState, $currencyId);
                                        $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->validationMessages([
                                        'required' => 'Tipe Pajak belum dipilih'
                                    ]),
                                Placeholder::make('tax_breakdown')
                                    ->label('Rincian Pajak')
                                    ->columnSpanFull()
                                    ->content(function (Get $get) {
                                        $qty       = (float)($get('quantity') ?? 0);
                                        $unitPrice = self::parseCurrencyState($get('unit_price'));
                                        $discount  = (float)($get('discount') ?? 0);
                                        $taxRate   = (float)($get('tax') ?? 0);
                                        $tipePajak = self::normalizeTaxTypeValue($get('tipe_pajak') ?? null);
                                        $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;

                                        if ($qty <= 0 || $unitPrice <= 0) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="text-xs text-gray-400">Masukkan quantity dan harga untuk melihat rincian pajak.</span>'
                                            );
                                        }

                                        $gross     = $qty * $unitPrice;
                                        $discAmt   = $gross * $discount / 100;
                                        $afterDisc = $gross - $discAmt;
                                        $fmt       = fn(float $n) => CurrencyConversionResolver::resolveSymbol($currencyId) . ' ' . self::formatCurrencyPreviewState($n, $currencyId);

                                        $preview = self::calculateCurrencyPreview($qty, $unitPrice, $discount, $taxRate, $tipePajak, $currencyId);
                                        $normalizedType = \App\Services\TaxService::normalizeType($tipePajak);
                                        $ppn = (float) $preview['tax_nominal'];
                                        $total = (float) $preview['subtotal'];
                                        $dpp = $normalizedType === 'Inklusif'
                                            ? max(0, $afterDisc - $ppn)
                                            : $afterDisc;

                                        if ($normalizedType === 'Non Pajak' || $taxRate <= 0) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="text-sm text-gray-600 py-1">' .
                                                    '<span class="font-semibold">&#9899; Non Pajak</span> &mdash; Tidak ada PPN. ' .
                                                    'DPP: <strong>' . $fmt($dpp) . '</strong> &nbsp;|&nbsp; PPN: <strong>' . $fmt(0) . '</strong> &nbsp;|&nbsp; Total: <strong>' . $fmt($total) . '</strong>' .
                                                    '</div>'
                                            );
                                        }

                                        if ($normalizedType === 'Eksklusif') {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="text-sm text-orange-700 py-1">' .
                                                    '<span class="font-semibold">&#9650; Eksklusif</span> &mdash; PPN <em>ditambahkan</em> ke harga:<br>' .
                                                    'DPP <strong>' . $fmt($dpp) . '</strong> + PPN ' . $taxRate . '% <strong>' . $fmt($ppn) . '</strong>' .
                                                    ' = Total <strong>' . $fmt($total) . '</strong>' .
                                                    '</div>'
                                            );
                                        }

                                        // Inklusif
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="text-sm text-green-700 py-1">' .
                                                '<span class="font-semibold">&#9989; Inklusif</span> &mdash; PPN sudah <em>termasuk</em> dalam harga:<br>' .
                                                'Total <strong>' . $fmt($total) . '</strong>' .
                                                ' (DPP <strong>' . $fmt($dpp) . '</strong> + PPN ' . $taxRate . '% <strong>' . $fmt($ppn) . '</strong>)' .
                                                '</div>'
                                        );
                                    }),
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
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Auto-suggest COA based on cost name keywords
                                        if (empty($state)) {
                                            return;
                                        }
                                        // Only suggest if COA is not already selected
                                        if ($get('coa_id')) {
                                            return;
                                        }
                                        $lower = strtolower(trim($state));
                                        $keywords = [
                                            'freight|pengiriman|ongkir|kirim|cargo|ekspedisi|angkut|transport' => 'Biaya Pengiriman',
                                            'handling|bongkar|muat|loading|unloading'                         => 'Biaya Handling',
                                            'customs|bea masuk|import|pajak impor|cukai'                      => 'Biaya Bea Masuk',
                                            'asuransi|insurance'                                              => 'Biaya Asuransi',
                                            'instalasi|install|pemasangan'                                    => 'Biaya Instalasi',
                                            'konsultasi|konsultan|jasa'                                       => 'Biaya Jasa',
                                        ];
                                        foreach ($keywords as $pattern => $coaName) {
                                            if (preg_match('/(' . $pattern . ')/', $lower)) {
                                                $coa = ChartOfAccount::where('type', 'Expense')
                                                    ->where(function ($q) use ($coaName) {
                                                        $q->where('name', 'like', '%' . $coaName . '%')
                                                            ->orWhere('name', 'like', '%' . explode(' ', $coaName)[1] . '%');
                                                    })
                                                    ->first();
                                                if ($coa) {
                                                    $set('coa_id', $coa->id);
                                                    return;
                                                }
                                            }
                                        }
                                        // Fallback – pick first general purchase/expense COA
                                        $fallback = ChartOfAccount::where('type', 'Expense')
                                            ->whereRaw('LOWER(name) LIKE ?', ['%biaya%'])
                                            ->first();
                                        if ($fallback) {
                                            $set('coa_id', $fallback->id);
                                        }
                                    })
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
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $currencies = $get('../../purchaseOrderCurrency') ?? [];
                                        $currencyExists = collect($currencies)->contains(fn ($currency) => ($currency['currency_id'] ?? null) == $state);

                                        if (! $currencyExists) {
                                            $currencies[] = [
                                                'currency_id' => $state,
                                                'nominal' => CurrencyConversionResolver::resolveRate(is_numeric($state) ? (int) $state : null),
                                            ];
                                            $set('../../purchaseOrderCurrency', $currencies);
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                Select::make('coa_id')
                                    ->label('COA Biaya')
                                    ->helperText('Otomatis disarankan berdasarkan nama biaya. Dapat diubah secara manual.')
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
                                    ])
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->dehydrateStateUsing(fn ($state) => self::parseCurrencyState($state ?? 0))
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
                                        $items = $livewire->data['purchaseOrderItem'] ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $itemSubtotal = \App\Http\Controllers\HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                self::parseCurrencyState($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                            // Convert item subtotal to IDR using purchaseOrderCurrency nominal
                                            $itemNominal = 1.0;
                                            if (!empty($item['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $item['currency_id']) {
                                                        $itemNominal = (float)($c['nominal'] ?? 1.0);
                                                        if ($itemNominal <= 0) $itemNominal = 1.0;
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += $itemSubtotal * $itemNominal;
                                        }

                                        $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
                                        foreach ($biayas as $biaya) {
                                            $nominal = 1.0;
                                            if (isset($biaya['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                                                        $nominal = (float)($c['nominal'] ?? 1.0);
                                                        if ($nominal <= 0) $nominal = 1.0;
                                                        break;
                                                    }
                                                }
                                            }
                                            $biayaTotal = (float) self::parseCurrencyState($biaya['total'] ?? 0);
                                            $total += $biayaTotal * $nominal;
                                        }

                                        $livewire->data['total_amount'] = self::formatMoneyState($total);
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
                            ->hidden()
                            ->dehydrated(true)
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
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Auto-fill nominal from Currency.to_rupiah master rate
                                        if ($state) {
                                            $rate = CurrencyConversionResolver::resolveRate(is_numeric($state) ? (int) $state : null);
                                            if ($rate > 0) {
                                                // Cast to float to prevent indonesianMoney dehydrate from treating
                                                // decimal strings like "1.00" as "100" (stripping the decimal dot)
                                                $set('nominal', $rate);
                                            }
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                TextInput::make('nominal')
                                    ->label('Nominal (Kurs ke IDR)')
                                    ->helperText('Nilai tukar mata uang terhadap IDR. Otomatis terisi dari master, bisa disesuaikan.')
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
                                        $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
                                        $items = $livewire->data['purchaseOrderItem'] ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $itemSubtotal = \App\Http\Controllers\HelperController::hitungSubtotal(
                                                $item['quantity'] ?? 0,
                                                self::parseCurrencyState($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                $item['tipe_pajak'] ?? null
                                            );
                                            // Convert item subtotal to IDR using purchaseOrderCurrency nominal
                                            $itemNominal = 1.0;
                                            if (!empty($item['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $item['currency_id']) {
                                                        $itemNominal = (float)($c['nominal'] ?? 1.0);
                                                        if ($itemNominal <= 0) $itemNominal = 1.0;
                                                        break;
                                                    }
                                                }
                                            }
                                            $total += $itemSubtotal * $itemNominal;
                                        }

                                        $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
                                        foreach ($biayas as $biaya) {
                                            $nominal = 1.0;
                                            if (isset($biaya['currency_id'])) {
                                                foreach ($currencies as $c) {
                                                    if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                                                        $nominal = (float)($c['nominal'] ?? 1.0);
                                                        if ($nominal <= 0) $nominal = 1.0;
                                                        break;
                                                    }
                                                }
                                            }
                                            $biayaTotal = (float) self::parseCurrencyState($biaya['total'] ?? 0);
                                            $total += $biayaTotal * $nominal;
                                        }

                                        $livewire->data['total_amount'] = self::formatMoneyState($total);
                                    }),
                            ]),
                        TextInput::make('total_amount')
                            ->label("Total Amount")
                            ->required()
                            ->reactive()
                            ->hidden()
                            ->dehydrateStateUsing(fn ($state) => self::parseCurrencyState($state ?? 0))
                            ->helperText('Total dihitung dari item dan biaya; tampil untuk referensi saja')
                            ->afterStateHydrated(function ($component, $record) {
                                if (! $record) {
                                    return;
                                }

                                $total = 0;
                                // Load purchaseOrderCurrency for nominal (custom rate) lookup
                                $record->loadMissing('purchaseOrderCurrency');
                                $poCurrencies = $record->purchaseOrderCurrency->keyBy('currency_id');

                                foreach ($record->purchaseOrderItem as $item) {
                                    $itemSubtotal = \App\Http\Controllers\HelperController::hitungSubtotal(
                                        (float)$item->quantity,
                                        (float)$item->unit_price,
                                        (float)$item->discount,
                                        (float)$item->tax,
                                        $item->tipe_pajak
                                    );
                                    // Use purchaseOrderCurrency.nominal (custom PO rate), fallback to Currency.to_rupiah
                                    $poCurrency = $poCurrencies->get($item->currency_id);
                                    $itemNominal = ($poCurrency && (float)$poCurrency->nominal > 0)
                                        ? (float)$poCurrency->nominal
                                        : CurrencyConversionResolver::resolveRate((int) ($item->currency_id ?? null));
                                    $total += $itemSubtotal * $itemNominal;
                                }

                                foreach ($record->purchaseOrderBiaya as $biaya) {
                                    $poCurrency = $poCurrencies->get($biaya->currency_id);
                                    $biayaNominal = ($poCurrency && (float)$poCurrency->nominal > 0)
                                        ? (float)$poCurrency->nominal
                                            : CurrencyConversionResolver::resolveRate((int) ($biaya->currency_id ?? null));
                                    $total += (float) $biaya->total * $biayaNominal;
                                }
                                $component->state(self::formatMoneyState($total));
                            }),

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                $query->orderByDesc('order_date');
            })
            ->columns([
                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('supplier', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('perusahaan', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->perusahaan}";
                    }),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('referModel.request_number')
                    ->label('Request Number')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->where(function (Builder $query) use ($search) {
                            $query->where('refer_model_type', OrderRequest::class)
                                ->whereHasMorph('referModel', [OrderRequest::class], function (Builder $query) use ($search) {
                                    $query->where('request_number', 'LIKE', "%{$search}%");
                                });
                        });
                    })
                    ->formatStateUsing(fn($state, $record) => $record->referModel?->request_number ?? '-'),
                IconColumn::make('is_import')
                    ->label('Import?')
                    ->boolean()
                    ->tooltip(fn($state) => $state ? 'Pembelian import (pajak dicatat saat pembayaran)' : 'Pembelian lokal'),
                TextColumn::make('ppn_option')
                    ->label('Tipe Pajak')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'non_ppn'  => 'Non Pajak',
                            'inklusif' => 'Inklusif',
                            'eklusif'  => 'Eklusif',
                            default    => 'PPN',
                        };
                    })
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'non_ppn'  => 'warning',
                        'inklusif' => 'info',
                        default    => 'success',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        return self::formatStatusLabel($state);
                    })
                    ->color(fn ($state) => self::getStatusColor($state))
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
                    ->formatStateUsing(function ($state, $record) {
                        return self::formatMoneyState($state, $record->currency_id ?? null);
                    })
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
                TextColumn::make('item_units')
                    ->label('Satuan')
                    ->state(function ($record) {
                        return $record->purchaseOrderItem
                            ->map(fn($item) => $item->product?->uom?->abbreviation ?? '-')
                            ->filter()
                            ->unique()
                            ->implode(', ');
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->recordClasses(fn($record) => match ($record->status) {
                'draft' => '',
                'approved' => 'bg-blue-100',
                'partially_received' => 'bg-yellow-100',
                'request_close' => 'bg-red-100',
                'closed' => 'bg-red-100',
                'completed' => 'bg-green-100',
                'paid' => 'bg-green-100',
                default => '',
            })
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Purchase Order</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Purchase Order (PO) adalah instruksi pembelian resmi ke supplier.</li>' .
                    '<li><strong>Membuat PO:</strong> PO dapat dibuat dari Order Request atau Sales Order, atau dibuat manual lewat tombol Create PO.</li>' .
                    '<li><strong>Alur baru (QC First):</strong> Setelah PO dibuat (langsung <em>Approved</em>), lanjutkan ke <strong>Quality Control</strong> untuk inspeksi barang. Setelah QC lulus, Purchase Receipt akan dibuat otomatis dan stok diperbarui.</li>' .
                    '<li><strong>Dampak Status Completed:</strong> PO berstatus <em>completed</em> menandakan semua barang telah melewati QC dan diterima; selanjutnya proses invoice dan pembayaran dapat dilanjutkan.</li>' .
                    '<li><strong>Catatan:</strong> PO dibuat dalam status <em>Draft</em> — perlu disetujui melalui tombol <em>Setujui PO</em> sebelum dapat diproses lebih lanjut. Tindakan <em>close</em> memerlukan hak akses tertentu.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status PO')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'partially_received' => 'Partially Received',
                        'completed' => 'Completed',
                        'paid' => 'Paid',
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
                    ->label('Tipe Pajak')
                    ->options([
                        'non_ppn'  => 'Non Pajak',
                        'inklusif' => 'Inklusif',
                        'eklusif'  => 'Eklusif',
                    ])
                    ->placeholder('Semua Tipe Pajak'),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'perusahaan')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                        return "({$supplier->code}) {$supplier->perusahaan}";
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
                                fn(Builder $query, $date): Builder => $query->whereDate('order_date', '>=', $date),
                            )
                            ->when(
                                $data['order_date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('order_date', '<=', $date),
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
                                && $record->status == 'request_close';
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

                            return null;
                        })
                        ->action(function (array $data, $record) {
                            if ($record->status == 'request_close') {
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
                                && $record->status == 'request_close';
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'draft'
                            ]);
                        }),
                    Action::make('approve_po')
                        ->label('Setujui PO')
                        ->visible(function ($record) {
                            return Gate::allows('response purchase order')
                                && $record->status === 'draft';
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Setujui Purchase Order')
                        ->modalDescription('Apakah Anda yakin ingin menyetujui Purchase Order ini?')
                        ->modalSubmitActionLabel('Ya, Setujui')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($record) {
                            try {
                                $poService = app(PurchaseOrderService::class);
                                $poService->approvePo($record);
                                $record->refresh();
                                \Filament\Notifications\Notification::make()
                                    ->title('Purchase Order Disetujui')
                                    ->body('PO ' . $record->po_number . ' berhasil disetujui.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $exception) {
                                \App\Support\ProcurementFailureNotifier::danger(
                                    'Gagal Menyetujui PO',
                                    $exception,
                                    'Purchase Order belum dapat disetujui. Silakan coba lagi.'
                                );
                            }
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
                            ])->setPaper('A4', 'portrait');

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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total amount berhasil disinkronkan. Proses selanjutnya: Pastikan semua data Purchase Order sudah benar sebelum mengajukan untuk disetujui.");
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
                                                $otherFee += ($biaya->total * CurrencyConversionResolver::resolveRate((int) ($biaya->currency_id ?? null)));
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Generate invoice berhasil. Proses selanjutnya: Tim Finance perlu memproses pembayaran terhadap Invoice yang telah diterbitkan sesuai jatuh tempo.");
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

    /**
     * Build PurchaseOrderItem array from an OrderRequest.
     * When $filterSupplierId is given, only items with matching supplier_id are included.
     * This supports the 1-PO-per-supplier rule when creating a manual PO from a multisupplier OR.
     */
    public static function buildOrderRequestItems(
        OrderRequest $orderRequest,
        ?int $filterSupplierId = null,
        ?int $filterCabangId = null,
        ?int $defaultCurrencyId = null
    ): array {
        if (func_num_args() === 3 && $defaultCurrencyId === null) {
            $defaultCurrencyId = $filterCabangId;
            $filterCabangId = null;
        }

        $defaultCurrencyId ??= Currency::query()->first()?->id;
        $items = [];

        foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
            // Skip items that belong to a different supplier when a filter is active
            if ($filterSupplierId !== null
                && $orderRequestItem->supplier_id !== null
                && (int) $orderRequestItem->supplier_id !== $filterSupplierId
            ) {
                continue;
            }

            $resolvedCabangId = static::resolveOrderRequestItemCabangId($orderRequestItem, $orderRequest);
            if ($filterCabangId !== null && $resolvedCabangId !== null && (int) $resolvedCabangId !== $filterCabangId) {
                continue;
            }

            // Use the greater of fulfilled_quantity (from receipts) and locked_quantity (from approved POs)
            // to ensure we don't pre-fill items that are already covered by an existing approved PO.
            $fulfilledQty  = (float) ($orderRequestItem->fulfilled_quantity ?? 0);
            $lockedQty     = static::getLockedQuantityForOrderRequestItem((int) $orderRequestItem->id);
            $accountedQty  = max($fulfilledQty, $lockedQty);
            $remainingQuantity = max(0, (float) $orderRequestItem->quantity - $accountedQty);
            if ($remainingQuantity <= 0) {
                continue;
            }

            if (($orderRequestItem->unit_price ?? 0) > 0) {
                $unitPrice = (float) $orderRequestItem->unit_price;
            } else {
                $product  = $orderRequestItem->product;
                $spId     = $orderRequestItem->supplier_id;
                $sp       = ($product && $spId)
                    ? $product->suppliers()->where('suppliers.id', $spId)->first()
                    : null;
                $unitPrice = $sp ? (float) $sp->pivot->supplier_price : (float) ($product->cost_price ?? 0);
            }

            $discount  = $orderRequestItem->discount ?? 0;
            $tax       = $orderRequestItem->tax ?? 0;
            $tipePajak = self::normalizeTaxTypeValue($orderRequestItem->tipe_pajak ?: ((float) $tax > 0 ? 'eklusif' : 'none'));
            $subtotal = HelperController::hitungSubtotal($remainingQuantity, $unitPrice, $discount, $tax, $tipePajak);

            // Inherit currency from OR item, fallback to OR header, then default
            $itemCurrencyId = $orderRequestItem->currency_id ?? $orderRequest->currency_id ?? $defaultCurrencyId;

            $items[] = [
                'product_id'            => $orderRequestItem->product_id,
                'quantity'              => $remainingQuantity,
                'unit_price'            => $unitPrice,
                'discount'              => $discount,
                'tax'                   => $tax,
                'tipe_pajak'            => $tipePajak,
                'subtotal'              => $subtotal,
                'currency_id'           => $itemCurrencyId,
                'refer_item_model_type' => \App\Models\OrderRequestItem::class,
                'refer_item_model_id'   => $orderRequestItem->id,
                'unit'                  => $orderRequestItem->product->uom?->abbreviation ?? '-',
                'cabang_id'             => $resolvedCabangId,
            ];
        }

        return $items;
    }

    /**
     * Return Order Request supplier IDs that still have items with remaining quantity
     * (i.e., quantity not yet covered by approved POs or fulfilled receipts).
     *
     * NOTE: We no longer simply block a supplier because they already have a PO.
     * A supplier remains "available" as long as any of their OR items still have
     * qty > (locked in approved POs + already received). This allows partial POs
     * to be followed by additional POs for the remaining qty.
     */
    public static function getAvailableOrderRequestSupplierIds(OrderRequest $orderRequest): array
    {
        return collect(static::getAvailableOrderRequestItemGroups($orderRequest))
            ->pluck('supplier_id')
            ->filter()
            ->map(fn ($supplierId) => (int) $supplierId)
            ->unique()
            ->values()
            ->all();
    }

    public static function getRelations(): array
    {
        return [
            PurchaseOrderItemRelationManager::class
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'supplier',
                'purchaseOrderItem.product',
                'purchaseReceipt',
            ])
            ->withCount('purchaseOrderItem');
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
