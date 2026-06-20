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
use App\Rules\InternationalPhoneNumber;
use App\Services\CustomerService;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use App\Services\CreditValidationService;
use App\Support\CurrencyConversionResolver;
use App\Helpers\MoneyHelper;
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
use Filament\Forms\Get;
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

    protected static function convertCurrencyAmount(float $amount, ?int $fromCurrencyId, ?int $toCurrencyId): float
    {
        // Use centralized resolver with high-precision intermediate calculation,
        // but return a non-rounded intermediate value for UI (rounded where needed on persist).
        return (float) \App\Support\CurrencyConversionResolver::convertBetweenCurrencies($amount, $fromCurrencyId, $toCurrencyId, false);
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

    protected static function normalizeCurrencyDisplayValue(float $amount, ?int $currencyId): float
    {
        $currencyCode = Currency::find($currencyId)?->code;

        return $currencyCode === 'IDR' ? round($amount, 2) : $amount;
    }

    protected static function currencyInputDecimals(?int $currencyId): int
    {
        return static::isIdrCurrency($currencyId) ? 2 : 10;
    }

    protected static function currencyPreviewDecimals(?int $currencyId): int
    {
        return 2;
    }

    protected static function isIdrCurrency(?int $currencyId): bool
    {
        if ($currencyId === null) {
            $currencyId = static::resolveDefaultCurrencyId();
        }

        return Currency::find($currencyId)?->code === 'IDR';
    }

    protected static function formatCurrencyInputState(mixed $amount, ?int $currencyId): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $decimals = static::currencyInputDecimals($currencyId);
        $formatted = number_format(static::parseCurrencyState($amount), $decimals, ',', '.');

        if ($decimals <= 2) {
            return $formatted;
        }

        [$whole, $fraction] = explode(',', $formatted, 2);
        $fraction = rtrim($fraction, '0');
        $fraction = strlen($fraction) < 2 ? str_pad($fraction, 2, '0') : $fraction;

        return "{$whole},{$fraction}";
    }

    protected static function formatCurrencyPreviewState(mixed $amount, ?int $currencyId): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format(static::parseCurrencyState($amount), static::currencyPreviewDecimals($currencyId), ',', '.');
    }

    protected static function formatCurrencyAmount(?int $currencyId, mixed $amount): string
    {
        return static::resolveCurrencySymbol($currencyId) . ' ' . static::formatCurrencyPreviewState($amount, $currencyId);
    }

    protected static function calculateCurrencyPreview(float $quantity, float $unitPrice, float $discount, float $tax, ?string $taxType, ?int $currencyId): array
    {
        $normalizedTaxType = static::normalizeTaxTypeValue($taxType);
        $base = $quantity * $unitPrice;
        $discountNominal = $base * ($discount / 100);
        $afterDiscount = $base - $discountNominal;
        $taxNominal = round($afterDiscount * ($tax / 100), 2);

        return [
            'total' => $base,
            'discount_nominal' => $discountNominal,
            'tax_nominal' => $normalizedTaxType === 'none' ? 0.0 : $taxNominal,
            'subtotal' => $normalizedTaxType === 'inklusif' ? $afterDiscount : $afterDiscount + $taxNominal,
        ];
    }

    protected static function saleOrderDetailColumnEntry(string $name, string $heading, array $rows): \Filament\Infolists\Components\TextEntry
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

    protected static function isReferQuotationForm(Get $get): bool
    {
        if ((string) ($get('options_form') ?? $get('../../options_form') ?? '') === '2') {
            return true;
        }

        return filled($get('quotation_id') ?? $get('../../quotation_id') ?? null);
    }

    protected static function lockedInputAttributes(Get $get): array
    {
        if (! static::isReferQuotationForm($get)) {
            return [];
        }

        return static::readOnlyGrayInputAttributes();
    }

    protected static function readOnlyGrayInputAttributes(): array
    {
        return [
            'class' => 'bg-gray-100 text-gray-500 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400',
            'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;',
        ];
    }

    protected static function statusRowClass(?string $status): string
    {
        return match ($status) {
            'request_approve' => 'bg-gray-100',
            'approved', 'confirmed', 'received' => 'bg-blue-100',
            'request_close' => 'bg-yellow-100',
            'completed' => 'bg-green-100',
            'closed', 'reject', 'rejected', 'canceled', 'cancelled' => 'bg-red-100',
            default => '',
        };
    }

    public static function normalizeFormDataForPersist(array $data): array
    {
        $currencyId = static::resolveDefaultCurrencyId();

        $data['currency_id'] = $currencyId;
        $data['exchange_rate'] = static::resolveExchangeRate($currencyId);

        $items = [];
        foreach (($data['saleOrderItem'] ?? []) as $item) {
            $taxType = static::normalizeTaxTypeValue($item['tipe_pajak'] ?? null);
            $unitPrice = (float) static::parseCurrencyState($item['unit_price'] ?? 0);
            $itemCurrencyId = static::resolveItemCurrencyId($item['currency_id'] ?? null);
            $quantity = (float) ($item['quantity'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $taxRate = $taxType === 'none' ? 0.0 : (float) \App\Models\TaxSetting::activeRate('PPN');
            $preview = static::calculateCurrencyPreview($quantity, $unitPrice, $discount, $taxRate, $taxType, $itemCurrencyId);

            $item['tipe_pajak'] = $taxType;
            $item['tax'] = $taxRate;
            $item['currency_id'] = $itemCurrencyId;
            $item['unit_price'] = $unitPrice;
            $item['subtotal'] = static::formatCurrencyPreviewState($preview['subtotal'], $itemCurrencyId);
            $item['tax_nominal'] = static::formatCurrencyPreviewState($preview['tax_nominal'], $itemCurrencyId);
            $items[] = $item;
        }

        if (! empty($items)) {
            $data['saleOrderItem'] = $items;
            $data['total_amount'] = round(static::calculateSaleOrderTotalIdr($items), 2);
        }

        return $data;
    }

    protected static function resolveItemCurrencyId(mixed $currencyId = null): int
    {
        return is_numeric($currencyId) ? (int) $currencyId : static::resolveDefaultCurrencyId();
    }

    protected static function calculateSaleOrderTotalIdr(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $currencyId = static::resolveItemCurrencyId($item['currency_id'] ?? null);
            $preview = static::calculateCurrencyPreview(
                (float) ($item['quantity'] ?? 0),
                static::parseCurrencyState($item['unit_price'] ?? 0),
                (float) ($item['discount'] ?? 0),
                (float) ($item['tax'] ?? 0),
                $item['tipe_pajak'] ?? null,
                $currencyId
            );

            $total += CurrencyConversionResolver::convertToIdr(MoneyHelper::parseHighPrecision($preview['subtotal']), $currencyId, false);
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

    protected static function getWarehouseAllocationOptions(?int $productId, ?int $selectedWarehouseId = null): array
    {
        return WarehouseStockOptions::forProduct($productId, $selectedWarehouseId);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Penjualan')
                    ->columns(2)
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
                                    $quotationCurrencyId = is_numeric($quotation->currency_id) ? (int) $quotation->currency_id : static::resolveDefaultCurrencyId();
                                    foreach ($quotation->quotationItem as $item) {
                                        $tipePajak = static::normalizeTaxTypeValue($item->tax_type);
                                        $unitPrice = (float) static::parseCurrencyState($item->unit_price);
                                        $itemCurrencyId = $quotationCurrencyId;
                                        array_push($items, [
                                            'product_id' => $item->product_id,
                                            'quantity' => $item->quantity,
                                            'currency_id' => $itemCurrencyId,
                                            'unit_price' => static::formatCurrencyInputState($unitPrice, $itemCurrencyId),
                                            'discount' => $item->discount,
                                            'tax' => $item->tax,
                                            'tipe_pajak' => $tipePajak,
                                            'notes' => $item->notes,
                                            'warehouse_id' => null,
                                            'subtotal' => static::formatMoneyState(HelperController::hitungSubtotal($item->quantity, $unitPrice, $item->discount, $item->tax, $tipePajak), $itemCurrencyId),
                                            'discount_nominal' => static::formatMoneyState($item->quantity * $unitPrice * ((float) $item->discount / 100), $itemCurrencyId),
                                            'tax_nominal' => static::formatMoneyState(HelperController::hitungTaxNominal($item->quantity, $unitPrice, $item->discount, $item->tax, $tipePajak), $itemCurrencyId),
                                            'rak_id' => null,
                                            'unit' => $item->product->uom?->abbreviation ?? '-',
                                            'total' => static::formatMoneyState($item->quantity * $unitPrice, $itemCurrencyId),
                                        ]);
                                    }
                                    $set('total_amount', static::formatMoneyState(
                                        CurrencyConversionResolver::convertToIdr(MoneyHelper::parseHighPrecision($quotation->total_amount ?? 0), $quotationCurrencyId, false),
                                        static::resolveDefaultCurrencyId()
                                    ));
                                    $set('customer_id', $quotation->customer_id);
                                    $set('cabang_id', $quotation->cabang_id);
                                    $set('currency_id', $quotationCurrencyId);
                                    $set('exchange_rate', static::resolveExchangeRate($quotationCurrencyId));
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
                                ->icon('heroicon-m-arrow-path')
                                ->tooltip('Generate SO Number')
                                ->action(function ($set, $get, $state) {
                                    $salesOrderService = app(SalesOrderService::class);
                                    $set('so_number', $salesOrderService->generateSoNumber());
                                }))
                            ->maxLength(255),
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
                                        $itemCurrencyId = $item->currency_id ?? $saleOrder->currency_id ?? static::resolveDefaultCurrencyId();
                                        array_push($items, [
                                            'product_id' => $item->product_id,
                                            'currency_id' => $itemCurrencyId,
                                            'unit_price' => static::formatCurrencyInputState((float) $item->unit_price, $itemCurrencyId),
                                            'quantity' => $item->quantity,
                                            'discount' => $item->discount,
                                            'tax' => $item->tax,
                                            'tipe_pajak' => $tipePajak,
                                            'subtotal' => static::formatMoneyState(HelperController::hitungSubtotal($item->quantity, (float) $item->unit_price, $item->discount, $item->tax, $tipePajak), $itemCurrencyId),
                                            'discount_nominal' => static::formatMoneyState($item->quantity * (float) $item->unit_price * ((float) $item->discount / 100), $itemCurrencyId),
                                            'tax_nominal' => static::formatMoneyState(HelperController::hitungTaxNominal($item->quantity, (float) $item->unit_price, $item->discount, $item->tax, $tipePajak), $itemCurrencyId),
                                            'notes' => $item->notes,
                                        ]);
                                    }
                                    $set('total_amount', static::formatMoneyState($saleOrder->total_amount ?? 0, static::resolveDefaultCurrencyId()));
                                    $set('customer_id', $saleOrder->customer_id);
                                    $set('cabang_id', $saleOrder->cabang_id);
                                    $set('currency_id', static::resolveDefaultCurrencyId());
                                    $set('exchange_rate', static::resolveExchangeRate(static::resolveDefaultCurrencyId()));
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
                            ->disabled(fn(Get $get) => static::isReferQuotationForm($get))
                            ->dehydrated(true)
                            ->extraAttributes(fn(Get $get) => static::lockedInputAttributes($get))
                            ->helperText(function ($state) {
                                $customer = Customer::find($state);
                                if (!$customer) return null;

                                $creditService = app(CreditValidationService::class);
                                $creditSummary = $creditService->getCreditSummary($customer);

                                $helper = [];

                                // Deposit info
                                if ($customer->deposit->remaining_amount) {
                                    $helper[] = "Saldo: Rp." . number_format($customer->deposit->remaining_amount, 2, ',', '.');
                                }

                                // Credit info for credit customers
                                if ($customer->tipe_pembayaran === 'Kredit') {
                                    $helper[] = "Kredit Limit: Rp." . number_format($creditSummary['credit_limit'], 2, ',', '.');
                                    $helper[] = "Terpakai: Rp." . number_format($creditSummary['current_usage'], 2, ',', '.') . " ({$creditSummary['usage_percentage']}%)";
                                    $helper[] = "Tersedia: Rp." . number_format($creditSummary['available_credit'], 2, ',', '.');

                                    if ($creditSummary['overdue_count'] > 0) {
                                        $helper[] = "⚠️ {$creditSummary['overdue_count']} tagihan jatuh tempo (Rp." . number_format($creditSummary['overdue_total'], 2, ',', '.') . ")";
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
                                            ->telRegex('/^[0-9+\s().-]*$/')
                                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? trim($state) : $state)
                                            ->validationMessages([
                                                'max' => 'Telepon terlalu panjang'
                                            ])
                                            ->placeholder('Contoh: (+62) 830 9787 333')
                                            ->rules([new InternationalPhoneNumber()])
                                            ->helperText('Contoh : (+62) 830 9787 333, +62 21 12345678, 0211234567')
                                            ->required()
                                            ->maxLength(50),
                                        TextInput::make('phone')
                                            ->label('Handphone')
                                            ->tel()
                                            ->telRegex('/^[0-9+\s().-]*$/')
                                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? trim($state) : $state)
                                            ->validationMessages([
                                                'required' => 'Nomor handphone tidak boleh kosong',
                                                'max' => 'Nomor handphone terlalu panjang'
                                            ])
                                            ->helperText('Contoh : (+62) 830 9787 333, +62 812 3456 7890, 081234567890')
                                            ->rules([new InternationalPhoneNumber()])
                                            ->required()
                                            ->maxLength(50),
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
                                            ->tel()
                                            ->telRegex('/^[0-9+\s().-]*$/')
                                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? trim($state) : $state)
                                            ->rules([new InternationalPhoneNumber()])
                                            ->helperText('Contoh : (+62) 830 9787 333, +62 21 1234567, 0213456789')
                                            ->maxLength(50)
                                            ->validationMessages([
                                                'required' => 'Fax customer tidak boleh kosong',
                                                'max' => 'Fax terlalu panjang',
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
                            ->disabled(fn(Get $get) => static::isReferQuotationForm($get))
                            ->dehydrated(true)
                            ->extraAttributes(fn(Get $get) => static::lockedInputAttributes($get))
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
                        DatePicker::make('order_date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal order wajib diisi'
                            ]),
                        DatePicker::make('delivery_date')
                            ->validationMessages([
                                'date' => 'Format tanggal pengiriman tidak valid'
                            ]),
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
                        TextInput::make('shipped_to')
                            ->label('Shipped To')
                            ->reactive()
                            ->nullable()
                            ->maxLength(255)
                            ->validationMessages([
                                'max' => 'Alamat pengiriman maksimal 255 karakter'
                            ]),
                        Hidden::make('currency_id')
                            ->default(static::resolveDefaultCurrencyId())
                            ->dehydrated(true),
                        Hidden::make('exchange_rate')
                            ->default(fn() => static::resolveExchangeRate(static::resolveDefaultCurrencyId()))
                            ->dehydrated(true),
                        \Filament\Forms\Components\TextInput::make('tempo_pembayaran')
                            ->label('Tempo Pembayaran (Hari)')
                            ->numeric()
                            ->readOnly(fn(Get $get) => static::isReferQuotationForm($get))
                            ->extraInputAttributes(fn(Get $get) => static::lockedInputAttributes($get))
                            ->nullable()
                            ->helperText('Diisi otomatis dari data customer (tempo kredit). Dapat diubah bila perlu.')
                            ->suffix('Hari'),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->required()
                            ->disabled()
                            ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                            ->reactive()
                            ->live()
                            ->default(0)
                            ->prefix(fn() => static::resolveCurrencySymbol(static::resolveDefaultCurrencyId()))
                            ->formatStateUsing(function ($state, Get $get) {
                                if ($state === null || $state === '') {
                                    return '';
                                }

                                $currencyId = static::resolveDefaultCurrencyId();

                                return static::formatCurrencyPreviewState($state, $currencyId);
                            })
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
                                $totalForCredit = CurrencyConversionResolver::convertToIdr(
                                    MoneyHelper::parseHighPrecision(static::parseCurrencyState($state)),
                                    static::resolveDefaultCurrencyId(),
                                    false
                                );
                                $validation = $creditService->canCustomerMakePurchase($customer, (float) $totalForCredit);

                                if (!$validation['can_purchase']) {
                                    return '⚠️ Peringatan: ' . implode(' | ', $validation['messages']);
                                }

                                if (!empty($validation['warnings'])) {
                                    return '⚠️ ' . implode(' | ', $validation['warnings']);
                                }

                                return null;
                            }),
                        Repeater::make('saleOrderItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->reactive()
                            ->columns(5)
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
                            ->addAction(function (ActionsAction $action) {
                                return $action
                                    ->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Add Items')
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
                            ->addable(fn(Get $get) => ! static::isReferQuotationForm($get))
                            ->deletable(fn(Get $get) => ! static::isReferQuotationForm($get))
                            ->reorderable(fn(Get $get) => ! static::isReferQuotationForm($get))
                            ->collapsible()
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
                            ->itemLabel(function (array $state): ?string {
                                $product = isset($state['product_id'])
                                    ? Product::withoutGlobalScope('product_cabang')->find($state['product_id'])
                                    : null;
                                $productLabel = $product
                                    ? "Product: ({$product->sku}) {$product->name}"
                                    : 'Product: -';
                                $qty = $state['quantity'] ?? 0;
                                $subtotal = $state['subtotal'] ?? 0;
                                $currencyId = static::resolveItemCurrencyId($state['currency_id'] ?? null);

                                return "{$productLabel} | Qty: {$qty} | Subtotal: " . static::formatCurrencyAmount($currencyId, $subtotal);
                            })
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->columnSpan(3)
                                    ->searchable(['sku', 'name'])
                                    ->reactive()
                                    ->disabled(fn(Get $get) => static::isReferQuotationForm($get))
                                    ->dehydrated(true)
                                    ->extraAttributes(fn(Get $get) => static::lockedInputAttributes($get))
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                        if ($product) {
                                            $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                            $unitPrice = static::normalizeCurrencyDisplayValue(
                                                CurrencyConversionResolver::convertFromIdr(MoneyHelper::parseHighPrecision($product->sell_price), $currencyId, false),
                                                $currencyId
                                            );

                                            $set('unit_price', static::formatCurrencyInputState($unitPrice, $currencyId));
                                            $set('currency_id', $currencyId);
                                            $set('unit', $product->uom?->abbreviation ?? '-');
                                            $preview = static::calculateCurrencyPreview((float) ($get('quantity') ?? 0), $unitPrice, (float) ($get('discount') ?? 0), (float) ($get('tax') ?? 0), $get('tipe_pajak') ?? null, $currencyId);
                                            $set('total', static::formatCurrencyPreviewState($preview['total'], $currencyId));
                                            $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                            $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                            $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
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
                                    ->columnSpan(1)
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default('-')
                                    ->extraInputAttributes(array_merge(['title' => 'Satuan produk (otomatis)'], static::readOnlyGrayInputAttributes()))
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record?->product) {
                                            $component->state($record->product->uom?->abbreviation ?? '-');
                                        }
                                    }),
                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->reactive()
                                    ->readOnly(fn(Get $get) => static::isReferQuotationForm($get))
                                    ->extraInputAttributes(fn(Get $get) => static::lockedInputAttributes($get))
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
                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                        $qty = (float)($state ?? 0);
                                        $price = (float)static::parseCurrencyState($get('unit_price') ?? 0);
                                        $preview = static::calculateCurrencyPreview($qty, $price, (float) ($get('discount') ?? 0), (float) ($get('tax') ?? 0), $get('tipe_pajak') ?? null, $currencyId);
                                        $set('total', static::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
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
                                Select::make('currency_id')
                                    ->label('Mata Uang')
                                    ->columnSpan(1)
                                    ->options(static::resolveCurrencyOptions())
                                    ->default(static::resolveDefaultCurrencyId())
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->disabled(fn(Get $get) => static::isReferQuotationForm($get))
                                    ->dehydrated(true)
                                    ->extraAttributes(fn(Get $get) => static::lockedInputAttributes($get))
                                    ->required()
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        $component->state($state ?: ($record?->currency_id ?? $record?->saleOrder?->currency_id ?? static::resolveDefaultCurrencyId()));
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state, $old) {
                                        $newCurrencyId = static::resolveItemCurrencyId($state);
                                        $oldCurrencyId = static::resolveItemCurrencyId($old);

                                        if ($newCurrencyId === $oldCurrencyId) {
                                            return;
                                        }

                                        $convertedUnitPrice = static::normalizeCurrencyDisplayValue(
                                            static::convertCurrencyAmount(static::parseCurrencyState($get('unit_price') ?? 0), $oldCurrencyId, $newCurrencyId),
                                            $newCurrencyId
                                        );
                                        $preview = static::calculateCurrencyPreview(
                                            (float) ($get('quantity') ?? 0),
                                            $convertedUnitPrice,
                                            (float) ($get('discount') ?? 0),
                                            (float) ($get('tax') ?? 0),
                                            $get('tipe_pajak') ?? null,
                                            $newCurrencyId
                                        );

                                        $set('unit_price', static::formatCurrencyInputState($convertedUnitPrice, $newCurrencyId));
                                        $set('total', static::formatCurrencyPreviewState($preview['total'], $newCurrencyId));
                                        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $newCurrencyId));
                                        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $newCurrencyId));
                                        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $newCurrencyId));
                                    })
                                    ->validationMessages([
                                        'required' => 'Currency item wajib dipilih',
                                    ]),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->columnSpan(1)
                                    ->live(debounce: 500)
                                    ->readOnly(fn(Get $get) => static::isReferQuotationForm($get))
                                    ->extraInputAttributes(fn(Get $get) => static::lockedInputAttributes($get))
                                    ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 10)
        JS))
                                    ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveItemCurrencyId($get('currency_id'))))
                                    ->formatStateUsing(function ($state, Get $get) {
                                        if ($state === null || $state === '') {
                                            return '';
                                        }

                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));

                                        return static::formatCurrencyInputState($state, $currencyId);
                                    })
                                    ->dehydrateStateUsing(fn($state) => static::parseCurrencyState($state ?? 0))
                                    ->validationMessages([
                                        'required' => 'Unit Price harus diisi',
                                        'numeric' => 'Unit Price tidak valid !'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                        $qty = (float)($get('quantity') ?? 0);
                                        $price = (float)static::parseCurrencyState($get('unit_price') ?? 0);
                                        $preview = static::calculateCurrencyPreview($qty, $price, (float) ($get('discount') ?? 0), (float) ($get('tax') ?? 0), $get('tipe_pajak') ?? null, $currencyId);
                                        $set('total', static::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                    }),
                                TextInput::make('total')
                                    ->label('Total (Harga x Qty)')
                                    ->columnSpan(1)
                                    ->live()
                                    ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveItemCurrencyId($get('currency_id'))))
                                    ->readOnly()
                                    ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $currencyId = $record->currency_id ?? $record->saleOrder?->currency_id ?? static::resolveDefaultCurrencyId();
                                            $total = (float)$record->quantity * (float)$record->unit_price;
                                            $component->state(static::formatCurrencyPreviewState($total, $currencyId));
                                        }
                                    }),
                                TextInput::make('discount')
                                    ->label('Discount %')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->readOnly(fn(Get $get) => static::isReferQuotationForm($get))
                                    ->extraInputAttributes(fn(Get $get) => static::lockedInputAttributes($get))
                                    ->validationMessages([
                                        'numeric' => 'Discount harus berupa angka',
                                        'min' => 'Discount minimal 0%',
                                        'max' => 'Discount maksimal 100%'
                                    ])
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                        $preview = static::calculateCurrencyPreview((float) ($get('quantity') ?? 0), static::parseCurrencyState($get('unit_price') ?? 0), (float) ($state ?? 0), (float) ($get('tax') ?? 0), $get('tipe_pajak') ?? null, $currencyId);
                                        $set('total', static::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                    })
                                    ->suffix('%'),
                                TextInput::make('discount_nominal')
                                    ->label('Discount (Nominal)')
                                    ->columnSpan(1)
                                    ->live()
                                    ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveItemCurrencyId($get('currency_id'))))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $currencyId = $record->currency_id ?? $record->saleOrder?->currency_id ?? static::resolveDefaultCurrencyId();
                                            $preview = static::calculateCurrencyPreview((float) $record->quantity, (float) $record->unit_price, (float) $record->discount, (float) $record->tax, $record->tipe_pajak, $currencyId);
                                            $component->state(static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        }
                                    }),
                                \Filament\Forms\Components\Select::make('tipe_pajak')
                                    ->label('Tipe Pajak')
                                    ->columnSpan(1)
                                    ->options(static::taxTypeOptions())
                                    ->default('eklusif')
                                    ->reactive()
                                    ->disabled(fn(Get $get) => static::isReferQuotationForm($get))
                                    ->dehydrated(true)
                                    ->extraAttributes(fn(Get $get) => static::lockedInputAttributes($get))
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

                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                        $preview = static::calculateCurrencyPreview((float) ($get('quantity') ?? 0), static::parseCurrencyState($get('unit_price') ?? 0), (float) ($get('discount') ?? 0), (float) ($get('tax') ?? 0), $normalizedState, $currencyId);
                                        $set('total', static::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                    }),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->reactive()
                                    ->disabled()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(static::readOnlyGrayInputAttributes())
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
                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                        $preview = static::calculateCurrencyPreview((float) ($get('quantity') ?? 0), static::parseCurrencyState($get('unit_price') ?? 0), (float) ($get('discount') ?? 0), (float) ($state ?? 0), $taxType, $currencyId);
                                        $set('total', static::formatCurrencyPreviewState($preview['total'], $currencyId));
                                        $set('discount_nominal', static::formatCurrencyPreviewState($preview['discount_nominal'], $currencyId));
                                        $set('subtotal', static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                        $set('tax_nominal', static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                    })
                                    ->default(fn(callable $get) => static::normalizeTaxTypeValue($get('tipe_pajak') ?? null) === 'none' ? 0 : \App\Models\TaxSetting::activeRate('PPN'))
                                    ->suffix('%'),
                                TextInput::make('tax_nominal')
                                    ->label('Nominal Pajak')
                                    ->columnSpan(1)
                                    ->live()
                                    ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveItemCurrencyId($get('currency_id'))))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $currencyId = $record->currency_id ?? $record->saleOrder?->currency_id ?? static::resolveDefaultCurrencyId();
                                            $preview = static::calculateCurrencyPreview((float) $record->quantity, (float) $record->unit_price, (float) $record->discount, (float) $record->tax, $record->tipe_pajak, $currencyId);
                                            $component->state(static::formatCurrencyPreviewState($preview['tax_nominal'], $currencyId));
                                        }
                                    }),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->columnSpan(5)
                                    ->reactive()
                                    ->readOnly()
                                    ->default(0)
                                    ->extraInputAttributes(static::readOnlyGrayInputAttributes())
                                    ->prefix(fn(Get $get) => static::resolveCurrencySymbol(static::resolveItemCurrencyId($get('currency_id'))))
                                    ->formatStateUsing(function ($state, Get $get) {
                                        if ($state === null || $state === '') {
                                            return '';
                                        }

                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));

                                        return static::formatCurrencyPreviewState($state, $currencyId);
                                    })
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $currencyId = $record->currency_id ?? $record->saleOrder?->currency_id ?? static::resolveDefaultCurrencyId();
                                            $preview = static::calculateCurrencyPreview((float) $record->quantity, (float) $record->unit_price, (float) $record->discount, (float) $record->tax, $record->tipe_pajak, $currencyId);
                                            $component->state(static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));
                                        }
                                    })
                                    ->afterStateUpdated(function ($component, $state, $livewire, $get) {
                                        $qty   = $get('quantity') ?? 0;
                                        $price = static::parseCurrencyState($get('unit_price') ?? 0);
                                        $disc  = $get('discount') ?? 0;
                                        $tax   = $get('tax') ?? 0;
                                        $type  = static::normalizeTaxTypeValue($get('tipe_pajak'));

                                        $currencyId = static::resolveItemCurrencyId($get('currency_id'));
                                        $preview = static::calculateCurrencyPreview((float) $qty, (float) $price, (float) $disc, (float) $tax, $type, $currencyId);
                                        $component->state(static::formatCurrencyPreviewState($preview['subtotal'], $currencyId));

                                        // hitung ulang total order
                                        $total = 0;
                                        foreach ($livewire->data['saleOrderItem'] ?? [] as $item) {
                                            $itemCurrencyId = static::resolveItemCurrencyId($item['currency_id'] ?? null);
                                            $preview = static::calculateCurrencyPreview(
                                                $item['quantity'] ?? 0,
                                                static::parseCurrencyState($item['unit_price'] ?? 0),
                                                $item['discount'] ?? 0,
                                                $item['tax'] ?? 0,
                                                static::normalizeTaxTypeValue($item['tipe_pajak'] ?? null),
                                                $itemCurrencyId
                                            );
                                            $total += CurrencyConversionResolver::convertToIdr(MoneyHelper::parseHighPrecision($preview['subtotal']), $itemCurrencyId, false);
                                        }
                                        $livewire->data['total_amount'] = static::formatCurrencyPreviewState($total, static::resolveDefaultCurrencyId());

                                        // Check credit validation
                                        $customerId = $livewire->data['customer_id'] ?? null;
                                        if ($customerId && $total > 0) {
                                            $customer = Customer::find($customerId);
                                            if ($customer) {
                                                $creditService = app(CreditValidationService::class);
                                                $totalForCredit = CurrencyConversionResolver::convertToIdr(
                                                    MoneyHelper::parseHighPrecision($total),
                                                    static::resolveDefaultCurrencyId(),
                                                    false
                                                );
                                                $validation = $creditService->canCustomerMakePurchase($customer, (float) $totalForCredit);

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
                                $totalAmount = is_array($state) ? static::calculateSaleOrderTotalIdr($state) : 0.0;
                                $set('total_amount', static::formatMoneyState($totalAmount, static::resolveDefaultCurrencyId()));
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
                    ->formatStateUsing(function ($state) {
                        return static::formatMoneyState($state, static::resolveDefaultCurrencyId());
                    })
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
                $classes = [static::statusRowClass($record->status)];

                if ($record->status !== 'completed' && $record->hasInsufficientStock()) {
                    $classes[] = 'insufficient-stock-row';
                }

                return trim(implode(' ', array_filter($classes)));
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
                        ->label('Preview / Download PDF')
                        ->color('info')
                        ->icon('heroicon-o-document-arrow-down')
                        ->visible(fn ($record) => in_array($record->status, ['approved', 'completed', 'confirmed', 'received']))
                        ->url(fn ($record) => route('pdf-stream', ['type' => 'sale-order', 'id' => $record->id]))
                        ->openUrlInNewTab(),

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
                '<style>.fi-ta-header:has(.dt-table-description-full-width){align-items:stretch}.fi-ta-header>.grid:has(.dt-table-description-full-width){width:100%;max-width:none;flex:1 1 100%;}.dt-table-description-full-width{width:100%;min-width:100%;max-width:none;box-sizing:border-box;}</style>' .
                    '<div class="dt-table-description-full-width space-y-4 mb-6 w-full min-w-full max-w-none" style="width: 100%; min-width: 100%; max-width: none; box-sizing: border-box;">' .
                    '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full max-w-none" style="width: 100%; max-width: none; box-sizing: border-box; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px; background-color: #ffffff; transition: all 0.2s;">' .
                    '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; color: #374151;">' .
                    '<span class="flex items-center gap-2" style="display: flex; align-items: center; gap: 8px;">' .
                    '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px; color: #3b82f6;">' .
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                    '</svg>' .
                    'Panduan Sales Order' .
                    '</span>' .
                    '<span class="transition group-open:rotate-180">' .
                    '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                    '</span>' .
                    '</summary>' .
                    '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top: 12px; font-size: 14px; color: #4b5563; padding-left: 28px; border-left: 2px solid rgba(59, 130, 246, 0.3); display: flex; flex-direction: column; gap: 8px;">' .
                    '<p><strong>Apa ini:</strong> Sale Order adalah pesanan penjualan yang dibuat dari Quotation atau langsung, memerlukan approval sebelum diproses.</p>' .
                    '<p><strong>Status Flow:</strong> Draft → Request Approve → Approved → Confirmed → Received → Completed. Atau bisa Request Close → Closed.</p>' .
                    '<p><strong>Tipe Pengiriman:</strong> <em>Ambil Sendiri</em> (customer datang ke gudang), <em>Kirim Langsung</em> (barang dikirim ke customer).</p>' .
                    '<p><strong>Validasi:</strong> <em>Status Stok</em> menunjukkan apakah stok cukup. <em>Credit Limit</em> customer dicek saat approve.</p>' .
                    '<p><strong>Stock Management:</strong> <em>Ambil Sendiri</em>: Stock berkurang saat <em>Complete</em> (manual). <em>Kirim Langsung</em>: Perlu Delivery Order completed terlebih dahulu.</p>' .
                    '<p><strong>Actions:</strong> <em>Request Approve</em> (draft), <em>Approve/Reject</em> (request_approve), <em>Request Close</em> (approved+), <em>Close</em> (request_close), <em>Complete</em> (approved+), <em>PDF/Kwitansi</em> (approved+), <em>Create PO</em> (untuk drop ship), <em>Sync Total</em> (update amount).</p>' .
                    '<p><strong>Permissions:</strong> <em>request sales order</em> untuk request actions, <em>response sales order</em> untuk approve/reject/close, <em>update sales order</em> untuk complete.</p>' .
                    '<p><strong>Integration:</strong> Terintegrasi dengan inventory, accounting, dan bisa generate Purchase Order untuk drop shipping.</p>' .
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
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #4b5563;">Putih (Draft)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">SO masih draft</span></div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (Approved)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">SO sudah disetujui</span></div>' .
                    '</div>' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 243, 199, 0.4); border: 1px solid rgba(253, 230, 138, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #eab308; box-shadow: 0 1px 3px rgba(234, 179, 8, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight"><span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #854d0e;">Kuning (Partially Received)</span><span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">SO diterima sebagian</span></div>' .
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
                            ->getStateUsing(fn($record) => static::formatCurrencyAmount(static::resolveDefaultCurrencyId(), $record?->total_amount)),
                        \Filament\Infolists\Components\TextEntry::make('tipe_pengiriman')
                            ->label('Tipe Pengiriman')
                            ->placeholder('-'),
                        \Filament\Infolists\Components\TextEntry::make('shipped_to')
                            ->label('Shipped To')
                            ->placeholder('-'),
                    ]),
                \Filament\Infolists\Components\Section::make('Ringkasan Sales Order')
                    ->columns(5)
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('summary_item_count')
                            ->label('Jumlah Item')
                            ->getStateUsing(fn($record) => $record->saleOrderItem->count()),
                        \Filament\Infolists\Components\TextEntry::make('summary_total_qty')
                            ->label('Total Qty')
                            ->getStateUsing(fn($record) => number_format((float) $record->saleOrderItem->sum('quantity'), 0, ',', '.')),
                        \Filament\Infolists\Components\TextEntry::make('summary_delivered_qty')
                            ->label('Total Qty Terkirim')
                            ->getStateUsing(fn($record) => number_format((float) $record->saleOrderItem->sum('delivered_quantity'), 0, ',', '.')),
                        \Filament\Infolists\Components\TextEntry::make('summary_remaining_qty')
                            ->label('Sisa Qty Belum Dikirim')
                            ->getStateUsing(function ($record) {
                                $remaining = $record->saleOrderItem->sum(function ($item) {
                                    return (float) ($item->remaining_quantity ?? 0);
                                });

                                return number_format($remaining, 0, ',', '.');
                            }),
                        \Filament\Infolists\Components\TextEntry::make('summary_total_amount')
                            ->label('Total Amount')
                            ->getStateUsing(fn($record) => static::formatCurrencyAmount($record?->currency_id ?? static::resolveDefaultCurrencyId(), $record?->total_amount)),
                    ]),
                \Filament\Infolists\Components\Section::make('Detail Item Sales Order')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('saleOrderItem')
                            ->label('')
                            ->columnSpanFull()
                            ->schema([
                                \Filament\Infolists\Components\Section::make(function ($record) {
                                    $productName = $record->product
                                        ? trim('(' . ($record->product->sku ?? '-') . ') ' . ($record->product->name ?? '-'))
                                        : '-';
                                    $qty = number_format((float) ($record->quantity ?? 0), 0, ',', '.');
                                    $currencyId = $record?->currency_id ?? $record?->saleOrder?->currency_id;
                                    $preview = static::calculateCurrencyPreview(
                                        (float) ($record->quantity ?? 0),
                                        (float) ($record->unit_price ?? 0),
                                        (float) ($record->discount ?? 0),
                                        (float) ($record->tax ?? 0),
                                        $record->tipe_pajak ?? null,
                                        $currencyId
                                    );

                                    return 'Product: ' . $productName . ' | Qty: ' . $qty . ' | Subtotal: ' . static::formatCurrencyAmount($currencyId, $preview['subtotal']);
                                })
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        \Filament\Infolists\Components\Grid::make(2)
                                            ->schema([
                                                \Filament\Infolists\Components\Group::make([
                                                    static::saleOrderDetailColumnEntry(
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
                                                            ['Qty', fn($record) => number_format((float) ($record->quantity ?? 0), 0, ',', '.')],
                                                            ['Qty Delivered', fn($record) => number_format((float) ($record->delivered_quantity ?? 0), 0, ',', '.')],
                                                            ['Sisa Qty Belum Dikirim', fn($record) => number_format((float) ($record->remaining_quantity ?? 0), 0, ',', '.')],
                                                            ['Mode Gudang', function ($record) {
                                                                $allocCount = $record->warehouseAllocations->count();

                                                                if ($allocCount > 0) {
                                                                    return "Multi-Gudang ({$allocCount} gudang)";
                                                                }

                                                                return $record->warehouse?->name
                                                                    ? 'Single: ' . $record->warehouse->name
                                                                    : 'Belum diset';
                                                            }],
                                                            ['Alokasi Order', function ($record) {
                                                                $allocations = $record->warehouseAllocations;

                                                                if ($allocations->isEmpty()) {
                                                                    return $record->warehouse?->name
                                                                        ? ($record->warehouse->name . ' - qty: ' . number_format((float) ($record->quantity ?? 0), 0, ',', '.'))
                                                                        : '-';
                                                                }

                                                                return $allocations
                                                                    ->map(function ($alloc) {
                                                                        $warehouse = $alloc->warehouse?->name ?? "Gudang #{$alloc->warehouse_id}";

                                                                        return "{$warehouse}: " . number_format((float) $alloc->quantity, 0, ',', '.');
                                                                    })
                                                                    ->implode(' | ');
                                                            }],
                                                            ['Stok Bebas', function ($record) {
                                                                $stocks = InventoryStock::where('product_id', $record->product_id)
                                                                    ->with('warehouse')
                                                                    ->get();
                                                                $total = (float) $stocks->sum('free_qty');

                                                                if ($total <= 0) {
                                                                    return 'Habis';
                                                                }

                                                                $perWarehouse = $stocks
                                                                    ->groupBy('warehouse_id')
                                                                    ->map(function ($stocks) {
                                                                        $available = (float) $stocks->sum('free_qty');

                                                                        if ($available <= 0) {
                                                                            return null;
                                                                        }

                                                                        $warehouse = $stocks->first()->warehouse?->name ?? 'Wh#' . $stocks->first()->warehouse_id;

                                                                        return "{$warehouse}: " . number_format($available, 0, ',', '.');
                                                                    })
                                                                    ->filter()
                                                                    ->values()
                                                                    ->implode(' | ');

                                                                return number_format($total, 0, ',', '.') . ($perWarehouse ? " ({$perWarehouse})" : '');
                                                            }],
                                                        ]
                                                    ),
                                                ])
                                                    ->columnSpan(1)
                                                    ->columns(1),
                                                \Filament\Infolists\Components\Group::make([
                                                    static::saleOrderDetailColumnEntry(
                                                        'price_column',
                                                        'Price',
                                                        [
                                                            ['Mata Uang', fn($record) => $record->currency?->code ?? $record->saleOrder?->currency?->code ?? '-'],
                                                            ['Unit Price', fn($record) => static::formatCurrencyAmount($record?->currency_id ?? $record?->saleOrder?->currency_id, (float) ($record->unit_price ?? 0))],
                                                            ['Total (Harga x Qty)', function ($record) {
                                                                $total = (float) ($record->quantity ?? 0) * (float) ($record->unit_price ?? 0);

                                                                return static::formatCurrencyAmount($record?->currency_id ?? $record?->saleOrder?->currency_id, $total);
                                                            }],
                                                            ['Discount', fn($record) => number_format((float) ($record->discount ?? 0), 0, ',', '.') . '%'],
                                                            ['Discount (Nominal)', function ($record) {
                                                                $currencyId = $record?->currency_id ?? $record?->saleOrder?->currency_id;
                                                                $preview = static::calculateCurrencyPreview(
                                                                    (float) ($record->quantity ?? 0),
                                                                    (float) ($record->unit_price ?? 0),
                                                                    (float) ($record->discount ?? 0),
                                                                    (float) ($record->tax ?? 0),
                                                                    $record->tipe_pajak ?? null,
                                                                    $currencyId
                                                                );

                                                                return static::formatCurrencyAmount($currencyId, $preview['discount_nominal']);
                                                            }],
                                                            ['Tipe Pajak', fn($record) => static::normalizeTaxTypeValue($record->tipe_pajak ?? null)],
                                                            ['Tax (%)', fn($record) => number_format((float) ($record->tax ?? 0), 0, ',', '.') . '%'],
                                                            ['Nominal Pajak', function ($record) {
                                                                $currencyId = $record?->currency_id ?? $record?->saleOrder?->currency_id;
                                                                $preview = static::calculateCurrencyPreview(
                                                                    (float) ($record->quantity ?? 0),
                                                                    (float) ($record->unit_price ?? 0),
                                                                    (float) ($record->discount ?? 0),
                                                                    (float) ($record->tax ?? 0),
                                                                    $record->tipe_pajak ?? null,
                                                                    $currencyId
                                                                );

                                                                return static::formatCurrencyAmount($currencyId, $preview['tax_nominal']);
                                                            }],
                                                            ['Subtotal', function ($record) {
                                                                $currencyId = $record?->currency_id ?? $record?->saleOrder?->currency_id;
                                                                $preview = static::calculateCurrencyPreview(
                                                                    (float) ($record->quantity ?? 0),
                                                                    (float) ($record->unit_price ?? 0),
                                                                    (float) ($record->discount ?? 0),
                                                                    (float) ($record->tax ?? 0),
                                                                    $record->tipe_pajak ?? null,
                                                                    $currencyId
                                                                );

                                                                return static::formatCurrencyAmount($currencyId, $preview['subtotal']);
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('created_at', 'asc')
            ->with([
                'customer',
                'currency',
                'saleOrderItem.currency',
                'saleOrderItem.product.uom',
                'saleOrderItem.warehouse',
                'saleOrderItem.warehouseAllocations.warehouse',
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
