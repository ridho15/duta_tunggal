<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Helpers\MoneyHelper;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Cabang;
use App\Services\InvoiceService;
use App\Services\PurchaseInvoiceAccountingService;
use App\Support\CurrencyConversionResolver;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\ViewEntry;
use Filament\Tables\Enums\ActionsPosition;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoice Pembelian';
    protected static ?string $modelLabel = 'Invoice Pembelian';
    protected static ?string $pluralModelLabel = 'Invoice Pembelian';
    protected static ?string $navigationGroup = 'Keuangan Pembelian';
    protected static ?int $navigationSort = 9;

    protected static bool $shouldRegisterNavigation = false;

    protected static function formatMoneyState(mixed $value): string
    {
        return number_format((float) MoneyHelper::safeParse($value), 2, ',', '.');
    }

    public static function sourceCurrencyContextFromPurchaseOrders(mixed $purchaseOrderIds): array
    {
        $firstPoId = collect((array) $purchaseOrderIds)
            ->map(fn ($value, $key) => is_bool($value) ? $key : $value)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->first();

        $currencyId = null;
        $exchangeRate = 1.0;

        if ($firstPoId) {
            $purchaseOrder = PurchaseOrder::withoutGlobalScopes()
                ->with('purchaseOrderCurrency.currency')
                ->find($firstPoId);

            $poCurrency = $purchaseOrder?->purchaseOrderCurrency->first();
            $currencyId = is_numeric($poCurrency?->currency_id ?? null)
                ? (int) $poCurrency->currency_id
                : (is_numeric($purchaseOrder?->currency_id ?? null) ? (int) $purchaseOrder->currency_id : null);

            $exchangeRate = (float) ($poCurrency?->nominal ?? CurrencyConversionResolver::resolveRate($currencyId));
        }

        $currency = $currencyId ? CurrencyConversionResolver::resolveCurrency($currencyId) : null;
        $code = strtoupper((string) ($currency?->code ?? 'IDR'));
        $symbol = $currency?->symbol ?: ($code === 'IDR' ? 'Rp' : $code);

        if ($exchangeRate <= 0) {
            $exchangeRate = CurrencyConversionResolver::resolveRate($currencyId);
        }

        return [
            'currency_id' => $currencyId,
            'currency_code' => $code,
            'currency_symbol' => $symbol,
            'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.0,
            'is_foreign' => $code !== 'IDR' && $exchangeRate > 1.0,
        ];
    }

    public static function sourceCurrencyContextFromInvoice(?Invoice $invoice): array
    {
        if (! $invoice) {
            return static::sourceCurrencyContextFromPurchaseOrders([]);
        }

        $currencyId = is_numeric($invoice->currency_id ?? null) ? (int) $invoice->currency_id : $invoice->display_currency_id;
        $exchangeRate = (float) ($invoice->exchange_rate ?? CurrencyConversionResolver::resolveRate($currencyId));
        $currency = $currencyId ? CurrencyConversionResolver::resolveCurrency($currencyId) : null;
        $code = strtoupper((string) ($currency?->code ?? 'IDR'));
        $symbol = $currency?->symbol ?: ($code === 'IDR' ? 'Rp' : $code);

        return [
            'currency_id' => $currencyId,
            'currency_code' => $code,
            'currency_symbol' => $symbol,
            'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.0,
            'is_foreign' => $code !== 'IDR' && $exchangeRate > 1.0,
        ];
    }

    public static function formatSourceCurrencyAmount(mixed $amount, array $context): string
    {
        $numeric = (float) MoneyHelper::safeParse($amount ?? 0);
        $code = strtoupper((string) ($context['currency_code'] ?? 'IDR'));

        if ($code === 'IDR') {
            return MoneyHelper::rupiah($numeric);
        }

        return $code . ' ' . number_format($numeric, 2, '.', ',');
    }

    public static function formatSourceCurrencyPair(mixed $amount, array $context): string
    {
        $numeric = (float) MoneyHelper::safeParse($amount ?? 0);
        $source = static::formatSourceCurrencyAmount($numeric, $context);

        if (! ($context['is_foreign'] ?? false)) {
            return $source;
        }

        $idr = MoneyHelper::rupiah(round($numeric * (float) ($context['exchange_rate'] ?? 1), 2));

        return $idr . ' / ' . $source;
    }

    /**
     * Compact transaction-currency display used by the purchase invoice form.
     * IDR is rendered once, while foreign currency keeps the source amount first.
     */
    public static function formatTransactionCurrencyAmount(mixed $amount, array $context): string
    {
        $numeric = (float) MoneyHelper::safeParse($amount ?? 0);
        $source = static::formatSourceCurrencyAmount($numeric, $context);

        if (! ($context['is_foreign'] ?? false)) {
            return $source;
        }

        $idr = MoneyHelper::rupiah(round($numeric * (float) ($context['exchange_rate'] ?? 1), 2));

        return $source . ' (≈ ' . $idr . ')';
    }

    public static function formatInvoiceCurrencyPair(?Invoice $invoice, mixed $amount): string
    {
        return static::formatSourceCurrencyPair($amount, static::sourceCurrencyContextFromInvoice($invoice));
    }

    public static function invoiceAmountToIdr(?Invoice $invoice, mixed $amount): float
    {
        $context = static::sourceCurrencyContextFromInvoice($invoice);
        $numeric = (float) MoneyHelper::safeParse($amount ?? 0);

        return round($numeric * (float) ($context['exchange_rate'] ?? 1), 2);
    }

    protected static function transactionCurrencyContextFromPurchaseOrders(mixed $purchaseOrderIds): array
    {
        $ids = collect((array) $purchaseOrderIds)
            ->map(fn ($value, $key) => is_bool($value) ? ($value ? $key : null) : $value)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return static::sourceCurrencyContextFromPurchaseOrders([]);
        }

        $resolved = app(PurchaseInvoiceAccountingService::class)
            ->currencyContextFromPurchaseOrderIds($ids->all());
        $currencyId = is_numeric($resolved['currency_id'] ?? null) ? (int) $resolved['currency_id'] : null;
        $exchangeRate = (float) ($resolved['exchange_rate'] ?? 1);
        $currency = $currencyId ? CurrencyConversionResolver::resolveCurrency($currencyId) : null;
        $code = strtoupper((string) ($currency?->code ?? 'IDR'));

        return [
            'currency_id' => $currencyId,
            'currency_code' => $code,
            'currency_symbol' => $currency?->symbol ?: ($code === 'IDR' ? 'Rp' : $code),
            'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.0,
            'is_foreign' => $code !== 'IDR',
        ];
    }

    protected static function formatTransactionState(mixed $get, mixed $amount): string
    {
        return static::formatTransactionForCurrencyState(
            $amount,
            $get('currency_id'),
            $get('exchange_rate')
        );
    }

    protected static function formatTransactionForCurrencyState(
        mixed $amount,
        mixed $currencyId,
        mixed $exchangeRate
    ): string {
        $currencyId = is_numeric($currencyId) ? (int) $currencyId : null;
        $currency = $currencyId ? CurrencyConversionResolver::resolveCurrency($currencyId) : null;
        $code = strtoupper((string) ($currency?->code ?? 'IDR'));
        $rate = (float) MoneyHelper::safeParse($exchangeRate ?? 1);
        $context = [
            'currency_id' => $currencyId,
            'currency_code' => $code,
            'currency_symbol' => $currency?->symbol ?: ($code === 'IDR' ? 'Rp' : $code),
            'exchange_rate' => $rate > 0 ? $rate : 1.0,
            'is_foreign' => $code !== 'IDR',
        ];

        return static::formatTransactionCurrencyAmount($amount, $context);
    }

    protected static function createCurrencySummaryText(mixed $purchaseOrderIds): string
    {
        if (empty(array_filter((array) $purchaseOrderIds))) {
            return 'Pilih Purchase Order untuk melihat mata uang dan rate invoice.';
        }

        try {
            $context = static::transactionCurrencyContextFromPurchaseOrders($purchaseOrderIds);
        } catch (ValidationException) {
            return 'Purchase Order memiliki mata uang atau rate berbeda. Pisahkan ke invoice yang berbeda.';
        }

        if (! ($context['is_foreign'] ?? false)) {
            return 'Mata uang transaksi: IDR';
        }

        return 'Mata uang transaksi: ' . $context['currency_code']
            . ' | 1 ' . $context['currency_code'] . ' = ' . MoneyHelper::rupiah($context['exchange_rate']);
    }

    protected static function purchaseOrderSelectionIsImport(mixed $purchaseOrderIds): bool
    {
        $normalizedPurchaseOrderIds = array_values(array_filter(array_map('intval', (array) $purchaseOrderIds)));

        if (empty($normalizedPurchaseOrderIds)) {
            return false;
        }

        return PurchaseOrder::whereIn('id', $normalizedPurchaseOrderIds)
            ->where('is_import', true)
            ->exists();
    }

    protected static function importChargeTotalFromState(mixed $get): float
    {
        return (float) MoneyHelper::safeParse($get('pph22_amount') ?? 0)
            + (float) MoneyHelper::safeParse($get('bea_masuk_amount') ?? 0);
    }

    protected static function recalculatePurchaseInvoiceTotalState(mixed $set, mixed $get, array $overrides = []): void
    {
        $subtotal = (float) MoneyHelper::safeParse($overrides['subtotal'] ?? $get('subtotal') ?? 0);
        $receiptBiayaItems = $overrides['receiptBiayaItems'] ?? $get('receiptBiayaItems') ?? [];
        $otherFees = $overrides['otherFees'] ?? $get('other_fees') ?? [];

        $manualOtherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) MoneyHelper::safeParse($fee['amount'] ?? 0));
        $receiptBiayaTotal = (float) collect($receiptBiayaItems)->sum(fn ($row) => (float) MoneyHelper::safeParse($row['total'] ?? 0));
        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;
        $importChargeTotal = self::importChargeTotalFromState($get);

        $ppnRate = (float) MoneyHelper::safeParse($overrides['ppnRate'] ?? $get('ppn_rate') ?? 0);
        $taxAmount = array_key_exists('taxAmount', $overrides)
            ? (float) MoneyHelper::safeParse($overrides['taxAmount'])
            : $subtotal * $ppnRate / 100;
        $finalTotal = $subtotal + $totalOtherFee + $importChargeTotal + $taxAmount;

        $set('other_fee', $totalOtherFee);
        $set('total', round($finalTotal, 2));
        $set('tax', $ppnRate);
        $set('ppn_amount', round($taxAmount, 2));

        if (($overrides['syncPpnRate'] ?? false) === true) {
            $set('ppn_rate', $ppnRate);
        }
    }

    protected static function readonlyInputAttributes(): array
    {
        return [
            'class' => 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400',
            'style' => 'background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;',
        ];
    }

    protected static function clearDerivedPurchaseInvoiceState(mixed $set): void
    {
        $set('selected_purchase_receipts', []);
        $set('invoiceItem', []);
        $set('receiptBiayaItems', []);
        $set('from_model_type', PurchaseOrder::class);
        $set('from_model_id', null);
        $set('purchase_order_ids', []);
        $set('purchase_receipts', []);
        $set('currency_id', null);
        $set('exchange_rate', 1);
        $set('supplier_name', null);
        $set('supplier_phone', null);
        $set('other_fees', []);
        $set('other_fee', []);
        $set('pph22_amount', static::formatMoneyState(0));
        $set('bea_masuk_amount', static::formatMoneyState(0));
        $set('subtotal', 0);
        $set('dpp', 0);
        $set('tax', 0);
        $set('ppn_amount', 0);
        $set('total', 0);
    }

    public static function canManuallySetStatus(): bool
    {
        $user = Auth::user();

        return $user?->hasRole(['Super Admin', 'Owner']) === true;
    }

    public static function getSupplierOptions(): array
    {
        $recentSupplierIds = PurchaseReceipt::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_receipts.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereNull('purchase_orders.deleted_at')
            ->whereNull('suppliers.deleted_at')
            ->select('purchase_orders.supplier_id')
            ->selectRaw('MAX(purchase_receipts.receipt_date) as latest_receipt_date')
            ->selectRaw('MAX(purchase_receipts.created_at) as latest_receipt_created_at')
            ->groupBy('purchase_orders.supplier_id')
            ->orderByDesc('latest_receipt_date')
            ->orderByDesc('latest_receipt_created_at')
            ->orderBy('purchase_orders.supplier_id')
            ->limit(5)
            ->get()
            ->pluck('supplier_id')
            ->map(fn ($supplierId) => (int) $supplierId);

        $recentSuppliersById = Supplier::query()
            ->whereIn('id', $recentSupplierIds)
            ->get()
            ->keyBy('id');

        $recentSuppliers = $recentSupplierIds
            ->map(fn (int $supplierId) => $recentSuppliersById->get($supplierId))
            ->filter()
            ->values();

        $alphabeticalSuppliers = Supplier::query()
            ->whereNotIn('id', $recentSupplierIds)
            ->orderBy('perusahaan')
            ->orderBy('id')
            ->limit(50 - $recentSuppliers->count())
            ->get();

        return $recentSuppliers
            ->concat($alphabeticalSuppliers)
            ->mapWithKeys(fn (Supplier $supplier) => [
                $supplier->id => "({$supplier->code}) {$supplier->perusahaan}",
            ])
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Form Invoice')
                    ->schema([
                        // Header Section - Sumber Invoice
                        Section::make('Sumber Invoice')
                            ->description('Silahkan Pilih Supplier')
                            ->columns(2)
                            ->schema([
                                Select::make('selected_supplier')
                                    ->label('Supplier')
                                    ->options(fn () => self::getSupplierOptions())
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Supplier harus dipilih'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_order_request', null);
                                        $set('cabang_id', null);
                                        $set('selected_cabang_label', null);
                                        $set('selected_purchase_orders', []);
                                        self::clearDerivedPurchaseInvoiceState($set);
                                    }),

                                // Task 14: Select Order Request to filter POs
                                Select::make('selected_order_request')
                                    ->label('Order Request (OR)')
                                    ->options(function ($get) {
                                        return self::getOrderRequestOptions(
                                            $get('selected_supplier')
                                        );
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->required()
                                    ->helperText('Pilih Order Request terlebih dahulu. Purchase Order akan muncul setelah OR dipilih.')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_purchase_orders', []);
                                        self::clearDerivedPurchaseInvoiceState($set);
                                        $set('cabang_id', null);
                                        $set('selected_cabang_label', null);
                                    }),

                                Hidden::make('cabang_id')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Cabang harus ditentukan otomatis dari Purchase Order pertama.'
                                    ]),

                                TextInput::make('selected_cabang_label')
                                    ->label('Cabang')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(static::readonlyInputAttributes())
                                    ->helperText('Diisi dan dikunci otomatis dari Purchase Order pertama yang dipilih.'),

                                // Task 14: Multiple PO selection filtered by OR
                                Forms\Components\CheckboxList::make('selected_purchase_orders')
                                    ->label('Purchase Orders')
                                    ->hidden(fn($get) => blank($get('selected_order_request')))
                                    ->options(function ($get) {
                                        return self::getPurchaseOrderOptions(
                                            $get('selected_supplier'),
                                            $get('selected_order_request'),
                                            $get('cabang_id')
                                        );
                                    })
                                    ->disableOptionWhen(function ($value, $get) {
                                        $supplierId = $get('selected_supplier');
                                        $orId = $get('selected_order_request');
                                        $cabangId = self::resolveInvoiceCabangId($get('cabang_id'));
                                        if (!$supplierId || !$orId) {
                                            return false;
                                        }

                                        $po = PurchaseOrder::query()
                                            ->where('supplier_id', $supplierId)
                                            ->whereIn('status', ['approved', 'partially_received', 'completed'])
                                            ->where('refer_model_type', OrderRequest::class)
                                            ->where('refer_model_id', $orId)
                                            ->whereHas('purchaseReceipt', fn($receiptQuery) => $receiptQuery
                                                ->whereIn('status', ['partial', 'completed'])
                                                ->when($cabangId, fn ($query) => $query->where('cabang_id', $cabangId)))
                                            ->find($value);

                                        if (!$po) {
                                            return false;
                                        }

                                        $allReceiptIds = $po->purchaseReceipt()
                                            ->whereIn('status', ['partial', 'completed'])
                                            ->pluck('id')
                                            ->toArray();

                                        if (empty($allReceiptIds)) {
                                            return true;
                                        }

                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()
                                            ->pluck('purchase_receipts')
                                            ->flatten()
                                            ->intersect($allReceiptIds)
                                            ->unique()
                                            ->toArray();

                                        return count($invoicedReceiptIds) >= count($allReceiptIds);
                                    })
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->reactive()
                                    ->helperText(fn ($get) => blank($get('cabang_id'))
                                        ? 'Pilih Purchase Order terlebih dahulu untuk menentukan cabang invoice.'
                                        : 'Cabang dikunci dari Purchase Order pertama. Satu invoice hanya dapat memuat satu cabang.')
                                    ->required()
                                    ->minItems(1)
                                    ->validationMessages([
                                        'required' => 'Minimal satu Purchase Order harus dipilih.',
                                        'min' => 'Minimal satu Purchase Order harus dipilih.',
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        self::clearDerivedPurchaseInvoiceState($set);

                                        $selection = self::normalizePurchaseOrderSelectionForCabang($state);
                                        $selectedPurchaseOrderIds = $selection['ids'];
                                        $po = $selection['first_purchase_order'];
                                        $cabang = $selection['cabang'];

                                        if (empty($selectedPurchaseOrderIds) || ! $po || ! $cabang) {
                                            if (! empty($selection['requested_ids'])) {
                                                $set('selected_purchase_orders', []);
                                            }
                                            $set('cabang_id', null);
                                            $set('selected_cabang_label', null);

                                            return;
                                        }

                                        if ($selectedPurchaseOrderIds !== $selection['requested_ids']) {
                                            $set('selected_purchase_orders', $selectedPurchaseOrderIds);
                                        }

                                        $set('cabang_id', $cabang->id);
                                        $set('selected_cabang_label', self::formatCabangLabel($cabang));

                                        try {
                                            $currencyContext = self::transactionCurrencyContextFromPurchaseOrders($selectedPurchaseOrderIds);
                                        } catch (ValidationException $exception) {
                                            $set('selected_purchase_orders', []);
                                            $set('cabang_id', null);
                                            $set('selected_cabang_label', null);

                                            Notification::make()
                                                ->title('Mata uang Purchase Order tidak konsisten')
                                                ->body(collect($exception->errors())->flatten()->first()
                                                    ?? 'Pisahkan Purchase Order dengan mata uang atau rate berbeda ke invoice terpisah.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $set('currency_id', $currencyContext['currency_id']);
                                        $set('exchange_rate', $currencyContext['exchange_rate']);

                                        // Auto-set due date based on first PO TOP.
                                        $invoiceDate = $get('invoice_date') ?: now();
                                        $topType = strtolower(trim((string) ($po->top_type ?? 'credit_days')));
                                        $dueDate = in_array($topType, ['credit_days', 'credit', 'credit days'], true) && (int) ($po->tempo_hutang ?? 0) > 0
                                            ? \Carbon\Carbon::parse($invoiceDate)->addDays((int) $po->tempo_hutang)
                                            : \Carbon\Carbon::parse($invoiceDate);
                                        $set('due_date', $dueDate->toDateString());
                                    }),
                                Placeholder::make('purchase_invoice_currency_summary')
                                    ->label('Mata Uang / Rate')
                                    ->content(fn ($get) => self::createCurrencySummaryText($get('selected_purchase_orders')))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => ! empty($get('selected_purchase_orders'))),
                            ]),

                        // Invoice Info Section
                        Section::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->required()
                                    ->unique(table: 'invoices', column: 'invoice_number', ignoreRecord: true)
                                    ->validationMessages([
                                        'required' => 'Nomor invoice tidak boleh kosong',
                                        'max' => 'Nomor invoice terlalu panjang',
                                        'unique' => 'Nomor invoice sudah digunakan'
                                    ])
                                    ->suffixAction(
                                        Action::make('generate')
                                            ->icon('heroicon-m-arrow-path')
                                            ->tooltip('Generate Invoice Number')
                                            ->action(function ($set, $get) {
                                                $invoiceService = app(InvoiceService::class);
                                                $set('invoice_number', $invoiceService->generatePurchaseInvoiceNumber());
                                            })
                                    )
                                    ->maxLength(255),

                                DatePicker::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tanggal invoice harus diisi'
                                    ])
                                    ->default(now())
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Auto-update due date based on first selected PO TOP
                                        $poIds = $get('selected_purchase_orders');
                                        if ($poIds && count($poIds) > 0 && $state) {
                                            $po = PurchaseOrder::find($poIds[0]);
                                            if ($po) {
                                                $topType = strtolower(trim((string) ($po->top_type ?? 'credit_days')));
                                                $dueDate = in_array($topType, ['credit_days', 'credit', 'credit days'], true) && (int) ($po->tempo_hutang ?? 0) > 0
                                                    ? \Carbon\Carbon::parse($state)->addDays((int) $po->tempo_hutang)
                                                    : \Carbon\Carbon::parse($state);
                                                $set('due_date', $dueDate->toDateString());
                                            }
                                        }

                                        if ($state) {
                                            $set('due_date_display', $state);
                                        }
                                    }),

                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tanggal jatuh tempo harus diisi'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if ($state) {
                                            $set('due_date_display', $state);
                                        }
                                    }),

                                Section::make('Pajak Impor (Opsional)')
                                    ->visible(fn ($get) => static::purchaseOrderSelectionIsImport($get('selected_purchase_orders')))
                                    ->schema([
                                        TextInput::make('pph22_amount')
                                            ->label('PPh 22 Impor')
                                            ->indonesianMoney()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($set, $get) => self::recalculatePurchaseInvoiceTotalState($set, $get))
                                            ->visible(function ($get) {
                                                return static::purchaseOrderSelectionIsImport($get('selected_purchase_orders'));
                                            }),

                                        TextInput::make('bea_masuk_amount')
                                            ->label('Bea Masuk')
                                            ->indonesianMoney()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($set, $get) => self::recalculatePurchaseInvoiceTotalState($set, $get))
                                            ->visible(function ($get) {
                                                return static::purchaseOrderSelectionIsImport($get('selected_purchase_orders'));
                                            }),
                                    ])
                            ]),

                        // Purchase Receipts Selection
                        Section::make('Silahkan Pilih Purchase Receipt')
                            ->schema([
                                Placeholder::make('receipt_invoiced_info')
                                    ->label('')
                                    ->content('Receipt berstatus partial maupun completed dapat dipilih. Receipt yang berlabel "Sudah di-invoice" tetap ditampilkan, namun tidak dapat dipilih. Biaya lain dari receipt yang dipilih akan otomatis masuk ke Other Fees dan total invoice.'),
                                // Task 14: Receipts from ALL selected POs
                                Forms\Components\CheckboxList::make('selected_purchase_receipts')
                                    ->label('')
                                    ->options(function ($get) {
                                        $purchaseOrderIds = $get('selected_purchase_orders');
                                        if (!$purchaseOrderIds || empty($purchaseOrderIds)) return [];

                                        $purchaseOrders = PurchaseOrder::with(['purchaseOrderItem.currency', 'purchaseOrderCurrency.currency'])
                                            ->whereIn('id', $purchaseOrderIds)->get()->keyBy('id');
                                        if ($purchaseOrders->isEmpty()) return [];

                                        // Check which receipts are already invoiced
                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()->pluck('purchase_receipts')->flatten()->unique()->toArray();

                                        $options = [];
                                        foreach ($purchaseOrders as $purchaseOrder) {
                                            $purchaseReceipts = $purchaseOrder->purchaseReceipt()
                                                ->with('purchaseReceiptItem.purchaseOrderItem', 'purchaseReceiptBiaya')
                                                ->whereIn('status', ['partial', 'completed'])
                                                ->get();

                                            foreach ($purchaseReceipts as $receipt) {
                                                $isInvoiced = in_array($receipt->id, $invoicedReceiptIds);

                                                try {
                                                    $preview = app(PurchaseInvoiceAccountingService::class)
                                                        ->expectedReceiptBackedInvoiceDataFromReceiptIds([$receipt->id]);
                                                    $context = self::transactionCurrencyContextFromPurchaseOrders([$purchaseOrder->id]);
                                                    $amount = self::formatTransactionCurrencyAmount($preview['total'] ?? 0, $context);
                                                    $label = "[{$purchaseOrder->po_number}] {$receipt->receipt_number} — Total tagihan: {$amount}";
                                                } catch (ValidationException) {
                                                    $label = "[{$purchaseOrder->po_number}] {$receipt->receipt_number} — Konteks mata uang tidak valid";
                                                }

                                                if ($isInvoiced) $label .= ' (Sudah di-invoice)';
                                                $options[$receipt->id] = $label;
                                            }
                                        }
                                        return $options;
                                    })
                                    ->disableOptionWhen(function ($value) {
                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()
                                            ->pluck('purchase_receipts')
                                            ->flatten()
                                            ->unique()
                                            ->map(fn($id) => (int) $id)
                                            ->toArray();

                                        return in_array((int) $value, $invoicedReceiptIds, true);
                                    })
                                    ->columns(1)
                                    ->reactive()
                                    ->required()
                                    ->minItems(1)
                                    ->validationMessages([
                                        'required' => 'Minimal satu Purchase Receipt harus dipilih.',
                                        'min' => 'Minimal satu Purchase Receipt harus dipilih.',
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state || empty($state)) {
                                            self::clearDerivedPurchaseInvoiceState($set);
                                            return;
                                        }

                                        $purchaseOrderIds = collect((array) $get('selected_purchase_orders'))
                                            ->filter(fn ($id) => is_numeric($id))
                                            ->map(fn ($id) => (int) $id)
                                            ->unique()
                                            ->values();
                                        $receiptIds = collect((array) $state)
                                            ->filter(fn ($id) => is_numeric($id))
                                            ->map(fn ($id) => (int) $id)
                                            ->unique()
                                            ->values();
                                        $supplierId = is_numeric($get('selected_supplier')) ? (int) $get('selected_supplier') : null;

                                        if ($purchaseOrderIds->isEmpty() || $receiptIds->isEmpty() || ! $supplierId) {
                                            self::clearDerivedPurchaseInvoiceState($set);
                                            return;
                                        }

                                        $purchaseOrders = PurchaseOrder::query()
                                            ->with('supplier')
                                            ->whereIn('id', $purchaseOrderIds)
                                            ->where('supplier_id', $supplierId)
                                            ->get()
                                            ->keyBy('id');
                                        $purchaseReceipts = PurchaseReceipt::query()
                                            ->whereIn('id', $receiptIds)
                                            ->whereIn('purchase_order_id', $purchaseOrderIds)
                                            ->whereIn('status', ['partial', 'completed'])
                                            ->get();

                                        if ($purchaseOrders->count() !== $purchaseOrderIds->count()
                                            || $purchaseReceipts->count() !== $receiptIds->count()) {
                                            $set('selected_purchase_receipts', []);
                                            self::clearDerivedPurchaseInvoiceState($set);
                                            Notification::make()
                                                ->title('Sumber invoice tidak valid')
                                                ->body('Purchase Receipt harus berasal dari Purchase Order dan supplier yang dipilih.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        try {
                                            $preview = app(PurchaseInvoiceAccountingService::class)
                                                ->expectedReceiptBackedInvoiceDataFromReceiptIds($receiptIds->all());
                                        } catch (ValidationException $exception) {
                                            $set('selected_purchase_receipts', []);
                                            self::clearDerivedPurchaseInvoiceState($set);
                                            Notification::make()
                                                ->title('Receipt tidak dapat digabung')
                                                ->body(collect($exception->errors())->flatten()->first()
                                                    ?? 'Pisahkan receipt dengan mata uang atau rate berbeda ke invoice terpisah.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        if (! $preview) {
                                            self::clearDerivedPurchaseInvoiceState($set);
                                            return;
                                        }

                                        $firstPo = $purchaseOrders->get((int) ($preview['from_model_id'] ?? 0))
                                            ?? $purchaseOrders->first();
                                        $items = collect($preview['invoice_items'] ?? [])
                                            ->map(fn (array $item) => [
                                                'product_id' => $item['product_id'] ?? null,
                                                'quantity' => (float) ($item['quantity'] ?? 0),
                                                'price' => (float) ($item['price'] ?? 0),
                                                'subtotal' => (float) ($item['subtotal'] ?? $item['total'] ?? 0),
                                                'total' => (float) ($item['total'] ?? 0),
                                                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                                                'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                                            ])
                                            ->values()
                                            ->all();
                                        $receiptBiayaItems = collect($preview['receipt_fees'] ?? [])
                                            ->map(fn (array $fee) => [
                                                'receipt_id' => $fee['receipt_id'] ?? null,
                                                'nama_biaya' => $fee['nama_biaya'] ?? $fee['name'] ?? 'Biaya Lain',
                                                'total' => (float) ($fee['total'] ?? $fee['amount'] ?? 0),
                                            ])
                                            ->values()
                                            ->all();

                                        $set('cabang_id', $preview['cabang_id'] ?? $get('cabang_id'));
                                        $set('supplier_name', $firstPo?->supplier?->perusahaan);
                                        $set('supplier_phone', $firstPo?->supplier?->phone ?? '');
                                        $set('from_model_type', PurchaseOrder::class);
                                        $set('from_model_id', $preview['from_model_id'] ?? $firstPo?->id);
                                        $set('purchase_order_ids', $preview['purchase_order_ids'] ?? $purchaseOrderIds->all());
                                        $set('purchase_receipts', $receiptIds->all());
                                        $set('currency_id', $preview['currency_id'] ?? null);
                                        $set('exchange_rate', $preview['exchange_rate'] ?? 1);
                                        $set('invoiceItem', $items);
                                        $set('receiptBiayaItems', $receiptBiayaItems);
                                        $set('subtotal', (float) ($preview['subtotal'] ?? 0));
                                        $set('dpp', (float) ($preview['dpp'] ?? $preview['subtotal'] ?? 0));

                                        self::recalculatePurchaseInvoiceTotalState($set, $get, [
                                            'subtotal' => (float) ($preview['subtotal'] ?? 0),
                                            'receiptBiayaItems' => $receiptBiayaItems,
                                            'taxAmount' => (float) ($preview['ppn_amount'] ?? 0),
                                            'ppnRate' => (float) ($preview['ppn_rate'] ?? 0),
                                            'syncPpnRate' => true,
                                        ]);
                                    }),
                            ]),

                        // Invoice Items Section
                        Section::make('Item Invoice')
                            ->description('Item yang akan di-invoice berdasarkan Purchase Receipt yang dipilih')
                            ->schema([
                                Placeholder::make('invoice_item_readonly_info')
                                    ->label('')
                                    ->content('Harga mengikuti Purchase Receipt / Purchase Order dan tidak dapat diubah manual.'),
                                Repeater::make('invoiceItem')
                                    ->label('')
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Produk')
                                            ->options(\App\Models\Product::query()->orderBy('name')->limit(50)->get()->mapWithKeys(function ($product) {
                                                return [$product->id => $product->name];
                                            }))
                                            ->searchable()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Produk harus dipilih'
                                            ])
                                            ->disabled()
                                            ->dehydrated(true),
                                        TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Qty tidak boleh kosong',
                                                'numeric' => 'Qty harus berupa angka'
                                            ])
                                            ->disabled()
                                            ->dehydrated(true),
                                        Hidden::make('price')
                                            ->required()
                                            ->dehydrated(true),
                                        Hidden::make('subtotal')
                                            ->dehydrated(true),
                                        Hidden::make('total')
                                            ->required()
                                            ->dehydrated(true),
                                        Hidden::make('tax_rate')
                                            ->default(0)
                                            ->dehydrated(true),
                                        Hidden::make('tax_amount')
                                            ->default(0)
                                            ->dehydrated(true),
                                        Placeholder::make('unit_price_preview')
                                            ->label('Harga Satuan')
                                            ->content(fn ($get) => self::formatTransactionForCurrencyState(
                                                $get('price'),
                                                $get('../../currency_id'),
                                                $get('../../exchange_rate')
                                            )),
                                        Placeholder::make('line_dpp_preview')
                                            ->label('DPP Baris')
                                            ->content(fn ($get) => self::formatTransactionForCurrencyState(
                                                $get('total'),
                                                $get('../../currency_id'),
                                                $get('../../exchange_rate')
                                            )),
                                        Placeholder::make('line_tax_preview')
                                            ->label('PPN')
                                            ->content(fn ($get) => self::formatTransactionForCurrencyState(
                                                $get('tax_amount'),
                                                $get('../../currency_id'),
                                                $get('../../exchange_rate')
                                            )),
                                        Placeholder::make('line_total_preview')
                                            ->label('Total Termasuk PPN')
                                            ->content(fn ($get) => self::formatTransactionForCurrencyState(
                                                (float) MoneyHelper::safeParse($get('total') ?? 0)
                                                    + (float) MoneyHelper::safeParse($get('tax_amount') ?? 0),
                                                $get('../../currency_id'),
                                                $get('../../exchange_rate')
                                            )),
                                    ])
                                    ->columns(6)
                                    ->disableItemCreation()
                                    ->disableItemDeletion()
                                    ->disableItemMovement(),
                            ]),

                        Repeater::make('receiptBiayaItems')
                            ->label('Biaya Lain dari Purchase Receipt')
                            ->helperText('Biaya ini dibawa dari receipt terpilih dan akan digabung ke biaya invoice saat disimpan.')
                            ->schema([
                                Hidden::make('receipt_id'),
                                Hidden::make('nama_biaya')->required(),
                                Hidden::make('total')->required(),
                                Placeholder::make('receipt_fee_name_preview')
                                    ->label('Nama Biaya')
                                    ->content(fn ($get) => $get('nama_biaya') ?: 'Biaya Lain'),
                                Placeholder::make('receipt_fee_total_preview')
                                    ->label('Jumlah')
                                    ->content(fn ($get) => self::formatTransactionForCurrencyState(
                                        $get('total'),
                                        $get('../../currency_id'),
                                        $get('../../exchange_rate')
                                    )),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),

                        // Biaya Lain Section
                        Section::make('Biaya Lain - lain')
                            ->schema([
                                Repeater::make('other_fees')
                                    ->label('')
                                    ->addActionLabel('Tambah Biaya Lain')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Biaya')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Nama biaya tidak boleh kosong'
                                            ])
                                            ->default('Biaya Lain')
                                            ->disabled(fn($operation) => $operation === 'edit'),
                                        TextInput::make('amount')
                                            ->label('Jumlah')
                                            ->indonesianMoney()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Jumlah tidak boleh kosong',
                                            ])
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->disabled(fn($operation) => $operation === 'edit')
                                            ->dehydrated(true),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->disableItemCreation(fn($operation) => $operation === 'edit')
                                    ->disableItemDeletion(fn($operation) => $operation === 'edit')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        self::recalculatePurchaseInvoiceTotalState($set, $get, [
                                            'otherFees' => $state ?? [],
                                        ]);
                                    })
                                    ->collapsible(),
                            ]),

                        // Tax and Total Summary
                        Section::make('Ringkasan Nilai Invoice')
                            ->columns(2)
                            ->schema([
                                Hidden::make('dpp'),
                                Hidden::make('tax')->default(0),
                                Hidden::make('ppn_amount'),
                                Hidden::make('total'),

                                Placeholder::make('dpp_preview')
                                    ->label('Dasar Pengenaan Pajak (DPP)')
                                    ->content(fn ($get) => self::formatTransactionState($get, $get('dpp'))),

                                TextInput::make('ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'PPN rate harus berupa angka'
                                    ])
                                    ->suffix('%')
                                    ->default(fn() => \App\Models\TaxSetting::activeRate('PPN'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(static::readonlyInputAttributes())
                                    ->helperText('PPN otomatis mengikuti Purchase Order terkait.'),

                                Placeholder::make('ppn_amount_preview')
                                    ->label('Nilai PPN')
                                    ->content(fn ($get) => self::formatTransactionState($get, $get('ppn_amount'))),

                                Placeholder::make('other_fee_preview')
                                    ->label('Biaya Lain')
                                    ->content(function ($get) {
                                        $manual = collect($get('other_fees') ?? [])
                                            ->sum(fn ($fee) => (float) MoneyHelper::safeParse($fee['amount'] ?? 0));
                                        $receipt = collect($get('receiptBiayaItems') ?? [])
                                            ->sum(fn ($fee) => (float) MoneyHelper::safeParse($fee['total'] ?? 0));

                                        return self::formatTransactionState($get, $manual + $receipt);
                                    }),

                                Placeholder::make('import_charge_preview')
                                    ->label('Biaya Impor')
                                    ->content(fn ($get) => self::formatTransactionState(
                                        $get,
                                        self::importChargeTotalFromState($get)
                                    ))
                                    ->visible(fn ($get) => static::purchaseOrderSelectionIsImport($get('selected_purchase_orders'))),
                            ]),

                        // Grand Total
                        Section::make('Grand Total Invoice')
                            ->schema([
                                Placeholder::make('grand_total_preview')
                                    ->label('Grand Total')
                                    ->content(fn ($get) => self::formatTransactionState($get, $get('total')))
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),

                        // Status Invoice
                        Section::make('Status Invoice')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(Invoice::getStatusOptions())
                                    ->default(Invoice::STATUS_DRAFT)
                                    ->visible(fn () => static::canManuallySetStatus())
                                    ->disabled(fn () => !static::canManuallySetStatus()),
                                Hidden::make('status')
                                    ->default(Invoice::STATUS_DRAFT)
                                    ->visible(fn () => !static::canManuallySetStatus()),
                            ]),

                        // COA Selection
                        Section::make('Pemilihan COA (Chart of Account)')
                            ->description('Pilih COA yang sesuai untuk pencatatan journal entry')
                            ->columns(2)
                            ->collapsed()
                            ->collapsible()
                            ->schema([
                                Select::make('accounts_payable_coa_id')
                                    ->label('COA Hutang Supplier (Accounts Payable)')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'liability')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'COA hutang supplier harus dipilih'
                                    ])
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', config('coa.accounts_payable', '2110'))->first()?->id;
                                    }),

                                Select::make('ppn_masukan_coa_id')
                                    ->label('COA PPn Masukan')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'asset')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id;
                                    }),

                                Select::make('inventory_coa_id')
                                    ->label('COA Inventory')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'asset')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id;
                                    }),

                                Select::make('expense_coa_id')
                                    ->label('COA Biaya Lain (Opsional)')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'expense')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id;
                                    })
                                    ->helperText('COA ini digunakan jika ada biaya yang tidak memiliki COA di PO'),
                            ]),

                        // Hidden fields
                        Hidden::make('from_model_type')->default('App\Models\PurchaseOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('supplier_name'),
                        Hidden::make('supplier_phone'),
                        Hidden::make('currency_id'),
                        Hidden::make('exchange_rate')->default(1),
                        Hidden::make('subtotal'),
                        Hidden::make('purchase_receipts'),
                    ])
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Purchase Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('fromModel.po_number')
                            ->label('PO Number'),
                        Infolists\Components\TextEntry::make('fromModel.supplier.perusahaan')
                            ->label('Supplier'),
                        Infolists\Components\TextEntry::make('fromModel.supplier.phone')
                            ->label('Supplier Phone'),
                        Infolists\Components\TextEntry::make('fromModel.order_date')
                            ->label('PO Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('purchase_receipts_display')
                            ->label('Purchase Receipts')
                            ->listWithLineBreaks()
                            ->state(function (Invoice $record) {
                                if (!$record->purchase_receipts) return 'No receipts';

                                $receipts = \App\Models\PurchaseReceipt::whereIn('id', $record->purchase_receipts)
                                    ->get()
                                    ->map(function ($receipt) {
                                        return $receipt->receipt_number . ' (' . \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') . ')';
                                    })
                                    ->toArray();

                                return $receipts;
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Invoice Items')
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice_items_display')
                            ->label('Items')
                            ->listWithLineBreaks()
                            ->state(function (Invoice $record) {
                                $items = [];
                                foreach ($record->invoiceItem as $item) {
                                    $product = $item->product;
                                    $productName = $product ? $product->name : 'Unknown Product';
                                    $items[] = sprintf(
                                        '%s: %s x %s = %s',
                                        $productName,
                                        number_format($item->quantity, 2, ',', '.'),
                                        self::formatInvoiceCurrencyPair($record, $item->price),
                                        self::formatInvoiceCurrencyPair($record, $item->total)
                                    );
                                }
                                return $items;
                            }),
                    ]),

                Infolists\Components\Section::make('Invoice Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->label('Invoice Number'),
                        Infolists\Components\TextEntry::make('display_currency')
                            ->label('Mata Uang')
                            ->state(fn (Invoice $record) => $record->displayCurrency?->code ? ($record->displayCurrency?->symbol . ' ' . $record->displayCurrency?->code) : '-'),
                        Infolists\Components\TextEntry::make('invoice_date')
                            ->label('Invoice Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, Invoice $record) => self::formatInvoiceCurrencyPair($record, $state)),
                        Infolists\Components\TextEntry::make('ppn_rate')
                            ->label('PPN Rate (%)')
                            ->state(function (Invoice $record) {
                                return $record->ppn_rate ? $record->ppn_rate . '%' : '0%';
                            }),
                        Infolists\Components\TextEntry::make('dpp')
                            ->label('DPP')
                            ->formatStateUsing(fn ($state, Invoice $record) => self::formatInvoiceCurrencyPair($record, $state)),
                        Infolists\Components\TextEntry::make('other_fee_total')
                            ->label('Other Fees')
                            ->formatStateUsing(fn ($state, Invoice $record) => self::formatInvoiceCurrencyPair($record, $state))
                            ->state(function (Invoice $record) {
                                return $record->getOtherFeeTotalAttribute();
                            }),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Invoice Total')
                            ->formatStateUsing(fn ($state, Invoice $record) => self::formatInvoiceCurrencyPair($record, $state)),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Chart of Accounts')
                    ->schema([
                        Infolists\Components\TextEntry::make('accounts_payable_coa_display')
                            ->label('Accounts Payable COA')
                            ->state(fn(Invoice $record) => self::formatCoa($record->accountsPayableCoa)),
                        Infolists\Components\TextEntry::make('ppn_masukan_coa_display')
                            ->label('PPN Masukan COA')
                            ->state(fn(Invoice $record) => self::formatCoa($record->ppnMasukanCoa)),
                        Infolists\Components\TextEntry::make('inventory_coa_display')
                            ->label('Inventory COA')
                            ->state(fn(Invoice $record) => self::formatCoa($record->inventoryCoa)),
                        Infolists\Components\TextEntry::make('expense_coa_display')
                            ->label('Expense COA')
                            ->state(fn(Invoice $record) => self::formatCoa($record->expenseCoa)),
                    ])
                    ->columns(2)
                    ->collapsed(),



                Infolists\Components\Section::make('Journal Entries')
                    ->schema([
                        Infolists\Components\ViewEntry::make('journal_entries_table')
                            ->label('')
                            ->view('filament.infolists.journal-entries-table')
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function modifyInfolistQueryUsing(Builder $query): Builder
    {
        return $query->with([
            'fromModel.purchaseOrderItem.purchaseReceiptItem',
            'fromModel.supplier',
            'fromModel.purchaseOrderBiaya',
            'invoiceItem.product',
            'cabang',
            'accountsPayableCoa',
            'ppnMasukanCoa',
            'inventoryCoa',
            'expenseCoa'
        ]);
    }

    protected static function formatCoa(?\App\Models\ChartOfAccount $coa): string
    {
        if (! $coa || ! $coa->id || empty($coa->code)) {
            return '-';
        }

        return $coa->code . ' - ' . $coa->name;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('from_model_type', 'App\Models\PurchaseOrder');
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),

                TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier_phone')
                    ->label('No. Telepon')
                    ->searchable(),

                TextColumn::make('invoice_date')
                    ->label('Tanggal Invoice')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total Transaksi')
                    ->formatStateUsing(fn ($state, Invoice $record) => self::formatInvoiceCurrencyPair($record, $state))
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            Invoice::STATUS_DRAFT => 'Draft',
                            Invoice::STATUS_SENT => 'Terkirim',
                            Invoice::STATUS_PAID => 'Lunas',
                            Invoice::STATUS_PARTIALLY_PAID => 'Dibayar Sebagian',
                            Invoice::STATUS_OVERDUE => 'Terlambat',
                            default => $state,
                        };
                    })
                    ->colors([
                        'secondary' => Invoice::STATUS_DRAFT,
                        'warning' => Invoice::STATUS_SENT,
                        'success' => Invoice::STATUS_PAID,
                        'primary' => Invoice::STATUS_PARTIALLY_PAID,
                        'danger' => Invoice::STATUS_OVERDUE,
                    ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    \Filament\Tables\Actions\Action::make('view_journal_entries')
                        ->label('Lihat Journal Entries')
                        ->icon('heroicon-o-book-open')
                        ->color('success')
                        ->action(function ($record) {
                            $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
                                ->where('source_id', $record->id)
                                ->get();

                            if ($journalEntries->count() === 1) {
                                // Jika hanya 1 journal entry, langsung ke halaman detail
                                $entry = $journalEntries->first();
                                return redirect()->to("/admin/journal-entries/{$entry->id}");
                            } else {
                                // Jika multiple entries, gunakan filter dengan format yang sesuai dengan filter options
                                $sourceType = 'App\\Models\\Invoice'; // Format yang sama dengan filter options
                                $sourceId = $record->id;
                                return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}");
                            }
                        }),
                    \Filament\Tables\Actions\Action::make('mark_as_sent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn($record) => $record->status === Invoice::STATUS_DRAFT)
                        ->requiresConfirmation()
                        ->modalHeading('Mark Invoice as Sent')
                        ->modalDescription('Are you sure you want to mark this invoice as sent? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, Mark as Sent')
                        ->action(function ($record) {
                            $record->update(['status' => Invoice::STATUS_SENT]);
                            \Filament\Notifications\Notification::make()
                                ->title('Invoice marked as sent')
                                ->success()
                                ->send();
                        }),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Invoice Pembelian</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Invoice Pembelian adalah faktur dari supplier untuk pembelian barang/jasa, digunakan untuk mencatat hutang dan memproses pembayaran.</li>' .
                    '<li><strong>Status Flow:</strong> Draft → Sent. Invoice dikirim setelah dibuat dan dapat diedit sebelum dikirim.</li>' .
                    '<li><strong>Validasi:</strong> Subtotal, Tax, PPN dihitung otomatis berdasarkan item. Total invoice digunakan untuk Account Payable.</li>' .
                    '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah invoice), <em>Delete</em> (hapus), <em>Mark as Sent</em> (ubah status ke sent).</li>' .
                    '<li><strong>Filters:</strong> Supplier, Status, Date Range, Amount Range, dll.</li>' .
                    '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan invoice dari cabang tersebut jika tidak memiliki akses all.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan Purchase Order, Purchase Receipt, dan menghasilkan Account Payable.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ));
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        // Calculate PPN amount for display
        $subtotal = (float) \App\Helpers\MoneyHelper::safeParse($data['subtotal'] ?? 0);
        $ppnRate = (float) \App\Helpers\MoneyHelper::safeParse($data['ppn_rate'] ?? 0);

        if ($ppnRate <= 0 && !empty($data['tax']) && $subtotal > 0) {
            $legacyTaxValue = (float) \App\Helpers\MoneyHelper::safeParse($data['tax']);
            $ppnRate = $legacyTaxValue <= 100 ? $legacyTaxValue : round(($legacyTaxValue / $subtotal) * 100, 2);
        }

        $data['tax'] = $ppnRate;
        $data['ppn_rate'] = $ppnRate;
        $data['ppn_amount'] = $subtotal * $ppnRate / 100;

        return $data;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = Invoice::STATUS_DRAFT;

        return static::prepareInvoiceData($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::prepareInvoiceData($data);
    }

    protected static function prepareInvoiceData(array $data): array
    {
        // Remove form-specific fields and prepare data for database
        unset($data['selected_supplier'], $data['selected_purchase_order'], $data['selected_purchase_receipts']);

        // Ensure other_fee is properly formatted - combine manual fees and receipt fees
        $otherFees = [];
        if (isset($data['other_fees']) && is_array($data['other_fees'])) {
            $otherFees = array_merge($otherFees, $data['other_fees']);
        }
        if (isset($data['receiptBiayaItems']) && is_array($data['receiptBiayaItems'])) {
            $otherFees = array_merge($otherFees, $data['receiptBiayaItems']);
        }
        $data['pph22_amount'] = (float) MoneyHelper::safeParse($data['pph22_amount'] ?? 0);
        $data['bea_masuk_amount'] = (float) MoneyHelper::safeParse($data['bea_masuk_amount'] ?? 0);

        foreach ($otherFees as $fee) {
            $name = strtolower(trim((string) ($fee['name'] ?? $fee['nama_biaya'] ?? '')));
            $amount = (float) MoneyHelper::safeParse($fee['amount'] ?? $fee['total'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            if (preg_match('/\bpph\b|pph\s*22/', $name)) {
                $data['pph22_amount'] += $amount;
            }

            if (preg_match('/bea masuk|customs|bm|import duty|cukai/', $name)) {
                $data['bea_masuk_amount'] += $amount;
            }
        }
        $data['other_fee'] = collect($otherFees)->map(function ($fee) {
            $amount = (float) \App\Helpers\MoneyHelper::safeParse($fee['total'] ?? $fee['amount'] ?? 0);
            $name = strtolower(trim((string) ($fee['name'] ?? $fee['nama_biaya'] ?? '')));

            if ($amount <= 0) {
                return null;
            }

            if (preg_match('/\bpph\b|pph\s*22/', $name) || preg_match('/bea masuk|customs|bm|import duty|cukai/', $name)) {
                return null;
            }

            return [
                'name' => $fee['nama_biaya'] ?? $fee['name'] ?? 'Biaya Lain',
                'amount' => $amount,
            ];
        })->filter()->values()->toArray();

        // Remove temporary fields
        unset($data['other_fees'], $data['receiptBiayaItems']);

        if (isset($data['invoiceItem']) && is_array($data['invoiceItem'])) {
            $data['invoiceItem'] = array_map(function ($item) {
                unset($item['price_display'], $item['total_display']);

                return $item;
            }, $data['invoiceItem']);
        }

        // Calculate totals if not set - use PPN only (no separate tax)
        $subtotal = (float) \App\Helpers\MoneyHelper::safeParse($data['subtotal'] ?? 0);
        $ppnRate = (float) \App\Helpers\MoneyHelper::safeParse($data['ppn_rate'] ?? 0);
        $ppnAmount = $subtotal * $ppnRate / 100;
        $importChargeTotal = (float) ($data['pph22_amount'] ?? 0) + (float) ($data['bea_masuk_amount'] ?? 0);

        if (!isset($data['total']) || $data['total'] == 0) {
            $otherFeeTotal = (float) collect($data['other_fee'] ?? [])->sum(fn($fee) => (float) \App\Helpers\MoneyHelper::safeParse($fee['amount'] ?? 0));
            $data['total'] = $subtotal + $otherFeeTotal + $importChargeTotal + $ppnAmount;
        }
        $data['tax'] = $ppnRate; // Store tax as percentage for consistency
        $data['ppn_amount'] = $ppnAmount;

        return $data;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static function formatJournalEntriesTableHtml(Invoice $record): string
    {
        $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
            ->where('source_id', $record->id)
            ->with('coa')
            ->get();

        if ($journalEntries->isEmpty()) {
            return '<div class="text-gray-500 italic">No journal entries found</div>';
        }

        $html = '<div class="overflow-x-auto">';
        $html .= '<table class="w-full border-collapse border border-gray-300 text-sm">';
        $html .= '<thead>';
        $html .= '<tr class="bg-gray-50">';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA Code</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA Name</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-left font-semibold">Reference</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-right font-semibold">Debit</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-right font-semibold">Credit</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($journalEntries as $entry) {
            $debit = $entry->debit > 0 ? MoneyHelper::rupiah($entry->debit) : '-';
            $credit = $entry->credit > 0 ? MoneyHelper::rupiah($entry->credit) : '-';

            $html .= '<tr class="hover:bg-gray-50">';
            $html .= '<td class="border border-gray-300 px-3 py-2 font-mono text-xs">' . htmlspecialchars($entry->coa->code) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2">' . htmlspecialchars(substr($entry->coa->name, 0, 40)) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2">' . htmlspecialchars(substr($entry->reference ?? '', 0, 35)) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2 text-right font-mono ' . ($entry->debit > 0 ? 'text-green-600 font-semibold' : 'text-gray-400') . '">' . htmlspecialchars($debit) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2 text-right font-mono ' . ($entry->credit > 0 ? 'text-red-600 font-semibold' : 'text-gray-400') . '">' . htmlspecialchars($credit) . '</td>';
            $html .= '</tr>';

            // Add description below each entry if exists
            if (!empty($entry->description)) {
                $html .= '<tr class="bg-gray-25">';
                $html .= '<td colspan="5" class="border border-gray-300 px-3 py-1 text-xs text-gray-600 italic">';
                $html .= '<strong>Description:</strong> ' . htmlspecialchars($entry->description);
                $html .= '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'view' => Pages\ViewPurchaseInvoice::route('/{record}'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }

    protected static function resolveInvoiceCabangId(mixed $selectedCabangId = null): ?int
    {
        $user = Auth::user();
        $manageType = $user?->manage_type ?? [];

        if (is_array($manageType) && in_array('all', $manageType, true)) {
            return filled($selectedCabangId) ? (int) $selectedCabangId : null;
        }

        return filled($user?->cabang_id) ? (int) $user->cabang_id : (filled($selectedCabangId) ? (int) $selectedCabangId : null);
    }

    protected static function orderRequestsHaveCabangColumn(): bool
    {
        return Schema::hasColumn('order_requests', 'cabang_id');
    }

    public static function getOrderRequestOptions(mixed $supplierId, mixed $selectedCabangId = null): array
    {
        $supplierId = filled($supplierId) ? (int) $supplierId : null;
        $cabangId = self::resolveInvoiceCabangId($selectedCabangId);

        if (!$supplierId) {
            return [];
        }

        return OrderRequest::query()
            ->when($cabangId, function ($query) use ($cabangId) {
                $query->where(function ($branchQuery) use ($cabangId) {
                    if (self::orderRequestsHaveCabangColumn()) {
                        $branchQuery->where('cabang_id', $cabangId);
                    } else {
                        $branchQuery->whereHas('orderRequestItem', fn($itemQuery) => $itemQuery->where('cabang_id', $cabangId));
                    }

                    $branchQuery
                        ->orWhereHas('orderRequestItem', fn($itemQuery) => $itemQuery->where('cabang_id', $cabangId))
                        ->orWhereHas('purchaseOrders', fn($purchaseOrderQuery) => $purchaseOrderQuery->whereHas('purchaseReceipt', fn($receiptQuery) => $receiptQuery->where('cabang_id', $cabangId)));
                });
            })
            ->where(function ($q) use ($supplierId) {
                $q->whereHas('orderRequestItem', fn($iq) => $iq->where('supplier_id', $supplierId))
                    ->orWhereHas('purchaseOrders', fn($pq) => $pq->where('supplier_id', $supplierId));
            })
            // ->whereHas('purchaseOrder', function ($q) {
            //     $q->where('status', 'completed')
            //       ->whereHas('purchaseReceipt', fn($q2) => $q2->where('status', 'completed'));
            // })
            ->orderByDesc('request_date')
            ->get()
            ->mapWithKeys(fn($or) => [$or->id => $or->request_number])
            ->toArray();
    }

    protected static function formatCabangLabel(?Cabang $cabang): ?string
    {
        if (! $cabang || ! $cabang->exists) {
            return null;
        }

        return "({$cabang->kode}) {$cabang->nama}";
    }

    /**
     * Keep a purchase-invoice selection within the branch of its first PO.
     * This is also used to safely handle bulk checkbox updates.
     *
     * @return array{requested_ids: array<int, int>, ids: array<int, int>, first_purchase_order: ?PurchaseOrder, cabang: ?Cabang}
     */
    protected static function normalizePurchaseOrderSelectionForCabang(mixed $state): array
    {
        $requestedIds = collect((array) $state)
            ->map(fn ($value, $key) => is_bool($value) ? ($value ? $key : null) : $value)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            return [
                'requested_ids' => [],
                'ids' => [],
                'first_purchase_order' => null,
                'cabang' => null,
            ];
        }

        $purchaseOrders = PurchaseOrder::query()
            ->with(['purchaseReceipt' => fn ($query) => $query
                ->whereIn('status', ['partial', 'completed'])
                ->with('cabang')
                ->orderBy('id')])
            ->whereIn('id', $requestedIds)
            ->get()
            ->keyBy('id');
        $firstPurchaseOrder = $purchaseOrders->get($requestedIds->first());

        $cabang = $firstPurchaseOrder?->purchaseReceipt->first()?->cabang;
        $cabangId = $cabang?->id;

        if (! $firstPurchaseOrder || ! $cabangId) {
            return [
                'requested_ids' => $requestedIds->all(),
                'ids' => [],
                'first_purchase_order' => null,
                'cabang' => null,
            ];
        }

        $sameCabangIds = $requestedIds
            ->filter(fn (int $id) => (int) ($purchaseOrders->get($id)?->purchaseReceipt->first()?->cabang_id ?? 0) === (int) $cabangId)
            ->values()
            ->all();

        return [
            'requested_ids' => $requestedIds->all(),
            'ids' => $sameCabangIds,
            'first_purchase_order' => $firstPurchaseOrder,
            'cabang' => $cabang,
        ];
    }

    public static function getOrderRequestCabangContext(mixed $orderRequestId, mixed $supplierId = null): array
    {
        $orderRequestId = filled($orderRequestId) ? (int) $orderRequestId : null;
        $supplierId = filled($supplierId) ? (int) $supplierId : null;

        if (! $orderRequestId) {
            return ['id' => null, 'label' => null];
        }

        $orderRequest = OrderRequest::query()->find($orderRequestId);
        $cabangId = self::orderRequestsHaveCabangColumn() && filled($orderRequest?->cabang_id)
            ? (int) $orderRequest->cabang_id
            : null;

        if (! $cabangId) {
            $cabangId = OrderRequestItem::query()
                ->where('order_request_id', $orderRequestId)
                ->when($supplierId, fn($query) => $query->where('supplier_id', $supplierId))
                ->whereNotNull('cabang_id')
                ->orderBy('id')
                ->value('cabang_id');
        }

        if (! $cabangId) {
            $cabangId = PurchaseReceipt::withoutGlobalScopes()
                ->whereNotNull('cabang_id')
                ->whereHas('purchaseOrder', function ($purchaseOrderQuery) use ($orderRequestId, $supplierId) {
                    $purchaseOrderQuery
                        ->withoutGlobalScopes()
                        ->where('refer_model_type', OrderRequest::class)
                        ->where('refer_model_id', $orderRequestId)
                        ->when($supplierId, fn($query) => $query->where('supplier_id', $supplierId));
                })
                ->orderBy('id')
                ->value('cabang_id');
        }

        $cabang = $cabangId ? Cabang::query()->find($cabangId) : null;

        return [
            'id' => $cabang?->id,
            'label' => self::formatCabangLabel($cabang),
        ];
    }

    public static function getPurchaseOrderOptions(mixed $supplierId, mixed $orderRequestId, mixed $selectedCabangId = null): array
    {
        $supplierId = filled($supplierId) ? (int) $supplierId : null;
        $orderRequestId = filled($orderRequestId) ? (int) $orderRequestId : null;
        $cabangId = self::resolveInvoiceCabangId($selectedCabangId);

        if (!$supplierId || !$orderRequestId) {
            return [];
        }

        $query = PurchaseOrder::query()
            ->with(['purchaseReceipt' => fn ($receiptQuery) => $receiptQuery
                ->whereIn('status', ['partial', 'completed'])
                ->when($cabangId, fn ($branchReceiptQuery) => $branchReceiptQuery->where('cabang_id', $cabangId))
                ->with('cabang')
                ->orderBy('id')])
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['approved', 'partially_received', 'completed'])
            ->whereHas(
                'purchaseReceipt',
                fn($receiptQuery) => $receiptQuery
                    ->whereIn('status', ['partial', 'completed'])
                    ->when($cabangId, fn($branchReceiptQuery) => $branchReceiptQuery->where('cabang_id', $cabangId))
            )
            // Allow PO selection once QC has produced a partial or completed receipt.
            ->where('refer_model_type', 'App\\Models\\OrderRequest')
            ->where('refer_model_id', $orderRequestId);

        return $query->get()
            ->mapWithKeys(function ($po) {
                $allReceiptIds = $po->purchaseReceipt()
                    ->whereIn('status', ['partial', 'completed'])
                    ->pluck('id')->toArray();

                $invoicedReceiptIds = Invoice::where('from_model_type', 'App\\Models\\PurchaseOrder')
                    ->whereNotNull('purchase_receipts')
                    ->get()->pluck('purchase_receipts')->flatten()
                    ->intersect($allReceiptIds)->unique()->toArray();

                $fullyInvoiced = !empty($allReceiptIds) && count($invoicedReceiptIds) >= count($allReceiptIds);
                $branchLabel = self::formatCabangLabel($po->purchaseReceipt->first()?->cabang) ?? 'Cabang tidak tersedia';
                $label = $po->po_number . ' — ' . $branchLabel;
                if ($fullyInvoiced) {
                    $label .= ' [Sudah di-invoice]';
                }

                return [$po->id => $label];
            })
            ->toArray();
    }
}
