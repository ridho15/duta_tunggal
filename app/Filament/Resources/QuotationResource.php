<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Filament\Resources\QuotationResource\RelationManagers\QuotationItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Helpers\MoneyHelper;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Services\CustomerService;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use App\Support\TaxDefaultResolver;
use App\Support\TaxTypeHelper;
use App\Support\WarehouseStockOptions;
use App\Support\CurrencyConversionResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Penjualan';

    protected static ?int $navigationSort = 1;

    protected static function normalizeTaxTypeValue(?string $taxType): string
    {
        return TaxTypeHelper::normalize($taxType, TaxTypeHelper::NONE);
    }

    protected static function taxTypeOptions(): array
    {
        return TaxTypeHelper::options();
    }

    protected static function subtotalLabelForTaxType(?string $taxType): string
    {
        return 'Subtotal';
    }

    protected static function taxTypeLabel(?string $taxType): string
    {
        $normalized = static::normalizeTaxTypeValue($taxType);

        return static::taxTypeOptions()[$normalized] ?? $normalized;
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

    protected static function resolveInitialCurrencyId(): ?int
    {
        $purchaseOrderId = request()->query('purchase_order_id');
        if (is_numeric($purchaseOrderId)) {
            $purchaseOrder = PurchaseOrder::query()
                ->with(['purchaseOrderItem:id,purchase_order_id,currency_id', 'purchaseOrderCurrency:id,purchase_order_id,currency_id'])
                ->find((int) $purchaseOrderId);
            $purchaseOrderCurrencyId = $purchaseOrder?->purchaseOrderItem?->first()?->currency_id
                ?? $purchaseOrder?->purchaseOrderCurrency?->first()?->currency_id;

            if (is_numeric($purchaseOrderCurrencyId)) {
                return (int) $purchaseOrderCurrencyId;
            }
        }

        $orderRequestId = request()->query('order_request_id');
        if (is_numeric($orderRequestId)) {
            $orderRequestCurrencyId = OrderRequest::query()->whereKey((int) $orderRequestId)->value('currency_id');

            if (is_numeric($orderRequestCurrencyId)) {
                return (int) $orderRequestCurrencyId;
            }
        }

        return static::resolveDefaultCurrencyId();
    }

    protected static function resolveExchangeRate(?int $currencyId): float
    {
        return CurrencyConversionResolver::resolveRate($currencyId);
    }

    protected static function isIdrCurrency(?int $currencyId): bool
    {
        if ($currencyId === null) {
            $currencyId = static::resolveDefaultCurrencyId();
        }

        return strtoupper((string) Currency::find($currencyId)?->code) === 'IDR';
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

    public static function formatCurrencyPreviewState(mixed $amount, ?int $currencyId): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $decimals = static::isIdrCurrency($currencyId) ? 0 : 2;

        return number_format(static::parseCurrencyState($amount), $decimals, ',', '.');
    }

    protected static function formatCurrencyInputState(mixed $amount, ?int $currencyId): string
    {
        return static::formatCurrencyPreviewState($amount, $currencyId);
    }

    protected static function formatCurrencyAmount(?int $currencyId, mixed $amount): string
    {
        return static::resolveCurrencySymbol($currencyId) . ' ' . static::formatCurrencyPreviewState($amount, $currencyId);
    }

    public static function resolveQuotationFormCurrencyId($get): ?int
    {
        $currencyId = $get('../../currency_id');

        if (! is_numeric($currencyId)) {
            $currencyId = $get('currency_id');
        }

        return is_numeric($currencyId) ? (int) $currencyId : static::resolveDefaultCurrencyId();
    }

    protected static function resolveQuotationItemUnitPriceIdr(array $item, ?int $oldCurrencyId): string
    {
        $unitPriceIdr = MoneyHelper::parseHighPrecision($item['unit_price_idr'] ?? 0);

        if ((float) $unitPriceIdr > 0) {
            return (string) $unitPriceIdr;
        }

        return (string) CurrencyConversionResolver::convertToIdrHighPrecision(
            MoneyHelper::parseHighPrecision(static::parseCurrencyState($item['unit_price'] ?? 0)),
            $oldCurrencyId
        );
    }

    protected static function convertCurrencyAmount(float $amount, ?int $fromCurrencyId, ?int $toCurrencyId): float
    {
        return (float) CurrencyConversionResolver::convertBetweenCurrencies($amount, $fromCurrencyId, $toCurrencyId, false);
    }

    protected static function calculateCurrencyPreview(float $quantity, float $unitPrice, float $discount, float $tax, ?string $taxType): array
    {
        $taxType = static::normalizeTaxTypeValue($taxType);
        $tax = $taxType === TaxTypeHelper::NONE ? 0.0 : $tax;

        // Base calculation: qty × unit_price
        $total = $quantity * $unitPrice;
        $discountNominal = $total * ($discount / 100);
        $afterDiscount = $total - $discountNominal;

        // Tax nominal calculated from afterDiscount base (same formula for all tax types)
        // This ensures consistency between inclusive and exclusive tax types
        $taxNominal = round($afterDiscount * ($tax / 100), 2);

        // Subtotal differs based on tax type
        // - Inklusif: price already includes tax, so subtotal = afterDiscount
        // - Eksklusif: price excludes tax, so subtotal = afterDiscount + taxNominal
        // - None: no tax, subtotal = afterDiscount
        if ($taxType === TaxTypeHelper::INKLUSIF) {
            $subtotal = round($afterDiscount, 2);
        } elseif ($taxType === TaxTypeHelper::EKLUSIF) {
            $subtotal = round($afterDiscount + $taxNominal, 2);
        } else {
            // NONE
            $subtotal = round($afterDiscount, 2);
            $taxNominal = 0.0;
        }

        return [
            'total' => $total,
            'discount_nominal' => round($discountNominal, 2),
            'tax_nominal' => $taxNominal,
            'subtotal' => $subtotal,
        ];
    }

    protected static function calculateQuotationTotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $preview = static::calculateCurrencyPreview(
                (float) ($item['quantity'] ?? 0),
                static::parseCurrencyState($item['unit_price'] ?? 0),
                (float) ($item['discount'] ?? 0),
                (float) ($item['tax'] ?? 0),
                $item['tax_type'] ?? null,
            );

            $total += (float) $preview['subtotal'];
        }

        return $total;
    }

    protected static function formatMoneyState(mixed $amount, ?int $currencyId = null): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return static::formatCurrencyPreviewState($amount, $currencyId ?? static::resolveDefaultCurrencyId());
    }

    protected static function readOnlyGrayInputAttributes(): array
    {
        return [
            'class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400',
            'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;',
        ];
    }

    protected static function recalculateQuotationItemState(callable $set, callable $get, $livewire): void
    {
        $currencyId = static::resolveQuotationFormCurrencyId($get);
        $numericUnit = static::parseCurrencyState($get('unit_price') ?? 0);
        $qty = (float) ($get('quantity') ?? 0);
        $discPct = (float) ($get('discount') ?? 0);
        $taxType = static::normalizeTaxTypeValue($get('tax_type') ?? 'None');
        $taxPct = $taxType === TaxTypeHelper::NONE ? 0 : (float) ($get('tax') ?? 0);

        $preview = static::calculateCurrencyPreview($qty, $numericUnit, $discPct, $taxPct, $taxType);

        $set('total_price', static::formatCurrencyPreviewState($preview['total'], $currencyId));
        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
        $set('unit_price_idr', CurrencyConversionResolver::convertToIdrHighPrecision(
            MoneyHelper::parseHighPrecision($numericUnit),
            $currencyId
        ));
        $set('currency_symbol', static::resolveCurrencySymbol($currencyId));

        // Recalculate overall total_amount
        $items = $livewire->data['quotationItem'] ?? [];
        $livewire->data['total_amount'] = static::formatMoneyState(static::calculateQuotationTotal($items), $currencyId);
    }
    
    public static function quotationStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'request_approve' => 'Request Approve',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            default => '-',
        };
    }
    
    protected static function quotationStatusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'request_approve' => 'gray',
            'approve' => 'info',
            'reject' => 'danger',
            default => 'gray',
        };
    }
    
    protected static function quotationStatusBadge(?string $status): HtmlString
    {
        $label = static::quotationStatusLabel($status);
        
        $palette = match ($status) {
            'draft' => ['bg' => '#ffffff', 'border' => '#9ca3af', 'text' => '#4b5563'],
            'request_approve' => ['bg' => '#e5e7eb', 'border' => '#d1d5db', 'text' => '#374151'],
            'approve' => ['bg' => '#dbeafe', 'border' => '#bfdbfe', 'text' => '#1e40af'],
            'reject' => ['bg' => '#fee2e2', 'border' => '#fecaca', 'text' => '#991b1b'],
            default => ['bg' => '#f3f4f6', 'border' => '#d1d5db', 'text' => '#4b5563'],
        };
        
        return new HtmlString(sprintf(
            '<span style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:9999px;border:1px solid %s;background-color:%s;color:%s;font-size:12px;font-weight:700;line-height:1;">%s</span>',
            $palette['border'],
            $palette['bg'],
            $palette['text'],
            e($label),
        ));
    }

    protected static function quotationMoney(mixed $amount, ?int $currencyId = null): string
    {
        return static::formatCurrencyAmount($currencyId ?? static::resolveDefaultCurrencyId(), $amount ?? 0);
    }

    protected static function quotationItemSubtotal($item): float
    {
        return (float) HelperController::hitungSubtotal(
            (float) ($item->quantity ?? 0),
            (float) ($item->unit_price ?? 0),
            (float) ($item->discount ?? 0),
            (float) ($item->tax ?? 0),
            static::normalizeTaxTypeValue($item->tax_type ?? null)
        );
    }

    public static function resolveCurrencySymbol(?int $currencyId): string
    {
        return CurrencyConversionResolver::resolveSymbol($currencyId);
    }

    protected static function quotationItemTaxNominal($item): float
    {
        return (float) HelperController::hitungTaxNominal(
            (float) ($item->quantity ?? 0),
            (float) ($item->unit_price ?? 0),
            (float) ($item->discount ?? 0),
            (float) ($item->tax ?? 0),
            static::normalizeTaxTypeValue($item->tax_type ?? null)
        );
    }

    protected static function quotationDetailColumnEntry(string $name, string $heading, array $rows): \Filament\Infolists\Components\TextEntry
    {
        return \Filament\Infolists\Components\TextEntry::make($name)
            ->label('')
            ->getStateUsing(function ($record) use ($heading, $rows) {
                $html = '<div class="space-y-1">';
                $html .= '<div class="mb-2 text-base font-semibold text-gray-950 dark:text-white">' . e($heading) . '</div>';

                foreach ($rows as [$label, $state]) {
                    $value = $state instanceof \Closure ? $state($record) : $state;
                    $html .= '<div class="flex gap-2 py-0.5 text-sm">';
                    $html .= '<span class="w-44 shrink-0 font-medium text-gray-600 dark:text-gray-400">' . e($label) . ' :</span>';
                    $html .= '<span class="min-w-0 flex-1 text-gray-950 dark:text-white">' . e((string) ($value ?? '-')) . '</span>';
                    $html .= '</div>';
                }

                $html .= '</div>';

                return $html;
            })
            ->html();
    }

    protected static function quotationStatusLegend(): HtmlString
    {
        return new HtmlString(
            '<style>.fi-ta-header:has(.dt-table-description-full-width){align-items:stretch}.fi-ta-header>.grid:has(.dt-table-description-full-width){width:100%;max-width:none;flex:1 1 100%;}.dt-table-description-full-width{width:100%;min-width:100%;max-width:none;box-sizing:border-box;}</style>' .
            '<div class="dt-table-description-full-width space-y-4 mb-6 w-full min-w-full max-w-none" style="width: 100%; min-width: 100%; max-width: none; box-sizing: border-box;">' .
            '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff; transition: all 0.2s;">' .
            '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; color: #374151;">' .
            '<span class="flex items-center gap-2" style="display: flex; align-items: center; gap: 8px;">' .
            '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; color: #3b82f6;">' .
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
            '</svg>' .
            'Panduan Quotation' .
            '</span>' .
            '<span class="transition group-open:rotate-180">' .
            '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
            '</span>' .
            '</summary>' .
            '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top: 12px; font-size: 14px; color: #4b5563; padding-left: 28px; border-left: 2px solid rgba(59, 130, 246, 0.3); display: flex; flex-direction: column; gap: 8px;">' .
            '<p><strong>Apa ini:</strong> Quotation adalah penawaran harga resmi ke customer sebelum dibuat menjadi Sales Order.</p>' .
            '<p><strong>Cara Pakai:</strong> Baris data pada list diberi warna agar status Quotation mudah dibaca tanpa membuka detail.</p>' .
            '<p><strong>Catatan:</strong> Warna legenda di bawah mengikuti warna baris data pada halaman list Quotation.</p>' .
            '</div>' .
            '</details>' .
            '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff;">' .
            '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">' .
            '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 16px; height: 16px;">' .
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
            '</svg>' .
            'Legenda Warna Status Baris Data' .
            '</h4>' .
            '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">' .
            '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: #ffffff; border: 1px solid #edf2f7;">' .
            '<div style="width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid #9ca3af; background-color: #ffffff; flex-shrink: 0;"></div>' .
            '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #4b5563;">Putih (Draft)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Quotation masih draft</span></div>' .
            '</div>' .
            '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">' .
            '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>' .
            '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (Approved)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Quotation sudah disetujui</span></div>' .
            '</div>' .
            '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(229, 231, 235, 0.45); border: 1px solid rgba(209, 213, 219, 0.85);">' .
            '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #6b7280; box-shadow: 0 1px 3px rgba(107, 114, 128, 0.4); flex-shrink: 0;"></div>' .
            '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #374151;">Abu-abu (Request Approve)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Menunggu persetujuan</span></div>' .
            '</div>' .
            '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 226, 226, 0.4); border: 1px solid rgba(254, 202, 202, 0.8);">' .
            '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #ef4444; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4); flex-shrink: 0;"></div>' .
            '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #991b1b;">Merah (Reject)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Quotation ditolak</span></div>' .
            '</div>' .
            '</div>' .
            '</div>'
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quotation')
                    ->columns(6)
                    ->schema([
                        Placeholder::make('status')
                            ->label('Status')
                            ->columnSpanFull()
                            ->content(function ($record) {
                                if (!$record) {
                                    return '-';
                                }
                                return new HtmlString(
                                    '<div class="space-y-2">' .
                                    static::quotationStatusBadge($record->status) .
                                    '<p class="text-xs text-gray-500">' .
                                    match ($record->status) {
                                        'draft' => 'Quotation masih draft dan belum diajukan.',
                                        'request_approve' => 'Quotation menunggu persetujuan.',
                                        'approve' => 'Quotation sudah disetujui dan siap dipakai.',
                                        'reject' => 'Quotation ditolak dan perlu revisi.',
                                        default => 'Status quotation belum ditentukan.',
                                    } .
                                    '</p>' .
                                    '</div>'
                                );
                            }),
                        Grid::make(6)->schema([
                            TextInput::make('quotation_number')
                                ->required()
                                ->label('Nomor Quotation')
                                ->columnSpan(2)
                                ->reactive()
                                ->validationMessages([
                                    'required' => 'Quotation number tidak boleh kosong',
                                    'unique' => 'Quotation number sudah digunakan'
                                ])
                                ->unique(ignoreRecord: true)
                                ->suffixAction(ActionsAction::make('generateQuotationNumber')
                                    ->icon('heroicon-m-arrow-path')
                                    ->tooltip('Generate Quotation Number')
                                    ->action(function ($set, $get, $state) {
                                        $quotationService = app(QuotationService::class);
                                        $set('quotation_number', $quotationService->generateCode());
                                    }))
                                ->maxLength(255),
                            Select::make('customer_id')
                                ->label('Customer')
                                ->columnSpan(2)
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->validationMessages([
                                    'required' => 'Customer wajib dipilih'
                                ])
                                ->relationship('customer', 'name')
                                ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                                    return "({$customer->code}) {$customer->name}";
                                })
                                ->createOptionForm([
                                    Fieldset::make('Form Customer')
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Kode Customer')
                                                ->required()
                                                ->reactive()
                                                ->suffixAction(ActionsAction::make('generateCode')
                                                    ->icon('heroicon-m-arrow-path')
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
                                                ->validationMessages([
                                                    'required' => 'Email tidak boleh kosong',
                                                    'email' => 'Format email tidak valid'
                                                ])
                                                ->maxLength(255),
                                            TextInput::make('fax')
                                                ->label('Fax')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Fax tidak boleh kosong'
                                                ]),
                                            TextInput::make('tempo_kredit')
                                                ->numeric()
                                                ->label('Tempo Kredit (Hari)')
                                                ->helperText('Hari')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Tempo kredit tidak boleh kosong',
                                                    'numeric' => 'Tempo kredit harus berupa angka'
                                                ])
                                                ->default(0),
                                            TextInput::make('kredit_limit')
                                                ->label('Kredit Limit (Rp.)')
                                                ->default(0)
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Kredit limit tidak boleh kosong',
                                                    'numeric' => 'Kredit limit harus berupa angka'
                                                ])
                                                ->indonesianMoney(),
                                            Radio::make('tipe_pembayaran')
                                                ->label('Tipe Bayar Customer')
                                                ->inlineLabel()
                                                ->options([
                                                    'Bebas' => 'Bebas',
                                                    'COD (Bayar Lunas)' => 'COD (Bayar Lunas)',
                                                    'Kredit' => 'Kredit (Bayar Kredit)'
                                                ])
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Tipe pembayaran wajib dipilih'
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
                                                    'required' => 'Tipe customer wajib dipilih'
                                                ]),
                                            Checkbox::make('isSpecial')
                                                ->label('Spesial (Ya / Tidak)'),
                                            TextInput::make('keterangan')
                                                ->label('Keterangan')
                                                ->nullable(),
                                        ]),
                                ])
                                ->afterStateUpdated(function ($set, $state) {
                                    if ($state) {
                                        $customer = Customer::find($state);
                                        if ($customer && $customer->tempo_kredit > 0) {
                                            $set('tempo_pembayaran', $customer->tempo_kredit);
                                        }
                                    }
                                })
                                ->required(),
                            Select::make('cabang_id')
                                ->label('Cabang')
                                ->columnSpan(2)
                                ->options(function () {
                                    $user = Auth::user();
                                    $manageType = $user?->manage_type ?? [];
                                    if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                        return \App\Models\Cabang::where('id', $user?->cabang_id)
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($c) => [$c->id => "{$c->kode} - {$c->nama}"]);
                                    }
                                    return \App\Models\Cabang::orderBy('kode')->limit(50)->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->kode} - {$c->nama}"]);
                                })
                                ->default(fn () => Auth::user()?->cabang_id)
                                ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ]),
                        Grid::make(6)->schema([
                            DatePicker::make('date')
                                ->label('Tanggal')
                                ->columnSpan(2)
                                ->validationMessages([
                                    'required' => 'Tanggal wajib dipilih'
                                ])
                                ->default(\Carbon\Carbon::today())
                                ->required(),
                            DatePicker::make('valid_until')
                                ->label('Valid Until')
                                ->columnSpan(2),
                            Select::make('currency_id')
                                ->label('Mata Uang')
                                ->columnSpan(2)
                                ->options(static::resolveCurrencyOptions())
                                ->default(fn() => static::resolveInitialCurrencyId())
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->live()
                                ->required()
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    $component->state($state ?: ($record?->currency_id ?? static::resolveInitialCurrencyId()));
                                })
                                ->afterStateUpdated(function ($state, $old, callable $get, callable $set) {
                                    $newCurrencyId = is_numeric($state) ? (int) $state : static::resolveDefaultCurrencyId();
                                    $oldCurrencyId = is_numeric($old) ? (int) $old : static::resolveDefaultCurrencyId();

                                    $set('exchange_rate', static::resolveExchangeRate($newCurrencyId));

                                    $items = $get('quotationItem') ?? [];
                                    foreach ($items as $key => $item) {
                                        if (! is_array($item)) {
                                            continue;
                                        }

                                        $unitPriceIdr = static::resolveQuotationItemUnitPriceIdr($item, $oldCurrencyId);
                                        $convertedUnitPrice = $newCurrencyId === $oldCurrencyId
                                            ? static::parseCurrencyState($item['unit_price'] ?? 0)
                                            : (float) CurrencyConversionResolver::convertFromIdrHighPrecision($unitPriceIdr, $newCurrencyId);

                                        $quantity = (float) ($item['quantity'] ?? 0);
                                        $discount = (float) ($item['discount'] ?? 0);
                                        $taxType = static::normalizeTaxTypeValue($item['tax_type'] ?? TaxTypeHelper::NONE);
                                        $tax = $taxType === TaxTypeHelper::NONE ? 0.0 : (float) ($item['tax'] ?? 0);
                                        $preview = static::calculateCurrencyPreview($quantity, $convertedUnitPrice, $discount, $tax, $taxType);

                                        $set("quotationItem.{$key}.unit_price", static::formatCurrencyInputState($convertedUnitPrice, $newCurrencyId));
                                        $set("quotationItem.{$key}.total_price", static::formatCurrencyPreviewState($preview['total'], $newCurrencyId));
                                        $set("quotationItem.{$key}.discount_nominal", static::formatCurrencyPreviewState($preview['discount_nominal'], $newCurrencyId));
                                        $set("quotationItem.{$key}.tax_nominal", static::formatCurrencyPreviewState($preview['tax_nominal'], $newCurrencyId));
                                        $set("quotationItem.{$key}.subtotal", static::formatCurrencyPreviewState($preview['subtotal'], $newCurrencyId));
                                        $set("quotationItem.{$key}.currency_symbol", static::resolveCurrencySymbol($newCurrencyId));
                                        $set("quotationItem.{$key}.unit_price_idr", $unitPriceIdr);

                                        $items[$key]['unit_price'] = static::formatCurrencyInputState($convertedUnitPrice, $newCurrencyId);
                                        $items[$key]['total_price'] = static::formatCurrencyPreviewState($preview['total'], $newCurrencyId);
                                        $items[$key]['discount_nominal'] = static::formatCurrencyPreviewState($preview['discount_nominal'], $newCurrencyId);
                                        $items[$key]['tax_nominal'] = static::formatCurrencyPreviewState($preview['tax_nominal'], $newCurrencyId);
                                        $items[$key]['subtotal'] = static::formatCurrencyPreviewState($preview['subtotal'], $newCurrencyId);
                                        $items[$key]['currency_symbol'] = static::resolveCurrencySymbol($newCurrencyId);
                                        $items[$key]['unit_price_idr'] = $unitPriceIdr;
                                    }

                                    $set('total_amount', static::formatMoneyState(static::calculateQuotationTotal($items), $newCurrencyId));
                                })
                                ->validationMessages([
                                    'required' => 'Mata uang wajib dipilih',
                                ]),
                            Hidden::make('exchange_rate')
                                ->default(fn (Get $get) => static::resolveExchangeRate(is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveInitialCurrencyId()))
                                ->dehydrated(true),
                        ]),
                        Grid::make(6)->schema([
                            TextInput::make('tempo_pembayaran')
                                ->label('Tempo Pembayaran (Hari)')
                                ->columnSpan(3)
                                ->numeric()
                                ->nullable()
                                ->minValue(0)
                                ->helperText('Masukkan jumlah hari tempo pembayaran khusus untuk quotation ini. Jika kosong, menggunakan default customer. Nilai ini akan diajukan untuk disetujui bersama discount.')
                                ->placeholder('Contoh: 30')
                                ->suffix('Hari'),
                            TextInput::make('total_amount')
                                ->label('Total Amount')
                                ->readOnly()
                                ->columnSpan(3)
                                ->reactive()
                                ->indonesianMoney()
                                ->prefix(fn(Get $get) => static::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()))
                                ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                ->default(0)
                                ->helperText('Total dihitung otomatis dari semua item')
                                ->afterStateHydrated(function ($component, $record) {
                                    if ($record) {
                                        $total = 0;
                                        foreach ($record->quotationItem as $item) {
                                            $total += (float) HelperController::hitungSubtotal(
                                                $item->quantity,
                                                (float)$item->unit_price,
                                                $item->discount,
                                                $item->tax,
                                                $item->tax_type ?? 'None'
                                            );
                                        }
                                        $component->state(static::formatMoneyState($total, is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId()));
                                    }
                                }),
                        ]),
                        Grid::make(6)->schema([
                            FileUpload::make('po_file_path')
                                ->label('File')
                                ->columnSpan(3)
                                ->directory('quotation')
                                ->downloadable()
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'image/*',
                                ])
                                ->maxSize('5120'),
                            TextArea::make('notes')
                                ->label('Note')
                                ->columnSpan(3),
                        ]),
                        Repeater::make('quotationItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(12)
                            ->minItems(1)
                            ->collapsible()
                            ->addAction(function (ActionsAction $action) {
                                return $action
                                    ->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Items')
                                    ->extraAttributes(fn ($component) => [
                                        'onclick' => (function () use ($component) {
                                            $event = 'repeater-collapse';
                                            $statePath = $component->getStatePath();
                                            $eventJs = 'String.fromCharCode(' . implode(',', array_map('ord', str_split($event))) . ')';
                                            $statePathJs = 'String.fromCharCode(' . implode(',', array_map('ord', str_split($statePath))) . ')';

                                            return "window.dispatchEvent(new CustomEvent({$eventJs}, { detail: {$statePathJs} }))";
                                        })(),
                                    ])
                                    ->action(function (Repeater $component): void {
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
                            ->itemLabel(function (array $state): ?string {
                                $productLabel = 'Item baru';
                                if (! empty($state['product_id'])) {
                                    $product = Product::withoutGlobalScope('product_cabang')->find($state['product_id']);
                                    $productLabel = $product ? "({$product->sku}) {$product->name}" : 'Item baru';
                                } else {
                                    $sku = trim((string) ($state['product_sku'] ?? ''));
                                    $name = trim((string) ($state['product_name'] ?? ''));
                                    $productLabel = $name !== '' ? $name : ($sku !== '' ? $sku : $productLabel);
                                }

                                $qty = $state['quantity'] ?? '0';
                                $subtotal = trim((string) ($state['subtotal'] ?? '0'));
                                $currencySymbol = trim((string) ($state['currency_symbol'] ?? 'Rp'));

                                return sprintf('Product: %s | Qty: %s | Subtotal: %s %s', $productLabel, $qty, $currencySymbol, $subtotal);
                            })
                            ->collapsed(function (?string $operation, ?\Filament\Forms\ComponentContainer $item, Repeater $component): bool {
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
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                return $data;
                            })
                            ->schema([
                                Grid::make(12)->schema([
                                    Select::make('product_id')
                                        ->label('Product')
                                        ->columnSpan(4)
                                        ->preload()
                                        ->searchable()
                                        ->validationMessages([
                                            'required' => 'Produk wajib dipilih'
                                        ])
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                            $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                            $currencyId = is_numeric($get('../../currency_id')) ? (int) $get('../../currency_id') : static::resolveDefaultCurrencyId();
                                            if ($product) {
                                                $numericUnit = CurrencyConversionResolver::convertFromIdr(
                                                    (float) MoneyHelper::parseHighPrecision($product->sell_price ?? 0),
                                                    $currencyId,
                                                    false
                                                );
                                                $set('unit_price', static::formatCurrencyInputState($numericUnit, $currencyId));
                                                $set('unit_price_idr', MoneyHelper::parseHighPrecision($product->sell_price ?? 0));
                                                $set('unit', $product->uom?->abbreviation ?? '-');
                                                $set('product_name', $product->name);
                                                $set('product_sku', $product->sku);
                                                $set('currency_symbol', static::resolveCurrencySymbol($currencyId));

                                                $taxType = static::normalizeTaxTypeValue($get('tax_type') ?? TaxTypeHelper::NONE);
                                                $taxRate = TaxDefaultResolver::resolveForProductId((int) $state, $taxType);
                                                $set('tax', $taxType === TaxTypeHelper::NONE ? 0 : $taxRate);
                                            }

                                            static::recalculateQuotationItemState($set, $get, $livewire);
                                        })
                                        ->relationship('product', 'name')
                                        ->getSearchResultsUsing(function (string $search) {
                                            return Product::query()
                                                ->where('name', 'like', "%{$search}%")
                                                ->orWhere('sku', 'like', "%{$search}%")
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(function (Product $p) {
                                                    return [$p->id => "({$p->sku}) {$p->name}"];
                                                })->toArray();
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Product $product) {
                                            return "({$product->sku}) {$product->name}";
                                        }),
                                    Hidden::make('product_name')
                                        ->dehydrated(false),
                                    Hidden::make('product_sku')
                                        ->dehydrated(false),
                                    Hidden::make('currency_symbol')
                                        ->dehydrated(false)
                                        ->default(fn(Get $get) => static::resolveCurrencySymbol(static::resolveQuotationFormCurrencyId($get)))
                                        ->afterStateHydrated(function ($component, $record) {
                                            $currencyId = is_numeric($record?->quotation?->currency_id)
                                                ? (int) $record->quotation->currency_id
                                                : static::resolveInitialCurrencyId();

                                            $component->state(static::resolveCurrencySymbol($currencyId));
                                        }),
                                    Hidden::make('unit_price_idr')
                                        ->dehydrated(true)
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record?->unit_price_idr !== null) {
                                                $component->state($record->unit_price_idr);
                                            }
                                        }),
                                    TextInput::make('unit')
                                        ->label('Satuan')
                                        ->columnSpan(2)
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default('-')
                                        ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record?->product) {
                                                $component->state($record->product->uom?->abbreviation ?? '-');
                                            }
                                        }),
                                    TextInput::make('quantity')
                                        ->label('Qty')
                                        ->columnSpan(2)
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Quantity wajib diisi'
                                        ])
                                        ->reactive()
                                        ->default(1)
                                        ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                            static::recalculateQuotationItemState($set, $get, $livewire);
                                        }),
                                    TextInput::make('unit_price')
                                        ->label('Unit Price')
                                        ->columnSpan(4)
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Unit price wajib diisi'
                                        ])
                                        ->reactive()
                                        ->indonesianMoney()
                                        ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveQuotationFormCurrencyId($get)))
                                        ->formatStateUsing(function ($state, Get $get) {
                                            if ($state === null || $state === '') {
                                                return '';
                                            }

                                            $currencyId = static::resolveQuotationFormCurrencyId($get);

                                            return static::formatCurrencyInputState($state, $currencyId);
                                        })
                                        ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                            static::recalculateQuotationItemState($set, $get, $livewire);
                                        }),
                                ]),
                                Grid::make(12)->schema([
                                    TextInput::make('total_price')
                                        ->label('Total (Harga x Qty)')
                                        ->columnSpan(3)
                                        ->readOnly()
                                        ->required()
                                        ->default(0)
                                        ->indonesianMoney()
                                        ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveQuotationFormCurrencyId($get)))
                                        ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record) {
                                                $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                                $total = (float) $record->quantity * (float) $record->unit_price;
                                                $component->state(static::formatCurrencyPreviewState($total, $currencyId));
                                            }
                                        }),
                                    TextInput::make('discount')
                                        ->label('Discount %')
                                        ->columnSpan(3)
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->default(0)
                                        ->suffix('%')
                                        ->reactive()
                                        ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                            static::recalculateQuotationItemState($set, $get, $livewire);
                                        }),
                                    TextInput::make('discount_nominal')
                                        ->label('Discount (Nominal)')
                                        ->columnSpan(3)
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0)
                                        ->indonesianMoney()
                                        ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveQuotationFormCurrencyId($get)))
                                        ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record) {
                                                $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                                $base = (float) $record->quantity * (float) $record->unit_price;
                                                $nominal = $base * ((float) $record->discount / 100);
                                                $component->state(static::formatCurrencyPreviewState($nominal, $currencyId));
                                            }
                                        }),
                                ]),
                                Grid::make(12)->schema([
                                    Radio::make('tax_type')
                                        ->label('Tipe Pajak')
                                        ->columnSpan(4)
                                        ->inline()
                                        ->reactive()
                                        ->required()
                                        ->default(TaxTypeHelper::NONE)
                                        ->options(static::taxTypeOptions())
                                        ->afterStateHydrated(function ($component, $state) {
                                            $component->state(static::normalizeTaxTypeValue($state));
                                        })
                                        ->afterStateUpdated(function ($state, callable $get, callable $set, $livewire) {
                                            $state = static::normalizeTaxTypeValue($state);
                                            $defaultTax = TaxDefaultResolver::resolveForProductId(
                                                is_numeric($get('product_id')) ? (int) $get('product_id') : null,
                                                $state
                                            );

                                            if ($state === TaxTypeHelper::NONE) {
                                                $set('tax', 0);
                                            } else {
                                                $set('tax', $defaultTax);
                                            }

                                            static::recalculateQuotationItemState($set, $get, $livewire);
                                        })
                                        ->validationMessages([
                                            'required' => 'Tipe Pajak wajib dipilih.',
                                        ]),
                                    TextInput::make('tax')
                                        ->label('Tax (%)')
                                        ->columnSpan(4)
                                        ->numeric()
                                        ->reactive()
                                        ->required()
                                        ->readOnly()
                                        ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                        ->validationMessages([
                                            'required' => 'Tax tidak boleh kosong'
                                        ])
                                        ->maxValue(100)
                                        ->default(function (callable $get, $record) {
                                            if ($record) {
                                                $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();

                                                if ($quotationItem) {
                                                    return $quotationItem->tax;
                                                }
                                            }

                                            return TaxDefaultResolver::resolveForProductId(
                                                is_numeric($get('product_id')) ? (int) $get('product_id') : null,
                                                $get('tax_type') ?? TaxTypeHelper::NONE
                                            );
                                        })
                                        ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                            if (static::normalizeTaxTypeValue($get('tax_type') ?? TaxTypeHelper::NONE) === TaxTypeHelper::NONE) {
                                                $set('tax', 0);
                                            }
                                            static::recalculateQuotationItemState($set, $get, $livewire);
                                        })
                                        ->suffix('%'),
                                    TextInput::make('tax_nominal')
                                        ->label('Nominal Pajak')
                                        ->columnSpan(4)
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0)
                                        ->indonesianMoney()
                                        ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveQuotationFormCurrencyId($get)))
                                        ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record) {
                                                $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                                $base = (float) $record->quantity
                                                    * (float) \App\Http\Controllers\HelperController::parseIndonesianMoney($record->unit_price ?? 0)
                                                    * (1 - (float) $record->discount / 100);
                                                try {
                                                    $r = \App\Services\TaxService::compute($base, (float) $record->tax, $record->tax_type ?? 'None');
                                                    $component->state(static::formatCurrencyPreviewState((float) $r['ppn'], $currencyId));
                                                } catch (\Throwable $e) {
                                                    $component->state('0');
                                                }
                                            }
                                        }),
                                ]),
                                Grid::make(12)->schema([
                                    TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->columnSpan(4)
                                        ->readOnly()
                                        ->dehydrated(false)
                                        ->default(0)
                                        ->indonesianMoney()
                                        ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveQuotationFormCurrencyId($get)))
                                        ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record) {
                                                $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                                $subtotal = HelperController::hitungSubtotal(
                                                    $record->quantity,
                                                    (float) $record->unit_price,
                                                    $record->discount,
                                                    $record->tax,
                                                    $record->tax_type ?? 'None'
                                                );
                                                $component->state(static::formatCurrencyPreviewState((float) $subtotal, $currencyId));
                                            }
                                        }),
                                    TextArea::make('notes')
                                        ->label('Note')
                                        ->columnSpan(8)
                                        ->nullable(),
                                ]),
                            ])
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('quotation_number')
                    ->label('Nomor Quotation')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->date()
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Mata Uang')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('tempo_pembayaran')
                    ->label('Tempo (Hari)')
                    ->suffix(' hari')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state, $record) => static::quotationMoney($state, is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId()))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return Str::upper(static::quotationStatusLabel($state));
                    })
                    ->color(function ($state) {
                        return static::quotationStatusColor($state);
                    }),

                TextColumn::make('po_file_path')
                    ->searchable(),

                TextColumn::make('notes')
                    ->label('Catatan')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),

                TextColumn::make('quotationItem.product.name')
                    ->label('Product')
                    ->searchable()
                    ->badge(),
                TextColumn::make('item_units')
                    ->label('Satuan')
                    ->state(function ($record) {
                        return $record->quotationItem
                            ->map(fn($item) => $item->product?->uom?->abbreviation ?? '-')
                            ->filter()
                            ->unique()
                            ->implode(', ');
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('requestApproveBy.name')
                    ->label('Request Approve By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('rejectBy.name')
                    ->label('Reject')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reject_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('approveBy.name')
                    ->label('Approve By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approve_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
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
            ->description(static::quotationStatusLegend())
            ->recordClasses(fn($record) => match ($record->status) {
                'draft' => '',
                'request_approve' => 'bg-gray-100',
                'approve' => 'bg-blue-100',
                'reject' => 'bg-red-100',
                default => '',
            })
            ->filters([
                SelectFilter::make('customer')
                    ->label('Customer')
                    ->searchable()
                    ->preload()
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                        return "({$customer->code}) {$customer->name}";
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'request_approve' => 'Request Approve',
                        'approve' => 'Approved',
                        'reject' => 'Rejected',
                    ])
                    ->default(null),
                Filter::make('date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Tanggal Dari'),
                        DatePicker::make('date_until')
                            ->label('Tanggal Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_from'] ?? null) {
                            $indicators['date_from'] = 'Tanggal dari ' . Carbon::parse($data['date_from'])->toFormattedDateString();
                        }

                        if ($data['date_until'] ?? null) {
                            $indicators['date_until'] = 'Tanggal sampai ' . Carbon::parse($data['date_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('primary'),
                    DeleteAction::make(),
                    Action::make('pdf_quotation')
                        ->label('Download PDF Quotation')
                        ->color('danger')
                        ->icon('heroicon-o-document')
                        ->hidden(function ($record) {
                            return $record->status != 'approve';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.quotation', [
                                'quotation' => $record,
                            ])->setPaper('A4', 'portrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Quotation_' . $record->quotation_number . '.pdf');
                        }),
                    Action::make('download_file')
                        ->label('Download File')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-on-square')
                        ->openUrlInNewTab()
                        ->hidden(function ($record) {
                            return !$record->po_file_path;
                        })
                        ->url(function ($record) {
                            return asset('storage' . $record->po_file_path);
                        }),
                    Action::make('request_approve')
                        ->label('Request Approve')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->color('success')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request-approve quotation') && $record->status == 'draft';
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Ajukan Persetujuan Quotation')
                        ->modalDescription(function ($record) {
                            $discountItems = $record->quotationItem->filter(fn($i) => $i->discount > 0);
                            $tempoText = $record->tempo_pembayaran
                                ? "Tempo pembayaran yang diajukan: **{$record->tempo_pembayaran} hari**."
                                : 'Tidak ada tempo khusus (gunakan default customer).';
                            $discountText = $discountItems->count() > 0
                                ? "Terdapat **{$discountItems->count()} item** dengan discount."
                                : 'Tidak ada discount.';
                            return new \Illuminate\Support\HtmlString(
                                "<div class='text-sm'><p>{$discountText}</p><p>{$tempoText}</p><p class='mt-2 text-warning-600'>Dengan mengajukan, manager akan mereview dan menyetujui term discount/tempo ini.</p></div>"
                            );
                        })
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->requestApprove($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Pengajuan persetujuan Quotation berhasil. Proses selanjutnya: Manajer Sales perlu mereview dan memberikan persetujuan atas Quotation ini.");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('approve quotation') && ($record->status == 'request_approve');
                        })
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Setujui Quotation')
                        ->modalDescription(function ($record) {
                            $discountItems = $record->quotationItem->filter(fn($i) => $i->discount > 0);
                            $tempoText = $record->tempo_pembayaran
                                ? "<strong>Tempo Pembayaran:</strong> {$record->tempo_pembayaran} hari"
                                : '<strong>Tempo Pembayaran:</strong> Default customer';
                            $discountText = $discountItems->count() > 0
                                ? "<strong>Discount:</strong> {$discountItems->count()} item memiliki discount"
                                : '<strong>Discount:</strong> Tidak ada discount';
                            $totalText = \App\Helpers\MoneyHelper::rupiah($record->total_amount);
                            return new \Illuminate\Support\HtmlString(
                                "<div class='text-sm space-y-1'><p>{$discountText}</p><p>{$tempoText}</p><p><strong>Total Penawaran:</strong> {$totalText}</p><p class='mt-2 text-success-600'>Dengan menyetujui, term discount dan tempo pembayaran ini akan resmi berlaku untuk Sales Order yang dibuat dari quotation ini.</p></div>"
                            );
                        })
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->approve($record);

                            HelperController::sendNotification(isSuccess: true, title: "Success", message: "Quotation berhasil disetujui. Proses selanjutnya: Tim Sales perlu membuat Sale Order berdasarkan Quotation yang telah disetujui ini.");
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('reject quotation') && ($record->status == 'request_approve');
                        })
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->reject($record);
                            HelperController::sendNotification(isSuccess: true, title: "Danger", message: "Quotation ditolak. Proses selanjutnya: Tim Sales perlu merevisi penawaran sesuai catatan penolakan dan mengajukan kembali untuk persetujuan.");
                        }),
                    Action::make('sync_total_amount')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->action(function ($record) {
                            $quotationService = app(QuotationService::class);
                            $quotationService->updateTotalAmount($record);

                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                        }),
                    Action::make('create_sale_order')
                        ->label('Buat Sales Order')
                        ->icon('heroicon-o-plus')
                        ->color('success')
                        ->visible(function ($record) {
                            $user = Auth::user();
                            $hasPermission = $user && $user->hasPermissionTo('create sales order');
                            $isApproved = $record->status == 'approve';

                            Log::debug('QuotationResource: create_sale_order visibility check', [
                                'quotation_id' => $record->id,
                                'quotation_number' => $record->quotation_number,
                                'status' => $record->status,
                                'is_approved' => $isApproved,
                                'user_id' => $user ? $user->id : null,
                                'user_name' => $user ? $user->name : null,
                                'has_permission' => $hasPermission,
                                'visible' => $isApproved && $hasPermission
                            ]);

                            return $isApproved && $hasPermission;
                        })
                        ->form([
                            Section::make('Informasi Quotation')
                                ->schema([
                                    Placeholder::make('quotation_number')
                                        ->label('Nomor Quotation')
                                        ->content(fn($record) => $record->quotation_number),
                                    Placeholder::make('customer_name')
                                        ->label('Customer')
                                        ->content(fn($record) => $record->customer->name ?? '-'),
                                    Placeholder::make('total_amount')
                                        ->label('Total Amount')
                                        ->content(fn($record) => static::quotationMoney($record->total_amount, is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId())),
                                    Placeholder::make('currency_code')
                                        ->label('Mata Uang')
                                        ->content(fn($record) => $record->currency?->code ?? Currency::find(static::resolveDefaultCurrencyId())?->code ?? '-'),
                                    Placeholder::make('item_count')
                                        ->label('Jumlah Item')
                                        ->content(fn($record) => $record->quotationItem->count() . ' item(s)'),
                                ])->columns(2),
                            Section::make('Sales Order Baru')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('so_number')
                                                ->label('Nomor Sales Order')
                                                ->default(fn() => app(SalesOrderService::class)->generateSoNumber())
                                                ->required()
                                                ->unique(table: 'sale_orders', column: 'so_number')
                                                ->validationMessages([
                                                    'required' => 'Nomor Sales Order wajib diisi',
                                                    'unique' => 'Nomor Sales Order sudah digunakan'
                                                ])
                                                ->suffixAction(
                                                    \Filament\Forms\Components\Actions\Action::make('generateSoNumber')
                                                        ->icon('heroicon-o-arrow-path')
                                                        ->tooltip('Generate Nomor Sales Order Baru')
                                                        ->action(function ($set) {
                                                            $set('so_number', app(SalesOrderService::class)->generateSoNumber());
                                                        })
                                                ),
                                            DatePicker::make('order_date')
                                                ->label('Tanggal Order')
                                                ->default(now())
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Tanggal order wajib dipilih'
                                                ]),
                                            DatePicker::make('delivery_date')
                                                ->label('Tanggal Pengiriman')
                                                ->validationMessages([
                                                    'required' => 'Tanggal pengiriman wajib dipilih'
                                                ]),
                                            Select::make('tipe_pengiriman')
                                                ->label('Tipe Pengiriman')
                                                ->options([
                                                    'Ambil Sendiri' => 'Ambil Sendiri',
                                                    'Kirim Langsung' => 'Kirim Langsung'
                                                ])
                                                ->default('Kirim Langsung')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Tipe pengiriman wajib dipilih'
                                                ]),
                                        ]),
                                    Repeater::make('saleOrderItems')
                                        ->label('Item Sales Order')
                                        ->schema([
                                            Hidden::make('product_id'),
                                            Hidden::make('tax_type')->default('None'),
                                            Hidden::make('currency_id')
                                                ->default(fn($record) => is_numeric($record?->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId()),
                                            Placeholder::make('product_info')
                                                ->label('Produk')
                                                ->content(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    if ($quotationItem) {
                                                        return "({$quotationItem->product->sku}) {$quotationItem->product->name}";
                                                    }
                                                    return '-';
                                                })
                                                ->columnSpan(2),
                                            TextInput::make('quantity')
                                                ->label('Quantity')
                                                ->numeric()
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    return $quotationItem ? $quotationItem->quantity : 0;
                                                })
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Quantity wajib diisi',
                                                    'numeric' => 'Quantity harus berupa angka'
                                                ])
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $state ?? 0;
                                                    $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId();
                                                    $unitPrice = static::parseCurrencyState($get('unit_price') ?? 0);
                                                    $discount = $get('discount') ?? 0;
                                                    $tax = $get('tax') ?? 0;
                                                    $taxType = $get('tax_type') ?? 'None';
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax, $taxType);
                                                    $set('subtotal', static::formatCurrencyPreviewState($subtotal, $currencyId));
                                                    $set('tax_nominal', static::formatCurrencyPreviewState(HelperController::hitungTaxNominal($quantity, $unitPrice, $discount, $tax, $taxType), $currencyId));
                                                }),
                                            TextInput::make('unit_price')
                                                ->label('Unit Price')
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    $currencyId = is_numeric($record?->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId();
                                                    return $quotationItem
                                                        ? static::formatCurrencyInputState((float) $quotationItem->unit_price, $currencyId)
                                                        : 0;
                                                })
                                                ->prefix(fn($get) => static::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()))
                                                ->required()
                                                ->indonesianMoney()
                                                ->validationMessages([
                                                    'required' => 'Unit Price wajib diisi',
                                                    'numeric' => 'Unit Price harus berupa angka'
                                                ])
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $get('quantity') ?? 0;
                                                    $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId();
                                                    $unitPrice = static::parseCurrencyState($state ?? 0);
                                                    $discount = $get('discount') ?? 0;
                                                    $tax = $get('tax') ?? 0;
                                                    $taxType = $get('tax_type') ?? 'None';
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax, $taxType);
                                                    $set('subtotal', static::formatCurrencyPreviewState($subtotal, $currencyId));
                                                    $set('tax_nominal', static::formatCurrencyPreviewState(HelperController::hitungTaxNominal($quantity, $unitPrice, $discount, $tax, $taxType), $currencyId));
                                                }),
                                            Select::make('warehouse_id')
                                                ->label('Gudang')
                                                ->searchable()
                                                ->preload()
                                                ->options(function ($get) {
                                                    return WarehouseStockOptions::forProduct(
                                                        $get('product_id'),
                                                        $get('warehouse_id'),
                                                    );
                                                })
                                                ->helperText('Hanya menampilkan gudang yang memiliki stok bebas untuk produk ini.')
                                                ->validationMessages([
                                                    'required' => 'Gudang wajib dipilih'
                                                ])
                                                ->default(function ($get) {
                                                    return array_key_first(WarehouseStockOptions::forProduct($get('product_id')));
                                                })
                                                ->reactive()
                                                ->afterStateUpdated(function ($set) {
                                                    $set('rak_id', null); // Reset rak when warehouse changes
                                                }),
                                            Select::make('rak_id')
                                                ->label('Rak')
                                                ->searchable(['code', 'name'])
                                                ->preload()
                                                ->options(function ($get) {
                                                    $warehouseId = $get('warehouse_id');
                                                    if ($warehouseId) {
                                                        return \App\Models\Rak::where('warehouse_id', $warehouseId)->pluck('name', 'id')->map(function ($name, $id) {
                                                            $rak = \App\Models\Rak::find($id);
                                                            return "({$rak->code}) {$name}";
                                                        });
                                                    }
                                                    return [];
                                                })
                                                ->nullable(),
                                            TextInput::make('discount')
                                                ->label('Discount (%)')
                                                ->numeric()
                                                ->default(function ($get, $record) {
                                                    $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                    return $quotationItem ? $quotationItem->discount : 0;
                                                })
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $quantity = $get('quantity') ?? 0;
                                                    $currencyId = is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId();
                                                    $unitPrice = static::parseCurrencyState($get('unit_price') ?? 0);
                                                    $discount = $state ?? 0;
                                                    $tax = $get('tax') ?? 0;
                                                    $taxType = $get('tax_type') ?? 'None';
                                                    $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax, $taxType);
                                                    $set('subtotal', static::formatCurrencyPreviewState($subtotal, $currencyId));
                                                    $set('tax_nominal', static::formatCurrencyPreviewState(HelperController::hitungTaxNominal($quantity, $unitPrice, $discount, $tax, $taxType), $currencyId));
                                                }),
                                            TextInput::make('tax')
                                                ->label('Tax (%)')
                                                ->numeric()
                                                ->readOnly()
                                                ->default(0),
                                            TextInput::make('subtotal')
                                                ->label('Subtotal')
                                                ->prefix(fn($get) => static::resolveCurrencySymbol(is_numeric($get('currency_id')) ? (int) $get('currency_id') : static::resolveDefaultCurrencyId()))
                                                ->indonesianMoney()
                                                ->readOnly()
                                                ->default(0),
                                        ])
                                        ->columns(3)
                                        ->defaultItems(function ($record) {
                                            return $record && $record->quotationItem ? $record->quotationItem->count() : 0;
                                        })
                                        ->minItems(1)
                                        ->validationMessages([
                                            'minItems' => 'Minimal harus ada 1 item sales order'
                                        ])
                                        ->default(function ($record) {
                                            if ($record && $record->quotationItem) {
                                                $items = [];
                                                foreach ($record->quotationItem as $quotationItem) {
                                                    $currencyId = is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId();
                                                    $items[] = [
                                                        'product_id' => $quotationItem->product_id,
                                                        'quantity' => $quotationItem->quantity,
                                                        'currency_id' => $currencyId,
                                                        'unit_price' => static::formatCurrencyInputState((float) $quotationItem->unit_price, $currencyId),
                                                        'discount' => $quotationItem->discount,
                                                        'tax' => $quotationItem->tax,
                                                        'tax_type' => $quotationItem->tax_type ?? 'None',
                                                        'warehouse_id' => null,
                                                        'rak_id' => null,
                                                        'tax_nominal' => static::formatCurrencyPreviewState(HelperController::hitungTaxNominal(
                                                            $quotationItem->quantity,
                                                            (float) $quotationItem->unit_price,
                                                            $quotationItem->discount,
                                                            $quotationItem->tax,
                                                            $quotationItem->tax_type ?? 'None'
                                                        ), $currencyId),
                                                        'subtotal' => static::formatCurrencyPreviewState(HelperController::hitungSubtotal(
                                                            $quotationItem->quantity,
                                                            (float) $quotationItem->unit_price,
                                                            $quotationItem->discount,
                                                            $quotationItem->tax,
                                                            $quotationItem->tax_type ?? 'None'
                                                        ), $currencyId)
                                                    ];
                                                }
                                                return $items;
                                            }
                                            return [];
                                        })
                                        ->columnSpanFull(),
                                    Textarea::make('notes')
                                        ->label('Catatan')
                                        ->placeholder('Catatan tambahan untuk sales order (opsional)')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                        ])
                        ->action(function ($data, $record) {
                            $salesOrderService = app(SalesOrderService::class);

                            // Karena quotation sudah di-approve (termasuk discount & tempo pembayaran),
                            // SO yang dibuat dari quotation approved langsung berstatus 'approved'.
                            // Tidak perlu approval ulang di level SO karena sudah di-approve di Quotation.
                            $soStatus = 'draft';
                            $recordCurrencyId = is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId();
                            $saleOrder = SaleOrder::create([
                                'customer_id' => $record->customer_id,
                                'quotation_id' => $record->id,
                                'cabang_id' => $record->cabang_id, // Warisi cabang dari quotation
                                'so_number' => $data['so_number'],
                                'order_date' => $data['order_date'],
                                'delivery_date' => $data['delivery_date'],
                                'tipe_pengiriman' => $data['tipe_pengiriman'],
                                'status' => $soStatus,
                                'total_amount' => CurrencyConversionResolver::convertToIdr(MoneyHelper::parseHighPrecision($record->total_amount ?? 0), $recordCurrencyId, false),
                                'currency_id' => $recordCurrencyId,
                                'exchange_rate' => static::resolveExchangeRate($recordCurrencyId),
                                'tempo_pembayaran' => $record->tempo_pembayaran, // Warisi tempo yang sudah disetujui
                                'approve_by' => Auth::id(), // Marking as approved by the person creating SO from approved quotation
                                'approve_at' => now(),
                                'created_by' => Auth::id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            // Create sale order items from form data
                            if (isset($data['saleOrderItems']) && is_array($data['saleOrderItems'])) {
                                foreach ($data['saleOrderItems'] as $item) {
                                    $saleOrder->saleOrderItem()->create([
                                        'product_id' => $item['product_id'],
                                        'quantity' => $item['quantity'],
                                        'unit_price' => static::parseCurrencyState($item['unit_price']),
                                        'discount' => $item['discount'] ?? 0,
                                        'tax' => $item['tax'] ?? 0,
                                        'tipe_pajak' => $item['tax_type'] ?? 'None',
                                        'currency_id' => is_numeric($item['currency_id'] ?? null) ? (int) $item['currency_id'] : $recordCurrencyId,
                                        'warehouse_id' => $item['warehouse_id'],
                                        'rak_id' => $item['rak_id'] ?? null,
                                    ]);
                                }
                            } else {
                                // Fallback to quotation items if repeater data is not available
                                foreach ($record->quotationItem as $quotationItem) {
                                    $saleOrder->saleOrderItem()->create([
                                        'product_id' => $quotationItem->product_id,
                                        'quantity' => $quotationItem->quantity,
                                        'unit_price' => $quotationItem->unit_price,
                                        'discount' => $quotationItem->discount,
                                        'tax' => $quotationItem->tax,
                                        'tipe_pajak' => $quotationItem->tax_type ?? 'None',
                                        'currency_id' => $recordCurrencyId,
                                        'warehouse_id' => 1, // Default warehouse
                                        'rak_id' => null,
                                    ]);
                                }
                            }

                            // Update total amount
                            $salesOrderService->updateTotalAmount($saleOrder);

                            $saleOrder->load('saleOrderItem.warehouseAllocations', 'saleOrderItem.product');

                            $saleOrder->update([
                                'status' => 'approved',
                                'approve_by' => Auth::id(),
                                'approve_at' => now(),
                            ]);

                            HelperController::sendNotification(isSuccess: true, title: "Success", message: "Sale Order {$data['so_number']} berhasil dibuat dari Quotation dan disetujui. Proses selanjutnya: Tim Gudang/Logistik dapat melanjutkan ke Delivery Order.");

                            // Redirect to edit page
                            return redirect()->route('filament.admin.resources.sale-orders.edit', $saleOrder);
                        })
                        ->modalHeading('Buat Sales Order dari Quotation')
                        ->modalDescription('Buat sales order baru berdasarkan quotation ini. Periksa informasi dan isi nomor sales order.')
                        ->modalSubmitActionLabel('Buat Sales Order')
                        ->modalCancelActionLabel('Batal')
                        ->slideOver()
                ])->button()
                    ->label('Action')
                    ->color('primary'),

            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('sync_total_amounts')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $quotationService = app(QuotationService::class);
                            foreach ($records as $record) {
                                $quotationService->updateTotalAmount($record);
                            }
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil diupdate");
                        })
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Quotation</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Quotation adalah penawaran harga kepada customer yang perlu disetujui sebelum menjadi Sales Order.</li>' .
                    '<li><strong>Status Flow:</strong> Draft → Request Approve → Approved/Rejected. Hanya quotation approved yang bisa dijadikan Sales Order.</li>' .
                    '<li><strong>Validitas:</strong> Perhatikan tanggal <em>Valid Until</em> - quotation expired tidak bisa digunakan.</li>' .
                    '<li><strong>Actions:</strong> <em>Request Approve</em> (draft), <em>Approve/Reject</em> (request_approve), <em>Sync Total</em> (update amount), <em>Create Sale Order</em> (approved only).</li>' .
                    '<li><strong>PO File:</strong> Upload file Purchase Order customer sebagai referensi (opsional).</li>' .
                    '<li><strong>Integration:</strong> Quotation approved otomatis bisa dikonversi menjadi Sales Order dengan semua detail item.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ));

        return $table;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $cabangId = $user->cabang_id;
            // Filter by quotations.cabang_id; include legacy NULL records for this branch's customer
            $query->where(function ($q) use ($cabangId) {
                $q->where('cabang_id', $cabangId)
                  ->orWhereNull('cabang_id'); // backward-compat: old quotations without cabang_id
            });
        }

        return $query;
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Informasi Quotation')
                    ->columns(3)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('quotation_number')
                            ->label('Quotation Number'),
                        \Filament\Infolists\Components\TextEntry::make('customer.name')
                            ->label('Customer')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('cabang.nama')
                            ->label('Cabang')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn($state) => static::quotationStatusLabel($state))
                            ->color(fn($state) => static::quotationStatusColor($state))
                            ->badge(),
                        \Filament\Infolists\Components\TextEntry::make('date')
                            ->label('Date')
                            ->date('d/m/Y'),
                        \Filament\Infolists\Components\TextEntry::make('valid_until')
                            ->label('Valid Until')
                            ->date('d/m/Y')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('tempo_pembayaran')
                            ->label('Tempo Pembayaran')
                            ->formatStateUsing(fn($state) => $state ? $state . ' Hari' : '-'),
                        \Filament\Infolists\Components\TextEntry::make('currency.code')
                            ->label('Mata Uang')
                            ->placeholder(fn($record) => Currency::find(static::resolveDefaultCurrencyId())?->code ?? '-'),
                        \Filament\Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->formatStateUsing(fn($state, $record) => static::quotationMoney($state, is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId())),
                        \Filament\Infolists\Components\TextEntry::make('createdBy.name')
                            ->label('Created By')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('-')
                            ->columnSpan(2),
                        \Filament\Infolists\Components\TextEntry::make('po_file_path')
                            ->label('PO File')
                            ->placeholder('-'),
                    ]),
                \Filament\Infolists\Components\Section::make('Ringkasan Quotation')
                    ->columns(3)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('items_count')
                            ->label('Jumlah Item')
                            ->getStateUsing(fn($record) => $record->quotationItem->count()),
                        \Filament\Infolists\Components\TextEntry::make('total_quantity')
                            ->label('Total Qty')
                            ->getStateUsing(fn($record) => (float) $record->quotationItem->sum('quantity')),
                        \Filament\Infolists\Components\TextEntry::make('total_discount_nominal')
                            ->label('Total Discount (Nominal)')
                            ->getStateUsing(function ($record) {
                                $total = $record->quotationItem->sum(function ($item) {
                                    return ((float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0))
                                        * ((float) ($item->discount ?? 0) / 100);
                                });

                                return static::quotationMoney($total, is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId());
                            }),
                        \Filament\Infolists\Components\TextEntry::make('total_tax_nominal')
                            ->label('Total Nominal Pajak')
                            ->getStateUsing(fn($record) => static::quotationMoney(
                                $record->quotationItem->sum(fn($item) => static::quotationItemTaxNominal($item)),
                                is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId()
                            )),
                        \Filament\Infolists\Components\TextEntry::make('summary_total_amount')
                            ->label('Total Quotation')
                            ->getStateUsing(fn($record) => static::quotationMoney($record->total_amount, is_numeric($record->currency_id) ? (int) $record->currency_id : static::resolveDefaultCurrencyId())),
                    ]),
                \Filament\Infolists\Components\Section::make('Detail Item Quotation')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('quotationItem')
                            ->label('')
                            ->columnSpanFull()
                            ->schema([
                                \Filament\Infolists\Components\Section::make(function ($record) {
                                    $productName = $record->product
                                        ? "({$record->product->sku}) {$record->product->name}"
                                        : '-';
                                    $qty = (float) ($record->quantity ?? 0);
                                    $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                    $subtotal = static::quotationMoney(static::quotationItemSubtotal($record), $currencyId);

                                    return "Product: {$productName} | Qty: {$qty} | Subtotal: {$subtotal}";
                                })
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        \Filament\Infolists\Components\Grid::make(2)
                                            ->schema([
                                                \Filament\Infolists\Components\Group::make([
                                                    static::quotationDetailColumnEntry(
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
                                                            ['Note', fn($record) => $record->notes ?? '-'],
                                                        ]
                                                    ),
                                                ])
                                                    ->columnSpan(1)
                                                    ->columns(1),
                                                \Filament\Infolists\Components\Group::make([
                                                    static::quotationDetailColumnEntry(
                                                        'price_column',
                                                        'Price',
                                                        [
                                                            ['Unit Price', fn($record) => static::quotationMoney($record->unit_price ?? 0, is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId())],
                                                            ['Total (Harga x Qty)', function ($record) {
                                                                $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                                                $total = (float) ($record->quantity ?? 0) * (float) ($record->unit_price ?? 0);

                                                                return static::quotationMoney($total, $currencyId);
                                                            }],
                                                            ['Discount', fn($record) => number_format((float) ($record->discount ?? 0), 0, ',', '.') . '%'],
                                                            ['Discount (Nominal)', function ($record) {
                                                                $currencyId = is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId();
                                                                $total = (float) ($record->quantity ?? 0) * (float) ($record->unit_price ?? 0);
                                                                $discount = $total * ((float) ($record->discount ?? 0) / 100);

                                                                return static::quotationMoney($discount, $currencyId);
                                                            }],
                                                            ['Tipe Pajak', fn($record) => static::taxTypeLabel($record->tax_type ?? null)],
                                                            ['Tax (%)', fn($record) => number_format((float) ($record->tax ?? 0), 0, ',', '.') . '%'],
                                                            ['Nominal Pajak', fn($record) => static::quotationMoney(static::quotationItemTaxNominal($record), is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId())],
                                                            ['Subtotal', fn($record) => static::quotationMoney(static::quotationItemSubtotal($record), is_numeric($record->quotation?->currency_id) ? (int) $record->quotation->currency_id : static::resolveDefaultCurrencyId())],
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
