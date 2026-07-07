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
use App\Rules\InternationalPhoneNumber;
use App\Services\AssetService;
use App\Services\InvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\QualityControlService;
use App\Services\PurchaseReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\CurrencyConversionResolver;
use App\Support\OrderRequestQuantityLock;
use App\Helpers\MoneyHelper;
use App\Support\TaxTypeHelper;
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
use Illuminate\Support\Facades\Schema;
use Saade\FilamentAutograph\Forms\Components\SignaturePad as ComponentsSignaturePad;

class PurchaseOrderResource extends Resource
{
    public const RESOURCE_PO_EXCLUDED_STATUSES = ['closed', 'cancelled', 'canceled', 'rejected'];

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

        if (empty($normalized)) {
            return '';
        }

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
            ->filter(fn($row) => is_numeric($row['currency_id'] ?? null))
            ->mapWithKeys(fn($row) => [(int) $row['currency_id'] => $row['nominal'] ?? null]);

        $currencyIds = collect($data['purchaseOrderItem'] ?? [])
            ->pluck('currency_id')
            ->filter(fn($currencyId) => is_numeric($currencyId))
            ->map(fn($currencyId) => (int) $currencyId)
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

        return self::normalizeOrderRequestBackedItemTaxTypes($data);
    }

    public static function normalizeOrderRequestBackedItemTaxTypes(array $data): array
    {
        if (empty($data['purchaseOrderItem']) || ! is_array($data['purchaseOrderItem'])) {
            return $data;
        }

        foreach ($data['purchaseOrderItem'] as &$item) {
            $referItemType = $item['refer_item_model_type'] ?? null;
            $referItemId = $item['refer_item_model_id'] ?? null;

            if ($referItemType === OrderRequestItem::class && is_numeric($referItemId)) {
                $orderRequestItem = OrderRequestItem::withoutGlobalScopes()->find((int) $referItemId);

                if ($orderRequestItem) {
                    $item['tipe_pajak'] = TaxTypeHelper::normalize($orderRequestItem->tipe_pajak);
                }
            } else {
                $item['tipe_pajak'] = TaxTypeHelper::normalize($item['tipe_pajak'] ?? null);
            }

            if (
                array_key_exists('quantity', $item)
                && array_key_exists('unit_price', $item)
                && array_key_exists('tax', $item)
            ) {
                $item['subtotal'] = HelperController::hitungSubtotal(
                    (float) ($item['quantity'] ?? 0),
                    self::parseCurrencyState($item['unit_price'] ?? 0),
                    (float) ($item['discount'] ?? 0),
                    (float) ($item['tax'] ?? 0),
                    $item['tipe_pajak'] ?? null
                );
            }
        }
        unset($item);

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

        return self::formatCurrencyInputState($amount, $currencyId);
    }

    public static function renderLargePurchaseOrderItemSummary(
        array $items,
        ?string $search = null,
        ?string $taxFilter = null,
        ?string $sourceFilter = null,
        mixed $cabangFilter = null
    ): \Illuminate\Support\HtmlString {
        $search = trim((string) $search);
        $taxFilter = filled($taxFilter) ? self::normalizeTaxTypeValue($taxFilter) : null;
        $sourceFilter = filled($sourceFilter) ? (string) $sourceFilter : null;
        $cabangFilter = filled($cabangFilter) ? (int) $cabangFilter : null;

        $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
        $referItemIds = collect($items)->pluck('refer_item_model_id')->filter()->unique()->values();
        $products = Product::withoutGlobalScope('product_cabang')
            ->whereIn('id', $productIds)
            ->get(['id', 'sku', 'name', 'cabang_id'])
            ->keyBy('id');
        $referItems = OrderRequestItem::withoutGlobalScopes()
            ->whereIn('id', $referItemIds)
            ->get(['id', 'cabang_id'])
            ->keyBy('id');
        $cabangIds = $products->pluck('cabang_id')
            ->merge($referItems->pluck('cabang_id'))
            ->filter()
            ->unique()
            ->values();
        $cabangs = \App\Models\Cabang::whereIn('id', $cabangIds)
            ->get(['id', 'kode', 'nama'])
            ->keyBy('id');

        $totalItems = count($items);
        $totalQty = collect($items)->sum(fn ($item) => (float) ($item['quantity'] ?? 0));
        $totalPreview = collect($items)->sum(function ($item) {
            return MoneyHelper::safeParse($item['subtotal'] ?? $item['total'] ?? 0);
        });

        $matched = collect($items)->filter(function ($item) use ($search, $taxFilter, $sourceFilter, $cabangFilter, $products, $referItems) {
            if ($taxFilter && self::normalizeTaxTypeValue($item['tipe_pajak'] ?? null) !== $taxFilter) {
                return false;
            }

            $isOrderRequestBacked = ($item['refer_item_model_type'] ?? null) === OrderRequestItem::class
                || filled($item['refer_item_model_id'] ?? null);

            if ($sourceFilter === 'order_request' && ! $isOrderRequestBacked) {
                return false;
            }

            if ($sourceFilter === 'manual' && $isOrderRequestBacked) {
                return false;
            }

            $product = $products->get($item['product_id'] ?? null);
            $referItem = $referItems->get($item['refer_item_model_id'] ?? null);
            $resolvedCabangId = $referItem?->cabang_id ?? $product?->cabang_id;

            if ($cabangFilter && (int) $resolvedCabangId !== $cabangFilter) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = Str::lower(implode(' ', array_filter([
                $product?->sku,
                $product?->name,
                $item['tipe_pajak'] ?? null,
                $isOrderRequestBacked ? 'order request or' : 'manual',
            ])));

            return Str::contains($haystack, Str::lower($search));
        });

        $chips = [];
        if ($search !== '') {
            $chips[] = 'Search: ' . e($search);
        }
        if ($taxFilter) {
            $chips[] = 'Tipe Pajak: ' . e(Str::upper($taxFilter));
        }
        if ($sourceFilter) {
            $chips[] = 'Sumber: ' . e($sourceFilter === 'order_request' ? 'Dari Order Request' : 'Manual');
        }
        if ($cabangFilter && ($cabang = $cabangs->get($cabangFilter))) {
            $chips[] = 'Cabang: ' . e("({$cabang->kode}) {$cabang->nama}");
        }

        $hasFilters = ! empty($chips);
        $chipHtml = $hasFilters
            ? collect($chips)->map(fn ($chip) => '<span class="dt-item-chip">' . $chip . '</span>')->implode('')
            : '<span class="dt-item-chip muted">Tidak ada filter aktif</span>';
        $remaining = max(0, $totalItems - min($totalItems, 10));
        $largeMessage = $remaining > 0
            ? '<div class="dt-item-more">Masih ada ' . number_format($remaining, 0, ',', '.') . ' item lainnya. Gunakan search/filter dan Collapse All untuk navigasi cepat.</div>'
            : '';

        return new \Illuminate\Support\HtmlString(sprintf(
            '<style>
                .dt-item-panel{padding:14px;border:1px solid #dbeafe;border-radius:12px;background:#fff;color:#111827;box-shadow:0 1px 2px rgba(15,23,42,.04)}
                .dt-item-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px}
                .dt-item-title{font-weight:700;color:#1d4ed8}
                .dt-item-count{color:#6b7280;font-size:13px}
                .dt-item-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;border-top:1px solid #eef2ff;padding-top:10px}
                .dt-item-metric{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:9px 10px}
                .dt-item-metric strong{display:block;font-size:15px}
                .dt-item-metric span{font-size:12px;color:#6b7280}
                .dt-item-chips{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0 10px}
                .dt-item-chip{display:inline-flex;align-items:center;border-radius:999px;background:#eff6ff;color:#1e40af;padding:5px 10px;font-size:12px;font-weight:600}
                .dt-item-chip.muted{background:#f3f4f6;color:#6b7280}
                .dt-item-more{margin-top:10px;border:1px dashed #c7d2fe;border-radius:10px;padding:10px;text-align:center;color:#374151;background:#f8fafc}
            </style>
            <div class="dt-item-panel">
                <div class="dt-item-toolbar">
                    <div>
                        <div class="dt-item-title">Purchase Order Item</div>
                        <div class="dt-item-count">Showing %s of %s items%s</div>
                    </div>
                    <div class="dt-item-count">Review paginated tetap tersedia di tab item setelah PO tersimpan.</div>
                </div>
                <div class="dt-item-chips"><span style="color:#6b7280;font-size:13px;">Active filters:</span>%s</div>
                <div class="dt-item-grid">
                    <div class="dt-item-metric"><strong>%s</strong><span>Total item</span></div>
                    <div class="dt-item-metric"><strong>%s</strong><span>Total qty</span></div>
                    <div class="dt-item-metric"><strong>%s</strong><span>OR-backed</span></div>
                    <div class="dt-item-metric"><strong>%s</strong><span>Manual</span></div>
                    <div class="dt-item-metric"><strong>Rp %s</strong><span>Total subtotal</span></div>
                </div>
                %s
            </div>',
            number_format($hasFilters ? $matched->count() : min($totalItems, 10), 0, ',', '.'),
            number_format($totalItems, 0, ',', '.'),
            $hasFilters ? ' matched' : '',
            $chipHtml,
            number_format($totalItems, 0, ',', '.'),
            number_format($totalQty, 2, ',', '.'),
            number_format(collect($items)->filter(fn ($item) => filled($item['refer_item_model_id'] ?? null))->count(), 0, ',', '.'),
            number_format(collect($items)->filter(fn ($item) => blank($item['refer_item_model_id'] ?? null))->count(), 0, ',', '.'),
            number_format($totalPreview, 2, ',', '.'),
            $largeMessage
        ));
    }

    public static function usesLargePurchaseOrderItemEditor(array $items, ?string $operation): bool
    {
        return in_array($operation, ['create', 'edit'], true);
    }

    public static function parsePurchaseOrderCurrencyState(mixed $value): float
    {
        return self::parseCurrencyState($value);
    }

    public static function formatPurchaseOrderCurrencyInputState(mixed $amount, ?int $currencyId): string
    {
        return self::formatCurrencyInputState($amount, $currencyId);
    }

    public static function recalculatePurchaseOrderItemPreviewState(array $item): array
    {
        $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = self::parseCurrencyState($item['unit_price'] ?? 0);
        $discount = (float) ($item['discount'] ?? 0);
        $taxType = self::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);
        $tax = $taxType === 'none' ? 0.0 : (float) ($item['tax'] ?? \App\Models\TaxSetting::activeRate('PPN'));

        $preview = self::calculateCurrencyPreview($quantity, $unitPrice, $discount, $tax, $taxType, $currencyId);

        $item['tipe_pajak'] = $taxType;
        $item['tax'] = $tax;
        $item['total'] = self::formatCurrencyPreviewState($preview['total'], $currencyId);
        $item['discount_nominal'] = self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId);
        $item['tax_nominal'] = self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId);
        $item['subtotal'] = self::formatCurrencyPreviewState($preview['subtotal'], $currencyId);

        return $item;
    }

    public static function calculateInlinePurchaseOrderTotalAmount(array $items, array $currencies = []): string
    {
        $rates = collect($currencies)
            ->filter(fn ($row) => is_numeric($row['currency_id'] ?? null))
            ->mapWithKeys(fn ($row) => [(int) $row['currency_id'] => (float) ($row['nominal'] ?? 0)]);

        $total = collect($items)->sum(function (array $item) use ($rates): float {
            $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
            $subtotal = self::parseCurrencyState($item['subtotal'] ?? 0);
            $rate = $currencyId ? (float) $rates->get($currencyId, 0) : 0.0;

            if ($rate <= 0) {
                $rate = CurrencyConversionResolver::resolveRate($currencyId);
            }

            return $subtotal * $rate;
        });

        return self::formatMoneyState($total, null);
    }

    public static function renderPurchaseOrderItemNavigator(
        array $items,
        ?string $search = null,
        ?string $taxFilter = null,
        ?string $sourceFilter = null,
        mixed $cabangFilter = null,
        array $drafts = [],
        array $dirtyItems = [],
        array $validationErrors = []
    ): \Illuminate\Support\HtmlString {
        $search = trim((string) $search);
        $taxFilter = filled($taxFilter) ? self::normalizeTaxTypeValue($taxFilter) : null;
        $sourceFilter = filled($sourceFilter) ? (string) $sourceFilter : null;
        $cabangFilter = filled($cabangFilter) ? (int) $cabangFilter : null;
        $displayItems = collect($items)
            ->map(function ($item, $key) use ($drafts, $dirtyItems) {
                $item = is_array($item) ? $item : [];

                if (is_array($drafts[$key] ?? null)) {
                    $item = $drafts[$key];
                    $item['_is_dirty'] = ! empty($dirtyItems[$key]);
                }

                return $item;
            })
            ->all();

        $baseItemCollection = collect($items)->map(function ($item, $key) {
            $item = is_array($item) ? $item : [];
            $item['_navigator_key'] = (string) $key;

            return $item;
        });

        $itemCollection = collect($displayItems)->map(function ($item, $key) {
            $item = is_array($item) ? $item : [];
            $item['_navigator_key'] = (string) $key;

            return $item;
        });

        $productIds = $itemCollection->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $referItemIds = $itemCollection->pluck('refer_item_model_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $currencyIds = $itemCollection->pluck('currency_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $products = Product::withoutGlobalScope('product_cabang')
            ->whereIn('id', $productIds)
            ->get(['id', 'sku', 'name', 'cabang_id', 'cost_price'])
            ->keyBy('id');
        $referItems = OrderRequestItem::withoutGlobalScopes()
            ->whereIn('id', $referItemIds)
            ->get(['id', 'cabang_id'])
            ->keyBy('id');
        $currencies = Currency::query()
            ->when($currencyIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $currencyIds))
            ->orWhereIn('code', ['IDR'])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'symbol'])
            ->keyBy('id');

        $matched = $itemCollection->filter(function ($item) use ($search, $taxFilter, $sourceFilter, $cabangFilter, $products, $referItems) {
            $taxType = self::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);
            $isOrderRequestBacked = ($item['refer_item_model_type'] ?? null) === OrderRequestItem::class
                || filled($item['refer_item_model_id'] ?? null);
            $product = $products->get($item['product_id'] ?? null);
            $referItem = $referItems->get($item['refer_item_model_id'] ?? null);
            $resolvedCabangId = $referItem?->cabang_id ?? $product?->cabang_id;

            if ($taxFilter && $taxType !== $taxFilter) {
                return false;
            }

            if ($sourceFilter === 'order_request' && ! $isOrderRequestBacked) {
                return false;
            }

            if ($sourceFilter === 'manual' && $isOrderRequestBacked) {
                return false;
            }

            if ($cabangFilter && (int) $resolvedCabangId !== $cabangFilter) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = Str::lower(implode(' ', array_filter([
                $product?->sku,
                $product?->name,
                $item['unit'] ?? null,
                $isOrderRequestBacked ? 'order request or' : 'manual',
            ])));

            return Str::contains($haystack, Str::lower($search));
        });

        $productOptions = Product::withoutGlobalScope('product_cabang')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'sku', 'name'])
            ->mapWithKeys(fn (Product $product) => [$product->id => "({$product->sku}) {$product->name}"])
            ->all();
        $currencyOptions = Currency::orderBy('name')
            ->get(['id', 'name', 'code', 'symbol'])
            ->mapWithKeys(fn (Currency $currency) => [$currency->id => trim("{$currency->name} ({$currency->code} / {$currency->symbol})")])
            ->all();

        $cabangIds = $products->pluck('cabang_id')
            ->merge($referItems->pluck('cabang_id'))
            ->filter()
            ->unique()
            ->values();
        $cabangs = \App\Models\Cabang::whereIn('id', $cabangIds)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->keyBy('id');
        $cabangOptions = $cabangs
            ->map(fn (\App\Models\Cabang $cabang) => "({$cabang->kode}) {$cabang->nama}")
            ->all();

        $rows = $matched->values()->map(function ($item, int $index) use ($products, $referItems, $currencies, $productOptions, $currencyOptions, $validationErrors): array {
            $key = (string) ($item['_navigator_key'] ?? $item['id'] ?? $index);
            $product = $products->get($item['product_id'] ?? null);
            $referItem = $referItems->get($item['refer_item_model_id'] ?? null);
            $currencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
            $currencySymbol = CurrencyConversionResolver::resolveSymbol($currencyId);
            $isOrderRequestBacked = ($item['refer_item_model_type'] ?? null) === OrderRequestItem::class
                || filled($item['refer_item_model_id'] ?? null);
            $taxType = self::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);

            return [
                'key' => $key,
                'number' => $index + 1,
                'product_id' => $item['product_id'] ?? null,
                'product' => $product ? "({$product->sku}) {$product->name}" : '-',
                'product_options' => ($item['product_id'] ?? null) && $product
                    ? [(int) $item['product_id'] => "({$product->sku}) {$product->name}"] + $productOptions
                    : $productOptions,
                'currency_id' => $currencyId,
                'currency_options' => $currencyOptions,
                'currency_symbol' => $currencySymbol,
                'quantity' => $item['quantity'] ?? 0,
                'unit' => $item['unit'] ?? ($product?->uom?->abbreviation ?? '-'),
                'unit_price' => self::formatCurrencyInputState($item['unit_price'] ?? 0, $currencyId),
                'total' => self::formatCurrencyPreviewState($item['total'] ?? 0, $currencyId),
                'discount' => $item['discount'] ?? 0,
                'discount_nominal' => self::formatCurrencyPreviewState($item['discount_nominal'] ?? 0, $currencyId),
                'tax' => $item['tax'] ?? 0,
                'tax_nominal' => self::formatCurrencyPreviewState($item['tax_nominal'] ?? 0, $currencyId),
                'subtotal' => self::formatCurrencyPreviewState($item['subtotal'] ?? 0, $currencyId),
                'tipe_pajak' => $taxType,
                'source' => $isOrderRequestBacked ? 'Order Request' : 'Manual',
                'is_order_request_backed' => $isOrderRequestBacked,
                'refer_item_model_id' => $item['refer_item_model_id'] ?? null,
                'cabang_id' => $referItem?->cabang_id ?? $product?->cabang_id,
                'is_dirty' => ! empty($item['_is_dirty']),
                'validation_errors' => array_values(array_filter($validationErrors[$key] ?? [])),
            ];
        })->all();

        return new \Illuminate\Support\HtmlString(view('filament.forms.purchase-order-item-navigator', [
            'rows' => $rows,
            'totalItems' => count($items),
            'matchedCount' => count($rows),
            'totalQty' => $baseItemCollection->sum(fn ($item) => (float) ($item['quantity'] ?? 0)),
            'totalSubtotal' => $baseItemCollection->sum(fn ($item) => self::parseCurrencyState($item['subtotal'] ?? 0)),
            'search' => $search,
            'taxFilter' => $taxFilter,
            'sourceFilter' => $sourceFilter,
            'cabangFilter' => $cabangFilter,
            'cabangOptions' => $cabangOptions,
            'taxOptions' => TaxTypeHelper::options(),
            'validationErrors' => $validationErrors,
        ])->render());
    }

    public static function calculateCurrencyPreview(float $quantity, float $unitPrice, float $discount, float $tax, ?string $taxType, ?int $currencyId): array
    {
        $normalizedTaxType = self::normalizeTaxTypeValue($taxType);
        $base = $quantity * $unitPrice;
        $afterDiscount = $base - ($base * ($discount / 100));
        $taxNominal = round($afterDiscount * ($tax / 100), 2);

        return [
            'total' => $base,
            'discount_nominal' => round($base * ($discount / 100), 2),
            'tax_nominal' => $normalizedTaxType === 'none' ? 0.0 : $taxNominal,
            'subtotal' => $normalizedTaxType === 'inklusif' ? $afterDiscount : $afterDiscount + $taxNominal,
        ];
    }

    public static function purchaseOrderTotalSummary(PurchaseOrder $record): array
    {
        return app(PurchaseOrderService::class)->calculateTotalSummary($record);
    }

    public static function formatPurchaseOrderIdrAmount(mixed $amount): string
    {
        return 'Rp ' . self::formatMoneyState($amount, null);
    }

    public static function renderPurchaseOrderItemsTotalSummary(PurchaseOrder $record): string
    {
        $summary = self::purchaseOrderTotalSummary($record);
        $currencyTotals = collect($summary['currency_totals'] ?? []);

        if ($currencyTotals->isEmpty()) {
            return '-';
        }

        $lines = $currencyTotals->map(function (array $row): string {
            $currencyId = is_numeric($row['currency_id'] ?? null) ? (int) $row['currency_id'] : null;
            $currencyAmount = e(($row['currency_symbol'] ?? 'Rp') . ' ' . self::formatCurrencyPreviewState($row['subtotal'] ?? 0, $currencyId));
            $idrAmount = e(self::formatPurchaseOrderIdrAmount($row['subtotal_idr'] ?? 0));

            if (self::isIdrCurrency($currencyId)) {
                return '<div>' . $idrAmount . '</div>';
            }

            return '<div>' . $currencyAmount . ' -&gt; ' . $idrAmount . '</div>';
        })->implode('');

        return '<div class="space-y-1">' . $lines . '</div>';
    }

    public static function renderPurchaseOrderTotalAmountSummary(PurchaseOrder $record): string
    {
        $summary = self::purchaseOrderTotalSummary($record);
        $computedTotal = (float) ($summary['total_idr'] ?? 0);
        $storedTotal = (float) ($record->total_amount ?? 0);

        if ($storedTotal <= 0 && $computedTotal > 0) {
            return '<div class="space-y-1">'
                . '<div class="font-medium text-warning-600 dark:text-warning-400">' . e(self::formatPurchaseOrderIdrAmount($computedTotal)) . '</div>'
                . '<div class="text-xs text-warning-600 dark:text-warning-400">Perlu sync: header total masih 0.</div>'
                . '</div>';
        }

        return e(self::formatPurchaseOrderIdrAmount($storedTotal));
    }

    public static function isOrderRequestBackedItem(Get $get): bool
    {
        return $get('../../refer_model_type') === OrderRequest::class
            || $get('refer_item_model_type') === OrderRequestItem::class
            || filled($get('refer_item_model_id'));
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
        return TaxTypeHelper::normalize($value);
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
            ->where('product_id', $productId);

        if ($supplierId) {
            $query->orderByRaw('CASE WHEN supplier_id = ? THEN 0 WHEN supplier_id IS NULL THEN 1 ELSE 2 END', [$supplierId]);
        }

        if ($cabangId) {
            $query->orderByRaw('CASE WHEN cabang_id = ? THEN 0 WHEN cabang_id IS NULL THEN 1 ELSE 2 END', [$cabangId]);
        }

        return $query->orderBy('id')
            ->get()
            ->first(fn(OrderRequestItem $item) => OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'] > 0);
    }

    public static function resolveOrderRequestItemCabangId(OrderRequestItem $orderRequestItem, ?OrderRequest $orderRequest = null): ?int
    {
        if (! empty($orderRequestItem->cabang_id)) {
            return (int) $orderRequestItem->cabang_id;
        }

        return null;
    }

    public static function resolvePurchaseOrderCabang(?PurchaseOrder $purchaseOrder): ?\App\Models\Cabang
    {
        if (! $purchaseOrder) {
            return null;
        }

        $cabangId = $purchaseOrder->cabang_id;
        if (! $cabangId) {
            $purchaseOrder->loadMissing('purchaseOrderItem.referItemModel');
            foreach ($purchaseOrder->purchaseOrderItem as $item) {
                $referItem = $item->referItemModel;
                if ($referItem instanceof OrderRequestItem && $referItem->cabang_id) {
                    $cabangId = (int) $referItem->cabang_id;
                    break;
                }
            }
        }

        return $cabangId ? \App\Models\Cabang::find($cabangId) : null;
    }

    /**
     * Compute the quantity already locked in approved/active PO items for a given OrderRequestItem.
     * This is needed because fulfilled_quantity is only updated when goods are received,
     * not when a PO is approved. Without this, the system would think all qty is still
     * available for new POs right after approving the first PO.
     */
    public static function getLockedQuantityForOrderRequestItem(int $orderRequestItemId): float
    {
        return OrderRequestQuantityLock::activePurchaseOrderItemQuantity($orderRequestItemId);
    }

    public static function getAllocatedQuantityForOrderRequestItemInResource(int $orderRequestItemId, ?int $excludePurchaseOrderItemId = null): float
    {
        return (float) \App\Models\PurchaseOrderItem::query()
            ->where('refer_item_model_type', OrderRequestItem::class)
            ->where('refer_item_model_id', $orderRequestItemId)
            ->when($excludePurchaseOrderItemId, fn($query) => $query->whereKeyNot($excludePurchaseOrderItemId))
            ->whereHas('purchaseOrder', fn($query) => $query->whereNotIn('status', self::RESOURCE_PO_EXCLUDED_STATUSES))
            ->sum('quantity');
    }

    public static function orderRequestItemResourceLimit(int $orderRequestItemId, ?int $excludePurchaseOrderItemId = null): array
    {
        $orderRequestItem = OrderRequestItem::withoutGlobalScopes()->find($orderRequestItemId);

        if (! $orderRequestItem) {
            return [
                'or_quantity' => 0.0,
                'allocated_po_quantity' => 0.0,
                'remaining_for_po_resource' => 0.0,
            ];
        }

        $orQuantity = (float) ($orderRequestItem->quantity ?? 0);
        $allocatedPoQuantity = self::getAllocatedQuantityForOrderRequestItemInResource($orderRequestItemId, $excludePurchaseOrderItemId);
        $acceptedReceiptQuantity = OrderRequestQuantityLock::orderRequestItemLimit($orderRequestItemId, $excludePurchaseOrderItemId)['accepted_receipt_quantity'] ?? 0.0;
        $fulfilledQuantity = (float) ($orderRequestItem->fulfilled_quantity ?? 0);
        $accountedForPoResource = max($allocatedPoQuantity, (float) $acceptedReceiptQuantity, $fulfilledQuantity);

        return [
            'or_quantity' => $orQuantity,
            'allocated_po_quantity' => $allocatedPoQuantity,
            'remaining_for_po_resource' => max(0, $orQuantity - $accountedForPoResource),
        ];
    }

    public static function resolvePurchaseOrderItemIdFromValidationAttribute(string $attribute, array $items = []): ?int
    {
        $segments = explode('.', $attribute);
        $purchaseOrderItemIndex = array_search('purchaseOrderItem', $segments, true);
        $itemKey = $purchaseOrderItemIndex !== false ? ($segments[$purchaseOrderItemIndex + 1] ?? null) : null;

        if (is_string($itemKey) && preg_match('/^record-(\d+)$/', $itemKey, $matches)) {
            return (int) $matches[1];
        }

        if ($itemKey !== null && is_array($items[$itemKey] ?? null) && is_numeric($items[$itemKey]['id'] ?? null)) {
            return (int) $items[$itemKey]['id'];
        }

        return null;
    }

    public static function shouldAutoApproveOrderRequestPurchaseOrder(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->refer_model_type !== OrderRequest::class || ! $purchaseOrder->refer_model_id) {
            return false;
        }

        $purchaseOrder->loadMissing('purchaseOrderItem');

        if ($purchaseOrder->purchaseOrderItem->isEmpty()) {
            return false;
        }

        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            if (
                $item->refer_item_model_type !== OrderRequestItem::class
                || ! $item->refer_item_model_id
            ) {
                return false;
            }

            $limit = self::orderRequestItemResourceLimit(
                (int) $item->refer_item_model_id,
                $item->exists ? (int) $item->id : null
            );

            if (abs((float) ($item->quantity ?? 0) - (float) $limit['remaining_for_po_resource']) > 0.00001) {
                return false;
            }
        }

        return true;
    }

    public static function getAvailableOrderRequestItemGroups(OrderRequest $orderRequest): array
    {
        return $orderRequest->orderRequestItem
            ->map(function (OrderRequestItem $orderRequestItem) use ($orderRequest) {
                if (! static::isOrderRequestItemEligibleForPurchaseOrder($orderRequestItem)) {
                    return null;
                }

                $remainingQuantity = static::orderRequestItemResourceLimit((int) $orderRequestItem->id)['remaining_for_po_resource'];

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
            ->filter(fn(OrderRequest $orderRequest) => static::hasAvailableOrderRequestSupplier($orderRequest))
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

    public static function isOrderRequestItemEligibleForPurchaseOrder(OrderRequestItem $orderRequestItem): bool
    {
        return OrderRequestItem::normalizeApprovalStatus($orderRequestItem->status ?? null) === OrderRequestItem::STATUS_APPROVED;
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
            ->map(fn($id) => (int) $id)
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
            ->map(fn($id) => (int) $id)
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
                    ->columns(3)
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
                                                    $availableCabangIds = self::getAvailableOrderRequestCabangIds($orderRequest);
                                                    if (! empty($availableCabangIds)) {
                                                        $set('cabang_id', (int) $availableCabangIds[0]);
                                                    }
                                                }
                                            }
                                        }
                                        $availableCabangIds = self::getAvailableOrderRequestCabangIds($orderRequest);
                                        if (! empty($availableCabangIds)) {
                                            $set('cabang_id', (int) $availableCabangIds[0]);
                                        }
                                        $set('currency_id', $defaultCurrencyId);
                                        $set('purchaseOrderItem', $items);
                                    })
                                    ->nullable(),
                            ]),
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
                                        ->map(fn($id) => (int) $id)
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
                                    ->map(fn($id) => (int) $id)
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

                                // Perbarui harga unit dan hitung ulang subtotal/total pada item repeater yang sudah ada
                                $items = $get('purchaseOrderItem') ?? [];
                                if (! empty($items)) {
                                    foreach ($items as $index => $item) {
                                        $productId = $item['product_id'] ?? null;
                                        if ($productId) {
                                            $product = Product::withoutGlobalScope('product_cabang')->find($productId);
                                            if ($product) {
                                                $newUnitPrice = 0.0;
                                                if ($state) {
                                                    $supplierProduct = $product->suppliers()->where('suppliers.id', $state)->first();
                                                    if ($supplierProduct && (float) $supplierProduct->pivot->supplier_price > 0) {
                                                        $newUnitPrice = MoneyHelper::parseHighPrecision($supplierProduct->pivot->supplier_price);
                                                    } else {
                                                        $newUnitPrice = MoneyHelper::parseHighPrecision($product->cost_price ?? 0);
                                                    }
                                                } else {
                                                    $newUnitPrice = MoneyHelper::parseHighPrecision($product->cost_price ?? 0);
                                                }

                                                $itemCurrencyId = is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : null;
                                                $newUnitPrice = CurrencyConversionResolver::convertFromIdr($newUnitPrice, $itemCurrencyId, false);
                                                $newUnitPrice = self::isIdrCurrency($itemCurrencyId)
                                                    ? round((float) $newUnitPrice, 2)
                                                    : (float) $newUnitPrice;

                                                $set("purchaseOrderItem.{$index}.unit_price", self::formatCurrencyInputState($newUnitPrice, $itemCurrencyId));

                                                // Hitung ulang subtotal dan total item
                                                $qty = (float) ($item['quantity'] ?? 0);
                                                $disc = (float) ($item['discount'] ?? 0);
                                                $tax = (float) ($item['tax'] ?? 0);
                                                $tipePajak = self::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);
                                                $preview = self::calculateCurrencyPreview($qty, $newUnitPrice, $disc, $tax, $tipePajak, $itemCurrencyId);

                                                $set("purchaseOrderItem.{$index}.total", self::formatCurrencyPreviewState($preview['total'], $itemCurrencyId));
                                                $set("purchaseOrderItem.{$index}.discount_nominal", self::formatCurrencyPreviewState($preview['discount_nominal'], $itemCurrencyId));
                                                $set("purchaseOrderItem.{$index}.tax_nominal", self::formatCurrencyPreviewState($preview['tax_nominal'], $itemCurrencyId));
                                                $set("purchaseOrderItem.{$index}.subtotal", self::formatCurrencyPreviewState($preview['subtotal'], $itemCurrencyId));
                                            }
                                        }
                                    }
                                }

                                // When referring to a multisupplier Order Request, rebuild items for the chosen supplier only
                                if ($get('refer_model_type') === 'App\\Models\\OrderRequest' && $get('refer_model_id') && $state) {
                                    $orderRequest = OrderRequest::with(['orderRequestItem.product.uom', 'orderRequestItem.product.suppliers'])
                                        ->find($get('refer_model_id'));
                                    if ($orderRequest) {
                                        $orderRequestCabangIds = self::getAvailableOrderRequestCabangIds($orderRequest, (int) $state);
                                        $cabangId = $orderRequestCabangIds[0] ?? null;
                                        $set('cabang_id', $cabangId);

                                        $defaultCurrencyId = Currency::query()->first()?->id;
                                        $items = self::buildOrderRequestItems(
                                            $orderRequest,
                                            (int) $state,
                                            $cabangId,
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
                                    ->tel()
                                    ->telRegex('/^[0-9+\s().-]*$/')
                                    ->dehydrateStateUsing(fn ($state) => is_string($state) ? trim($state) : $state)
                                    ->helperText('Contoh : (+62) 830 9787 333, +62 21 12345678, 07512345678')
                                    ->rules([new InternationalPhoneNumber()])
                                    ->maxLength(50),
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
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                return \App\Models\Cabang::query()
                                    ->orderBy('nama')
                                    ->get()
                                    ->mapWithKeys(fn($cabang) => [$cabang->id => "({$cabang->kode}) {$cabang->nama}"])
                                    ->all();
                            })
                            ->default(fn() => Auth::user()?->cabang_id)
                            ->disabled(fn(Get $get) => $get('refer_model_type') === 'App\\Models\\OrderRequest')
                            ->dehydrated()
                            ->required(),
                        Select::make('top_type')
                            ->label('TOP (Term Of Payment)')
                            ->options(self::topTypeOptions())
                            ->default('credit_days')
                            ->reactive()
                            ->default(null)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (self::normalizeTopTypeValue($state) !== 'credit_days') {
                                    $set('tempo_hutang', 0);
                                }
                            })
                            ->dehydrated(),
                        TextInput::make('tempo_hutang')
                            ->label('Masa Kredit (Hari)')
                            ->helperText('Dipakai hanya jika TOP menggunakan Credit Days')
                            ->numeric()
                            ->default(0)
                            ->visible(fn(Get $get) => self::normalizeTopTypeValue($get('top_type') ?? null) === 'credit_days')
                            ->reactive()
                            ->dehydrated(),
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
                        TextInput::make('_purchase_order_item_search')
                            ->label('Cari Item PO')
                            ->placeholder('Cari SKU/nama produk atau sumber item')
                            ->helperText('Untuk PO besar, gunakan pencarian ini bersama filter dan collapse/expand item. Data item tidak difilter keluar dari state.')
                            ->hidden()
                            ->live(debounce: 500)
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Select::make('_purchase_order_item_tax_filter')
                            ->label('Filter Tipe Pajak Item')
                            ->options([
                                'inklusif' => 'Inklusif',
                                'eklusif' => 'Eklusif',
                                'none' => 'Non Pajak',
                            ])
                            ->hidden()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),
                        Select::make('_purchase_order_item_source_filter')
                            ->label('Filter Sumber Item')
                            ->options([
                                'order_request' => 'Dari Order Request',
                                'manual' => 'Manual',
                            ])
                            ->hidden()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),
                        Select::make('_purchase_order_item_cabang_filter')
                            ->label('Filter Cabang Item')
                            ->options(function (Get $get) {
                                $items = collect($get('purchaseOrderItem') ?? []);
                                $productIds = $items->pluck('product_id')->filter()->unique()->values();
                                $referItemIds = $items->pluck('refer_item_model_id')->filter()->unique()->values();
                                $cabangIds = Product::withoutGlobalScope('product_cabang')
                                    ->whereIn('id', $productIds)
                                    ->pluck('cabang_id')
                                    ->merge(OrderRequestItem::withoutGlobalScopes()->whereIn('id', $referItemIds)->pluck('cabang_id'))
                                    ->filter()
                                    ->unique()
                                    ->values();

                                return \App\Models\Cabang::whereIn('id', $cabangIds)
                                    ->orderBy('kode')
                                    ->get(['id', 'kode', 'nama'])
                                    ->mapWithKeys(fn (\App\Models\Cabang $cabang) => [$cabang->id => "({$cabang->kode}) {$cabang->nama}"]);
                            })
                            ->hidden()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),
                        Hidden::make('_purchase_order_item_drafts')
                            ->default([])
                            ->dehydrated(false),
                        Hidden::make('_purchase_order_item_dirty')
                            ->default([])
                            ->dehydrated(false),
                        Hidden::make('_purchase_order_item_validation_errors')
                            ->default([])
                            ->dehydrated(false),
                        Placeholder::make('_purchase_order_item_summary')
                            ->label('Ringkasan Item PO')
                            ->content(fn (Get $get) => self::renderPurchaseOrderItemNavigator(
                                is_array($get('purchaseOrderItem')) ? $get('purchaseOrderItem') : [],
                                $get('_purchase_order_item_search'),
                                $get('_purchase_order_item_tax_filter'),
                                $get('_purchase_order_item_source_filter'),
                                $get('_purchase_order_item_cabang_filter'),
                                is_array($get('_purchase_order_item_drafts')) ? $get('_purchase_order_item_drafts') : [],
                                is_array($get('_purchase_order_item_dirty')) ? $get('_purchase_order_item_dirty') : [],
                                is_array($get('_purchase_order_item_validation_errors')) ? $get('_purchase_order_item_validation_errors') : []
                            ))
                            ->columnSpanFull(),
                        Repeater::make('purchaseOrderItem')
                            ->relationship()
                            ->extraAttributes(fn (?string $operation): array => self::usesLargePurchaseOrderItemEditor([], $operation) ? [
                                'class' => 'dt-po-large-repeater',
                                'data-dt-large-po-repeater' => 'true',
                            ] : [])
                            ->addable(fn(Get $get) => $get('refer_model_type') !== 'App\\Models\\OrderRequest')
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
                                $data['discount_nominal'] = self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId);
                                $data['tax_nominal'] = self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId);

                                return $data;
                            })
                            ->columnSpanFull()
                            ->columns(10)
                            ->hint('Tambahkan item pembelian yang akan diinput')
                            ->defaultItems(0)
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

                                return $itemKey !== $lastKey;
                            })
                            ->addAction(function (ActionsAction $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Order Items')
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
                                        $component->callAfterStateUpdated();
                                    });
                            })
                            ->collapseAllAction(fn ($action) => $action->label('Collapse semua item'))
                            ->expandAllAction(fn ($action) => $action->label('Expand semua item'))
                            ->itemNumbers()
                            ->itemLabel(function (array $state) {
                                $productName = '-';
                                $uom = '-';
                                if (! empty($state['product_id'])) {
                                    $product = Product::withoutGlobalScope('product_cabang')->find($state['product_id']);
                                    $productName = $product ? "({$product->sku}) {$product->name}" : '-';
                                    $uom = $product?->uom?->abbreviation ?? $product?->uom?->name ?? '-';
                                }

                                $qty = $state['quantity'] ?? '0';
                                $subtotal = $state['subtotal'] ?? '0';
                                $price = $state['unit_price'] ?? '0';
                                $source = filled($state['refer_item_model_id'] ?? null) ? 'Order Request' : 'Manual';
                                $taxType = Str::upper((string) ($state['tipe_pajak'] ?? '-'));
                                $currencyId = isset($state['currency_id']) && is_numeric($state['currency_id'])
                                    ? (int) $state['currency_id']
                                    : null;
                                $currencySymbol = CurrencyConversionResolver::resolveSymbol($currencyId);

                                return "Product: {$productName} | Source: {$source} | Qty: {$qty} {$uom} | Price: {$currencySymbol} {$price} | Subtotal: {$currencySymbol} {$subtotal} | Tipe Pajak: {$taxType}";
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
                                    ->getOptionLabelUsing(function ($value) {
                                        if (!$value) {
                                            return null;
                                        }
                                        $product = \App\Models\Product::withoutGlobalScope('product_cabang')->find($value);
                                        if ($product) {
                                            return "({$product->sku}) {$product->name}";
                                        }
                                        return (string) $value;
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
                                            $supplierId = $get('../../supplier_id');
                                            $newUnitPrice = 0.0;
                                            if ($supplierId) {
                                                $supplierProduct = $product->suppliers()->where('suppliers.id', $supplierId)->first();
                                                if ($supplierProduct && (float) $supplierProduct->pivot->supplier_price > 0) {
                                                    $newUnitPrice = MoneyHelper::parseHighPrecision($supplierProduct->pivot->supplier_price);
                                                } else {
                                                    $newUnitPrice = MoneyHelper::parseHighPrecision($product->cost_price ?? 0);
                                                }
                                            } else {
                                                $newUnitPrice = MoneyHelper::parseHighPrecision($product->cost_price ?? 0);
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
                                            $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $itemCurrencyId));
                                            $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $itemCurrencyId));
                                            $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $itemCurrencyId));
                                        } else {
                                            $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : null;
                                            $preview = self::calculateCurrencyPreview((float)$get('quantity'), self::parseCurrencyState($get('unit_price')), (float)$get('discount'), (float)$get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null), $currencyId);
                                            $set('total', self::formatCurrencyPreviewState($preview['total'], $currencyId));
                                            $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                            $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                            $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                            $set('refer_item_model_type', null);
                                            $set('refer_item_model_id', null);
                                        }
                                    })
                                    ->disabled(fn(Get $get) => self::isOrderRequestBackedItem($get))
                                    ->dehydrated(true)
                                    ->columnSpan(4)
                                    ->required(),
                                TextInput::make('unit')
                                    ->label('Satuan')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default('-')
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record?->product) {
                                            $component->state($record->product->uom?->abbreviation ?? '-');
                                        }
                                    })
                                    ->columnSpan(3),
                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->default(0)
                                    ->reactive()
                                    ->helperText(function (Get $get) {
                                        $orItemId = $get('refer_item_model_id');
                                        if (!$orItemId) return null;
                                        $currentItemId = is_numeric($get('id')) ? (int) $get('id') : null;
                                        $max = self::orderRequestItemResourceLimit((int) $orItemId, $currentItemId)['remaining_for_po_resource'];
                                        return "Maks: {$max} (sisa OR)";
                                    })
                                    ->rules([function (Get $get) {
                                        return function ($attribute, $value, $fail) use ($get) {
                                            $orItemId = $get('refer_item_model_id');
                                            if (!$orItemId) return;

                                            $items = $get('../../purchaseOrderItem') ?? [];
                                            $itemId = self::resolvePurchaseOrderItemIdFromValidationAttribute(
                                                $attribute,
                                                is_array($items) ? $items : []
                                            );

                                            $max = self::orderRequestItemResourceLimit(
                                                (int) $orItemId,
                                                $itemId ? (int) $itemId : null
                                            )['remaining_for_po_resource'];
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
                                        $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->columnSpan(3),
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
                                            $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $newCurrencyId));
                                            $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $newCurrencyId));
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
                                    ->disabled(fn(Get $get) => self::isOrderRequestBackedItem($get))
                                    ->dehydrated(true)
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ])
                                    ->columnSpan(2),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->reactive()
                                    ->required()
                                    ->readOnly()
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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
                                        $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->columnSpan(2),
                                TextInput::make('total')
                                    ->label('Total (Harga x Qty)')
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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
                                    })
                                    ->columnSpan(2),
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
                                        $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->readOnly(fn(Get $get) => self::isOrderRequestBackedItem($get))
                                    ->extraInputAttributes(fn(Get $get) => self::isOrderRequestBackedItem($get)
                                        ? ['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;']
                                        : [])
                                    ->suffix('%')
                                    ->default(0)
                                    ->columnSpan(2),
                                TextInput::make('discount_nominal')
                                    ->label('Nominal Discount')
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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

                                        $component->state(self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                    })
                                    ->helperText('Nominal discount dihitung otomatis dari quantity, unit price, dan discount.')
                                    ->columnSpan(2),
                                Radio::make('tipe_pajak')
                                    ->label('Tipe Pajak per Item')
                                    ->inline()
                                    ->reactive()
                                    ->required()
                                    ->default('inklusif')
                                    ->dehydrated(true)
                                    ->disabled(fn(Get $get) => self::isOrderRequestBackedItem($get))
                                    ->extraAttributes(['class' => 'data-[disabled=true]:opacity-75'])
                                    ->options(TaxTypeHelper::options())
                                    ->helperText(fn (Get $get) => self::isOrderRequestBackedItem($get)
                                        ? 'Mengikuti tipe pajak dari Order Request sumber.'
                                        : 'Pajak ditentukan untuk setiap item Purchase Order.')
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
                                        $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->validationMessages([
                                        'required' => 'Tipe Pajak belum dipilih'
                                    ])
                                    ->columnSpan(3),
                                TextInput::make('tax')
                                    ->label('Tax (%)')
                                    ->reactive()
                                    ->numeric()
                                    ->maxValue(100)
                                    ->required()
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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
                                        $set('discount_nominal', self::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('tax_nominal', self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                        $set('subtotal', self::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                    })
                                    ->suffix('%')
                                    ->default(fn() => \App\Models\TaxSetting::activeRate('PPN'))
                                    ->columnSpan(2),
                                TextInput::make('tax_nominal')
                                    ->label('Nominal Pajak')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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

                                        $component->state(self::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                    })
                                    ->helperText('Nominal PPN dihitung otomatis dari quantity, harga, diskon, dan tipe pajak.')
                                    ->columnSpan(2),
                                TextInput::make('subtotal')
                                    ->label('Subtotal (termasuk Pajak)')
                                    ->reactive()
                                    ->prefix(function ($get) {
                                        return CurrencyConversionResolver::resolveSymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : null);
                                    })
                                    ->default(0)
                                    ->readOnly()
                                    ->extraInputAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400', 'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;'])
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

                                        $livewire->data['total_amount'] = self::formatMoneyState($total);
                                    })
                                    ->columnSpan(3),
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
                            ->hidden()
                            ->dehydrated(false)
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
                                        $currencyExists = collect($currencies)->contains(fn($currency) => ($currency['currency_id'] ?? null) == $state);

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
                                    ->live(debounce: 500)
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
                                    ->dehydrateStateUsing(fn($state) => self::parseCurrencyState($state ?? 0))
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
                                    ->live(debounce: 500)
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

                                        $livewire->data['total_amount'] = self::formatMoneyState($total);
                                    }),
                            ]),
                        TextInput::make('total_amount')
                            ->label("Total Amount")
                            ->required()
                            ->reactive()
                            ->hidden()
                            ->dehydrateStateUsing(fn($state) => self::parseCurrencyState($state ?? 0))
                            ->helperText('Total dihitung dari item pembelian; tampil untuk referensi saja')
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

                                $component->state(self::formatMoneyState($total));
                            }),

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                TextColumn::make('cabang_display')
                    ->label('Cabang')
                    ->getStateUsing(function (PurchaseOrder $record) {
                        $cabang = self::resolvePurchaseOrderCabang($record);
                        return $cabang ? "({$cabang->kode}) {$cabang->nama}" : '-';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->where(function (Builder $query) use ($search) {
                            if (Schema::hasColumn('purchase_orders', 'cabang_id')) {
                                $query->whereHas('cabang', function (Builder $query) use ($search) {
                                    $query->where('kode', 'LIKE', '%' . $search . '%')
                                        ->orWhere('nama', 'LIKE', '%' . $search . '%');
                                });
                            }

                            $query->orWhereHas('purchaseOrderItem', function (Builder $query) use ($search) {
                                $query->where('refer_item_model_type', OrderRequestItem::class)
                                    ->whereHasMorph('referItemModel', [OrderRequestItem::class], function (Builder $query) use ($search) {
                                        $query->whereHas('cabang', function (Builder $query) use ($search) {
                                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                                        });
                                    });
                            });
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
                    ->color(fn($state) => self::getStatusColor($state))
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
                    ->formatStateUsing(fn(PurchaseOrder $record) => $record->receiptFulfillmentSummary()['status_label'])
                    ->color(function ($record) {
                        return match ($record->receiptFulfillmentSummary()['status_label']) {
                            'No Items' => 'gray',
                            'Semua Diterima' => 'success',
                            'Sebagian Diterima' => 'warning',
                            default => 'danger',
                        };
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
                    '<style>.fi-ta-header:has(.dt-table-description-full-width){align-items:stretch}.fi-ta-header>.grid:has(.dt-table-description-full-width){width:100%;max-width:none;flex:1 1 100%;}.dt-table-description-full-width{width:100%;min-width:100%;max-width:none;box-sizing:border-box;}</style>' .
                        '<div class="dt-table-description-full-width space-y-4 mb-6 w-full min-w-full max-w-none" style="width: 100%; min-width: 100%; max-width: none; box-sizing: border-box;">' .
                        '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff; transition: all 0.2s;">' .
                        '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; color: #374151;">' .
                        '<span class="flex items-center gap-2" style="display: flex; align-items: center; gap: 8px;">' .
                        '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; color: #3b82f6;">' .
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                        '</svg>' .
                        'Panduan Purchase Order' .
                        '</span>' .
                        '<span class="transition group-open:rotate-180">' .
                        '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                        '</span>' .
                        '</summary>' .
                        '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top: 12px; font-size: 14px; color: #4b5563; padding-left: 28px; border-left: 2px solid rgba(59, 130, 246, 0.3); display: flex; flex-direction: column; gap: 8px;">' .
                        '<p><strong>Apa ini:</strong> Purchase Order (PO) adalah instruksi pembelian resmi ke supplier.</p>' .
                        '<p><strong>Membuat PO:</strong> PO dapat dibuat dari Order Request atau Sales Order, atau dibuat manual lewat tombol Create PO.</p>' .
                        '<p><strong>Alur baru (QC First):</strong> Setelah PO dibuat, lanjutkan ke <strong>Quality Control</strong> untuk inspeksi barang. Setelah QC lulus, Purchase Receipt akan dibuat otomatis dan stok diperbarui.</p>' .
                        '<p><strong>Catatan:</strong> PO berstatus <em>Draft</em> perlu disetujui melalui tombol <em>Setujui PO</em> sebelum diproses lebih lanjut. Tindakan <em>close</em> memerlukan hak akses tertentu.</p>' .
                        '</div>' .
                        '</details>' .
                        '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff;">' .
                        '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">' .
                        '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 16px; height: 16px;">' .
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
                        '</svg>' .
                        'Legenda Warna Status Baris Data' .
                        '</h4>' .
                        '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">' .
                        '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: #ffffff; border: 1px solid #edf2f7;">' .
                        '<div style="width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid #9ca3af; background-color: #ffffff; flex-shrink: 0;"></div>' .
                        '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #4b5563;">Putih (Draft)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">PO masih draft</span></div>' .
                        '</div>' .
                        '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">' .
                        '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>' .
                        '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (Approved)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">PO sudah disetujui</span></div>' .
                        '</div>' .
                        '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 243, 199, 0.4); border: 1px solid rgba(253, 230, 138, 0.8);">' .
                        '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #eab308; box-shadow: 0 1px 3px rgba(234, 179, 8, 0.4); flex-shrink: 0;"></div>' .
                        '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #854d0e;">Kuning (Partially Received)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">PO diterima sebagian</span></div>' .
                        '</div>' .
                        '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 226, 226, 0.4); border: 1px solid rgba(254, 202, 202, 0.8);">' .
                        '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #ef4444; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4); flex-shrink: 0;"></div>' .
                        '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #991b1b;">Merah (Request Close/Closed)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Diminta tutup / ditutup</span></div>' .
                        '</div>' .
                        '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(220, 252, 231, 0.4); border: 1px solid rgba(187, 247, 208, 0.8);">' .
                        '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #22c55e; box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4); flex-shrink: 0;"></div>' .
                        '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #166534;">Hijau (Completed/Paid)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Selesai / dibayar</span></div>' .
                        '</div>' .
                        '</div>' .
                        '</div>' .
                        '</div>'
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
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'perusahaan')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                        return "({$supplier->code}) {$supplier->perusahaan}";
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
                        ->label('Preview PDF')
                        ->icon('heroicon-o-document-check')
                        ->color('gray')
                        ->visible(fn ($record) => $record->status !== 'draft' && $record->status !== 'closed')
                        ->url(fn ($record) => route('pdf-stream', ['type' => 'purchase-order', 'id' => $record->id]))
                        ->openUrlInNewTab(),
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
            if (! static::isOrderRequestItemEligibleForPurchaseOrder($orderRequestItem)) {
                continue;
            }

            // Skip items that belong to a different supplier when a filter is active
            if (
                $filterSupplierId !== null
                && $orderRequestItem->supplier_id !== null
                && (int) $orderRequestItem->supplier_id !== $filterSupplierId
            ) {
                continue;
            }

            $resolvedCabangId = static::resolveOrderRequestItemCabangId($orderRequestItem, $orderRequest);
            if ($filterCabangId !== null && $resolvedCabangId !== null && (int) $resolvedCabangId !== $filterCabangId) {
                continue;
            }

            $remainingQuantity = static::orderRequestItemResourceLimit((int) $orderRequestItem->id)['remaining_for_po_resource'];
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
                $unitPrice = ($sp && (float) $sp->pivot->supplier_price > 0) ? (float) $sp->pivot->supplier_price : (float) ($product->cost_price ?? 0);
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
            ->map(fn($supplierId) => (int) $supplierId)
            ->unique()
            ->values()
            ->all();
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Informasi Purchase Order')
                    ->columns(3)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('po_number')
                            ->label('PO Number'),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn($state) => self::formatStatusLabel($state))
                            ->color(fn($state) => self::getStatusColor($state)),
                        \Filament\Infolists\Components\TextEntry::make('supplier_display')
                            ->label('Supplier')
                            ->getStateUsing(fn($record) => self::formatSupplierLabel($record->supplier)),
                        \Filament\Infolists\Components\TextEntry::make('cabang_display')
                            ->label('Cabang')
                            ->getStateUsing(function ($record) {
                                $cabang = self::resolvePurchaseOrderCabang($record);
                                if (! $cabang) {
                                    return '-';
                                }

                                return $cabang->kode ? "({$cabang->kode}) {$cabang->nama}" : ($cabang->nama ?? '-');
                            }),
                        \Filament\Infolists\Components\TextEntry::make('order_date')
                            ->label('Tanggal Pembelian')
                            ->date('d/m/Y'),
                        \Filament\Infolists\Components\TextEntry::make('expected_date')
                            ->label('Tanggal Diharapkan')
                            ->date('d/m/Y')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('top_type')
                            ->label('TOP')
                            ->getStateUsing(function ($record) {
                                $value = self::normalizeTopTypeValue($record->top_type ?? null);

                                return self::topTypeOptions()[$value] ?? '-';
                            }),
                        \Filament\Infolists\Components\TextEntry::make('tempo_hutang')
                            ->label('Masa Kredit (Hari)')
                            ->getStateUsing(fn($record) => (int) ($record->tempo_hutang ?? 0)),
                        \Filament\Infolists\Components\TextEntry::make('referensi')
                            ->label('Referensi')
                            ->getStateUsing(function ($record) {
                                if (! $record->refer_model_type || ! $record->refer_model_id) {
                                    return '-';
                                }

                                $record->loadMissing('referModel');
                                $refer = $record->referModel;
                                $number = $refer?->request_number
                                    ?? $refer?->so_number
                                    ?? $refer?->number
                                    ?? $record->refer_model_id;

                                return class_basename($record->refer_model_type) . ' #' . $number;
                            }),
                        \Filament\Infolists\Components\TextEntry::make('note')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                \Filament\Infolists\Components\Section::make('Ringkasan Quantity')
                    ->columns(4)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('items_count')
                            ->label('Jumlah Item')
                            ->getStateUsing(fn($record) => $record->purchaseOrderItem()->count()),
                        \Filament\Infolists\Components\TextEntry::make('total_ordered_qty')
                            ->label('Total Qty Dipesan')
                            ->getStateUsing(fn($record) => (float) $record->purchaseOrderItem()->sum('quantity')),
                        \Filament\Infolists\Components\TextEntry::make('total_accepted_qty')
                            ->label('Total Qty Accepted')
                            ->getStateUsing(fn($record) => (float) $record->receiptFulfillmentSummary()['total_accepted']),
                        \Filament\Infolists\Components\TextEntry::make('remaining_qty')
                            ->label('Sisa Qty Belum Diterima')
                            ->getStateUsing(function ($record) {
                                $summary = $record->receiptFulfillmentSummary();

                                return max(0, (float) $summary['total_ordered'] - (float) $summary['total_accepted']);
                            }),
                        \Filament\Infolists\Components\TextEntry::make('receipt_status')
                            ->label('Status Penerimaan')
                            ->badge()
                            ->getStateUsing(fn($record) => $record->receiptFulfillmentSummary()['status_label'])
                            ->color(fn($state) => match ($state) {
                                'Semua Diterima' => 'success',
                                'Sebagian Diterima' => 'warning',
                                'Belum Diterima' => 'gray',
                                default => 'gray',
                            }),
                    ]),
                \Filament\Infolists\Components\Section::make('Detail Item Purchase Order')
                    ->description('Detail item ditampilkan pada tabel review berikut agar pencarian, filter, dan pemeriksaan per item tetap ringan.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('purchase_order_items_table_note')
                            ->label('')
                            ->getStateUsing(fn($record) => 'Gunakan tabel review berikut untuk melihat semua item dengan pencarian produk/currency/cabang, filter tipe pajak/source, dan detail expand per item. Total item: ' . number_format($record->purchaseOrderItem()->count(), 0, ',', '.'))
                            ->badge()
                            ->color('info'),
                        \Filament\Infolists\Components\ViewEntry::make('purchase_order_items_review')
                            ->label('')
                            ->view('filament.infolists.purchase-order-items-review')
                            ->columnSpanFull(),
                    ]),
                \Filament\Infolists\Components\Section::make('Ringkasan Total')
                    ->columns(3)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('items_total')
                            ->label('Total Item')
                            ->getStateUsing(fn($record) => self::renderPurchaseOrderItemsTotalSummary($record))
                            ->html(),
                        \Filament\Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->getStateUsing(fn($record) => self::renderPurchaseOrderTotalAmountSummary($record))
                            ->html(),
                        \Filament\Infolists\Components\TextEntry::make('currency_rates')
                            ->label('Currency / Rate')
                            ->getStateUsing(function ($record) {
                                $record->loadMissing('purchaseOrderCurrency.currency');
                                if ($record->purchaseOrderCurrency->isEmpty()) {
                                    return '-';
                                }

                                return $record->purchaseOrderCurrency
                                    ->map(fn($row) => ($row->currency?->code ?? '-') . ': ' . self::formatMoneyState($row->nominal ?? 0))
                                    ->implode(', ');
                            }),
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
