<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\CustomerReceiptResource\Pages;
use App\Filament\Resources\CustomerReceiptResource\Pages\ViewCustomerReceipt;
use App\Helpers\MoneyHelper;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\JournalEntry;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class CustomerReceiptResource extends Resource
{
    protected static ?string $model = CustomerReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Pembayaran Keuangan';

    protected static ?string $navigationLabel = 'Penerimaan Pelanggan';

    protected static ?string $modelLabel = 'Penerimaan Pelanggan';

    protected static ?string $pluralModelLabel = 'Penerimaan Pelanggan';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    protected static function getPaymentMethodOptions(): array
    {
        return [
            'Cash' => 'Cash',
            'Transfer' => 'Transfer',
            'Giro' => 'Giro',
            'Cheque' => 'Cheque',
            'Deposit' => 'Deposit',
        ];
    }

    protected static function getCoaQueryByPaymentMethod(?string $paymentMethod): Builder
    {
        $normalizedPaymentMethod = strtolower(trim((string) $paymentMethod));

        return match ($normalizedPaymentMethod) {
            'cash' => ChartOfAccount::query()
                ->where('is_active', true)
                ->where(function ($builder) {
                    $builder->where('code', 'LIKE', '111%')
                        ->where(function ($nested) {
                            $nested->where('name', 'LIKE', '%kas%')
                                ->orWhere('name', 'LIKE', '%tunai%');
                        });
                }),
            'transfer', 'bank transfer', 'cheque', 'giro' => ChartOfAccount::query()
                ->where('is_active', true)
                ->where(function ($builder) {
                    $builder->where('code', 'LIKE', '111%')
                        ->where(function ($nested) {
                            $nested->where('name', 'LIKE', '%bank%')
                                ->orWhere('name', 'LIKE', '%rekening%')
                                ->orWhere('name', 'LIKE', '%giro%')
                                ->orWhere('name', 'LIKE', '%cek%')
                                ->orWhere('name', 'LIKE', '%cheque%');
                        });
                }),
            'deposit' => ChartOfAccount::query()
                ->where('is_active', true)
                ->where(function ($builder) {
                    $builder->where('code', config('coa.customer_deposit'))
                        ->orWhere(function ($nested) {
                            $nested->where('type', 'liability')
                                ->where(function ($liabilityBuilder) {
                                    $liabilityBuilder->where('name', 'LIKE', '%deposit%')
                                        ->orWhere('name', 'LIKE', '%titipan%')
                                        ->orWhere('name', 'LIKE', '%uang muka pelanggan%');
                                });
                        });
                }),
            default => ChartOfAccount::query()->where('is_active', true),
        };
    }

    public static function getDefaultCoaIdByPaymentMethod(?string $paymentMethod): ?int
    {
        $query = static::getCoaQueryByPaymentMethod($paymentMethod);

        return $query->orderBy('code')->value('id');
    }

    public static function getCoaOptionsByPaymentMethod(?string $paymentMethod): array
    {
        return static::getCoaQueryByPaymentMethod($paymentMethod)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (ChartOfAccount $coa) => [$coa->id => "({$coa->code}) {$coa->name}"])
            ->toArray();
    }

    public static function resolveInvoiceRemainingAmount(Invoice $invoice): float
    {
        $accountReceivable = $invoice->accountReceivable;

        if ($accountReceivable?->getKey()) {
            return max(0, (float) MoneyHelper::parse($accountReceivable->remaining ?? 0));
        }

        $paidTotal = (float) CustomerReceiptItem::query()
            ->where('invoice_id', $invoice->id)
            ->selectRaw('COALESCE(SUM(amount), 0) as paid_total')
            ->value('paid_total');

        return max(0, (float) MoneyHelper::parse($invoice->total ?? 0) - $paidTotal);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Customer Receipt')
                    ->schema([
                        // Header Section - Customer and Payment Date
                        Section::make()
                            ->columns(2)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Customer belum dipilih',
                                    ])
                                    ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                                        return "({$customer->code}) {$customer->name}";
                                    })
                                    ->relationship('customer', 'name')
                                    ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                        if ($state) {
                                            $customer = Customer::find($state);

                                            if ($customer?->cabang_id) {
                                                $set('cabang_id', $customer->cabang_id);
                                            }
                                        }

                                        $set('selected_invoices', []);
                                        $set('total_payment', self::formatMoneyState(0));
                                        $set('payment_adjustment', 0);

                                        // Force refresh ViewField component
                                        $livewire->dispatch('refreshInvoiceTable');
                                    })
                                    ->required(),

                                DatePicker::make('payment_date')
                                    ->label('Payment Date')
                                    ->required()
                                    ->default(now())
                                    ->validationMessages([
                                        'required' => 'Tanggal pembayaran wajib diisi',
                                    ]),
                                Select::make('cabang_id')
                                    ->label('Cabang')
                                    ->preload()
                                    ->searchable()
                                    ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                    }))
                                    ->visible(function () {
                                        $manageType = Auth::user()?->manage_type ?? [];

                                        return in_array('all', is_array($manageType) ? $manageType : [$manageType]);
                                    })
                                    ->default(function () {
                                        $manageType = Auth::user()?->manage_type ?? [];

                                        return in_array('all', is_array($manageType) ? $manageType : [$manageType]) ? null : Auth::user()?->cabang_id;
                                    })
                                    ->required()
                                    ->helperText('Pilih cabang untuk customer receipt ini')
                                    ->validationMessages([
                                        'required' => 'Cabang wajib dipilih',
                                    ]),
                            ]),

                        // Invoice Selection Section
                        Section::make('Silahkan Pilih Invoice')
                            ->schema([
                                ViewField::make('invoice_selection_table')
                                    ->label('')
                                    ->view('components.customer-receipt-invoice-table')
                                    ->live()
                                    ->extraAttributes(['wire:key' => 'invoice-table'])
                                    ->viewData(function ($get, $record) {
                                        $customerId = $get('customer_id');

                                        logger()->info('ViewField viewData called', [
                                            'customer_id' => $customerId,
                                            'record_id' => $record ? $record->id : null,
                                        ]);

                                        if (! $customerId) {
                                            logger()->info('No customer_id, returning message');

                                            return [
                                                'invoices' => [],
                                                'selectedInvoices' => [],
                                                'message' => 'Silahkan pilih customer terlebih dahulu',
                                            ];
                                        }

                                        // Get existing invoice receipts data
                                        $existingInvoiceReceipts = [];
                                        if ($record && ! empty($record->invoice_receipts)) {
                                            $existingInvoiceReceipts = is_array($record->invoice_receipts)
                                                ? $record->invoice_receipts
                                                : json_decode($record->invoice_receipts, true) ?? [];
                                        }

                                        // Get existing selected invoices for edit mode
                                        $existingSelectedInvoices = [];
                                        if ($record && ! empty($record->selected_invoices)) {
                                            $existingSelectedInvoices = is_array($record->selected_invoices)
                                                ? $record->selected_invoices
                                                : json_decode($record->selected_invoices, true) ?? [];
                                        }

                                        // Get invoices for selected customer
                                        logger()->info('Building invoice query for customer', ['customer_id' => $customerId]);

                                        // Query invoices that are from SaleOrder for this customer
                                        // Use join instead of whereHas to avoid polymorphic relation issues
                                        $invoicesQuery = Invoice::withoutGlobalScope('App\Models\Scopes\CabangScope')
                                            ->where('invoices.from_model_type', 'App\Models\SaleOrder')
                                            ->join('sale_orders', function ($join) use ($customerId) {
                                                $join->on('invoices.from_model_id', '=', 'sale_orders.id')
                                                    ->where('sale_orders.customer_id', '=', $customerId)
                                                    ->whereIn('sale_orders.status', ['confirmed', 'received', 'completed']) // Only invoiceable orders
                                                    ->whereNull('sale_orders.deleted_at');
                                            })
                                            ->select('invoices.*') // Select only invoice columns to avoid conflicts
                                            ->distinct(); // Ensure no duplicates

                                        logger()->info('Invoice query built', [
                                            'sql' => $invoicesQuery->toSql(),
                                            'bindings' => $invoicesQuery->getBindings(),
                                        ]);

                                        $invoices = $invoicesQuery->get()
                                            ->load(['accountReceivable']) // Load relations separately to avoid conflicts
                                            ->map(function ($invoice) use ($existingInvoiceReceipts, $existingSelectedInvoices) {
                                                $receiptAmount = (float) ($existingInvoiceReceipts[$invoice->id] ?? 0);
                                                $remaining = self::resolveInvoiceRemainingAmount($invoice);

                                                if (in_array($invoice->id, $existingSelectedInvoices, true) && $receiptAmount > 0) {
                                                    $remaining += $receiptAmount;
                                                }

                                                $balance = $receiptAmount > 0 ? ($remaining - $receiptAmount) : '';

                                                return [
                                                    'id' => $invoice->id,
                                                    'invoice_number' => $invoice->invoice_number,
                                                    'customer_name' => $invoice->customer_name_display,
                                                    'cabang_id' => $invoice->cabang_id,
                                                    'total' => $invoice->total,
                                                    'remaining' => $remaining,
                                                    'receipt' => $receiptAmount > 0 ? $receiptAmount : '',
                                                    'balance' => $balance,
                                                    'payment_balance' => '',
                                                    'adjustment_description' => '',
                                                ];
                                            });

                                        logger()->info('Invoices query result', [
                                            'count' => $invoices->count(),
                                            'invoice_ids' => $invoices->pluck('id')->toArray(),
                                            'customer_id' => $customerId,
                                        ]);

                                        $invoicesArray = $invoices->toArray();

                                        $message = $invoices->isEmpty()
                                            ? 'Invoice customer belum ada'
                                            : '';

                                        $selectedInvoices = $get('selected_invoices') ?? [];

                                        // Ensure selectedInvoices is always an array
                                        if (is_string($selectedInvoices)) {
                                            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
                                        }
                                        if (! is_array($selectedInvoices)) {
                                            $selectedInvoices = [];
                                        }

                                        return [
                                            'invoices' => $invoicesArray,
                                            'selectedInvoices' => $selectedInvoices,
                                            'message' => $message,
                                        ];
                                    })
                                    ->visible(fn($get) => ! empty($get('customer_id'))),

                                Hidden::make('selected_invoices')
                                    ->default('[]')
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($set, $state) {
                                        $selectedInvoices = $state;

                                        if (is_string($selectedInvoices)) {
                                            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
                                        }

                                        if (! is_array($selectedInvoices) || empty($selectedInvoices)) {
                                            return;
                                        }

                                            $lastSelectedInvoiceId = (int) end($selectedInvoices);
                                        $invoiceCabangId = Invoice::withoutGlobalScope('App\Models\Scopes\CabangScope')
                                                ->whereKey($lastSelectedInvoiceId)
                                            ->value('cabang_id');

                                        if ($invoiceCabangId) {
                                            $set('cabang_id', $invoiceCabangId);
                                        }
                                    })
                                    ->extraAttributes([
                                        'wire:model' => 'data.selected_invoices',
                                        'data-field' => 'selected_invoices',
                                        'style' => 'font-family: monospace; font-size: 12px;',
                                    ])
                                    ->helperText('Invoice IDs yang dipilih (diupdate otomatis oleh JavaScript)'),

                                Hidden::make('invoice_receipts')
                                    ->default('{}')
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->extraAttributes([
                                        'wire:model' => 'data.invoice_receipts',
                                        'data-field' => 'invoice_receipts',
                                        'style' => 'font-family: monospace; font-size: 12px;',
                                    ])
                                    ->helperText('Data pembayaran per invoice (diupdate otomatis oleh JavaScript)'),

                                ViewField::make('javascript_init_main')
                                    ->view('components.customer-receipt-javascript-init')
                                    ->dehydrated(false),
                            ]),

                        // Notes and Payment Method Section
                        Section::make()
                            ->columns(2)
                            ->schema([
                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options(static::getPaymentMethodOptions())
                                    ->default('Cash')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($set, $state) {
                                        $set('coa_id', static::getDefaultCoaIdByPaymentMethod($state));
                                    }),

                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(3)
                                    ->columnSpan(1),
                            ]),

                        // Payment Details Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('ntpn')
                                    ->label('NTPN')
                                    ->maxLength(255)
                                    ->hidden()
                                    ->dehydrated(false),

                                TextInput::make('total_payment')
                                    ->label('Total Pembayaran')
                                    ->required()
                                    ->indonesianMoney()
                                    ->reactive()
                                    ->disabled(false) // Allow JavaScript to update this field
                                    ->extraAttributes([
                                        'class' => 'auto-calculated-field',
                                        'data-field' => 'total_payment',
                                        'style' => 'background-color: #f9fafb;', // Light gray background to indicate it's auto-calculated
                                    ])
                                    ->helperText('Total ini mengikuti jumlah receipt yang diisi manual untuk invoice yang dipilih')
                                    ->validationMessages([
                                        'required' => 'Total pembayaran wajib diisi',
                                        'numeric' => 'Total pembayaran harus berupa angka',
                                    ]),

                                Select::make('coa_id')
                                    ->label('COA')
                                    ->native(false)
                                    ->preload()
                                    ->searchable(['code', 'name'])
                                    ->reactive()
                                    ->default(function ($get) {
                                        return static::getDefaultCoaIdByPaymentMethod($get('payment_method') ?: 'Cash');
                                    })
                                    ->afterStateHydrated(function ($set, $get, $state) {
                                        if (blank($state)) {
                                            $set('coa_id', static::getDefaultCoaIdByPaymentMethod($get('payment_method') ?: 'Cash'));
                                        }
                                    })
                                    ->options(function ($get) {
                                        return static::getCoaOptionsByPaymentMethod($get('payment_method'));
                                    })
                                    ->extraAttributes([
                                        'id' => 'main-coa-field',
                                    ])
                                    ->validationMessages([
                                        'required' => 'COA belum dipilih',
                                    ])
                                    ->helperText('Daftar COA menyesuaikan metode pembayaran, namun tetap bisa dipilih manual.')
                                    ->required(),
                            ]),

                        // Hidden fields for backward compatibility
                        Hidden::make('invoice_id')
                            ->dehydrated(true),
                        Hidden::make('status')
                            ->default('Draft')
                            ->dehydrated(true),
                        Hidden::make('payment_adjustment')
                            ->default(0)
                            ->dehydrated(true),
                        Hidden::make('diskon')
                            ->default(0)
                            ->dehydrated(true),

                        // Keep repeater for compatibility but make it expanded by default
                        Section::make('Detail Payment Items')
                            ->description('Items pembayaran akan dibuat otomatis berdasarkan invoice yang dipilih di atas')
                            ->schema([
                                Placeholder::make('payment_items_info')
                                    ->label('')
                                    ->content('Detail pembayaran per invoice akan ditampilkan setelah Customer Receipt disimpan. Anda hanya perlu memilih invoice di tabel di atas dan sistem akan membuat detail pembayaran secara otomatis.')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(false),
                    ]),
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

                TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date()
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge(),

                TextColumn::make('total_payment')
                    ->label('Total Payment')
                    ->rupiah()
                    ->sortable(),

                TextColumn::make('payment_count')
                    ->label('Invoice Count')
                    ->getStateUsing(function ($record) {
                        return $record->customerReceiptItem()->count() . ' invoice';
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('remaining_balance')
                    ->label('AR Status')
                    ->getStateUsing(function ($record) {
                        $items = $record->customerReceiptItem;
                        if ($items->isEmpty()) {
                            return 'No Items';
                        }

                        $totalRemaining = 0;
                        $allPaid = true;

                        foreach ($items as $item) {
                            $ar = \App\Models\AccountReceivable::where('invoice_id', $item->invoice_id)->first();
                            if ($ar) {
                                $totalRemaining += $ar->remaining;
                                if ($ar->remaining > 0) {
                                    $allPaid = false;
                                }
                            }
                        }

                        if ($allPaid) {
                            return 'Fully Paid';
                        } else {
                            return 'Rp ' . number_format($totalRemaining, 0, ',', '.') . ' remaining';
                        }
                    })
                    ->badge()
                    ->color(function ($state) {
                        if (str_contains($state, 'Fully Paid')) {
                            return 'success';
                        } elseif (str_contains($state, 'remaining')) {
                            return 'warning';
                        }

                        return 'gray';
                    }),

                TextColumn::make('ntpn')
                    ->label('NTPN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->placeholder('Not set'),

                TextColumn::make('coa.name')
                    ->label('COA')
                    ->formatStateUsing(function ($state, $record) {
                        $coa = $record->coa;

                        return $coa ? "({$coa->code}) {$coa->name}" : '-';
                    })
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'secondary' => 'Draft',
                        'warning' => 'Partial',
                        'success' => 'Paid',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options(static::getPaymentMethodOptions()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Draft' => 'Draft',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Customer Receipt (Penerimaan Pembayaran Pelanggan)</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Customer Receipt adalah record penerimaan pembayaran dari pelanggan untuk melunasi invoice penjualan yang telah diterbitkan.</li>' .
                    '<li><strong>Komponen Utama:</strong> <em>Customer</em> (pelanggan pembayar), <em>Invoice(s)</em> (invoice yang dibayar - bisa multiple), <em>Payment Date</em> (tanggal pembayaran), <em>Total Payment</em> (total nominal), dan <em>COA</em> penerimaan pembayaran.</li>' .
                    '<li><strong>Multiple Invoices:</strong> Satu customer receipt dapat digunakan untuk membayar beberapa invoice sekaligus. Sistem akan otomatis mengalokasikan pembayaran ke masing-masing invoice.</li>' .
                    '<li><strong>Payment Allocation:</strong> Pembayaran dialokasikan ke invoice berdasarkan urutan tanggal invoice (FIFO - First In First Out) atau dapat diatur manual per item invoice.</li>' .
                    '<li><strong>Validasi:</strong> <em>Invoice Validation</em> - memastikan invoice masih outstanding. <em>Amount Check</em> - total payment tidak melebihi total outstanding invoice. <em>Customer Match</em> - invoice harus milik customer yang sama.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan <em>Invoice</em> (pelunasan), <em>Account Receivable</em> (pengurangan piutang), <em>Journal Entry</em> (otomatis buat jurnal), dan <em>Cash/Bank Account</em> (penambahan saldo).</li>' .
                    '<li><strong>Actions:</strong> <em>View</em> (lihat detail receipt), <em>Edit</em> (ubah receipt), <em>Delete</em> (hapus receipt).</li>' .
                    '<li><strong>Permissions:</strong> <em>view any customer receipt</em>, <em>create customer receipt</em>, <em>update customer receipt</em>, <em>delete customer receipt</em>, <em>restore customer receipt</em>, <em>force-delete customer receipt</em>.</li>' .
                    '<li><strong>Journal Impact:</strong> Otomatis membuat journal entry dengan debit Cash/Bank Account dan credit Account Receivable. Overpayment akan dicatat sebagai customer deposit.</li>' .
                    '<li><strong>Reporting:</strong> Menyediakan data untuk accounts receivable aging, cash receipt journal, dan customer payment history tracking.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'customer',
                'invoice',
                'coa',
                'customerReceiptItem.invoice',
            ]);
    }

    protected static function getReceiptItemsForDisplay(CustomerReceipt $record): Collection
    {
        return CustomerReceiptItem::withoutGlobalScopes()
            ->with('invoice')
            ->where('customer_receipt_id', $record->id)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
    }

    protected static function resolveInvoiceReceipts(CustomerReceipt $record): array
    {
        $invoiceReceipts = $record->invoice_receipts ?? [];

        if (is_string($invoiceReceipts)) {
            $invoiceReceipts = json_decode($invoiceReceipts, true) ?? [];
        }

        return is_array($invoiceReceipts) ? $invoiceReceipts : [];
    }

    protected static function resolveSelectedInvoiceIds(CustomerReceipt $record): array
    {
        $selectedInvoices = $record->selected_invoices ?? [];

        if (is_string($selectedInvoices)) {
            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
        }

        return array_values(array_filter(array_map('intval', is_array($selectedInvoices) ? $selectedInvoices : [])));
    }

    protected static function getReceiptInvoiceIds(CustomerReceipt $record): Collection
    {
        $invoiceIds = collect(static::resolveSelectedInvoiceIds($record));

        if (! empty($record->invoice_id)) {
            $invoiceIds->push((int) $record->invoice_id);
        }

        $invoiceIds = $invoiceIds->merge(static::getReceiptItemsForDisplay($record)->pluck('invoice_id'));

        $invoiceReceipts = static::resolveInvoiceReceipts($record);
        if (! empty($invoiceReceipts)) {
            $invoiceIds = $invoiceIds->merge(array_map('intval', array_keys($invoiceReceipts)));
        }

        return $invoiceIds
            ->filter()
            ->map(fn ($invoiceId) => (int) $invoiceId)
            ->unique()
            ->values();
    }

    protected static function normalizeAccountReceivableStatus(?string $status): string
    {
        $normalizedStatus = strtolower(trim((string) $status));

        return match ($normalizedStatus) {
            'lunas', 'paid', 'fully paid', 'settled' => PaymentStatus::PAID->value,
            'belum lunas', 'unpaid', 'partial', 'outstanding' => PaymentStatus::UNPAID->value,
            default => $status ?: '-',
        };
    }

    protected static function isPaidAccountReceivableStatus(?string $status): bool
    {
        return static::normalizeAccountReceivableStatus($status) === PaymentStatus::PAID->value;
    }

    protected static function getReceiptAccountReceivableSummary(CustomerReceipt $record): array
    {
        $summaries = [];

        foreach (static::getReceiptInvoiceIds($record) as $invoiceId) {
            $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
            $items = static::getReceiptItemsForDisplay($record)->where('invoice_id', $invoiceId);
            $invoiceReceipts = static::resolveInvoiceReceipts($record);
            $fallbackPayment = (float) ($invoiceReceipts[$invoiceId] ?? $items->sum('amount'));

            $accountReceivable = AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceId)->first();

            $invoiceTotal = (float) ($accountReceivable?->total ?? $invoice?->total ?? $fallbackPayment);
            $totalPaid = (float) ($accountReceivable?->paid ?? $fallbackPayment);
            $remaining = (float) ($accountReceivable?->remaining ?? max(0, $invoiceTotal - $totalPaid));
            $status = static::normalizeAccountReceivableStatus($accountReceivable?->status ?? ($remaining <= 0 ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value));

            $summaries[] = [
                'invoice_number' => $invoice?->invoice_number ?? ('#' . $invoiceId),
                'invoice_total' => $invoiceTotal,
                'total_paid' => $totalPaid,
                'remaining' => $remaining,
                'percentage' => $invoiceTotal > 0 ? min(100, ($totalPaid / $invoiceTotal) * 100) : 0,
                'status' => $status,
                'this_payment' => $fallbackPayment,
            ];
        }

        return $summaries;
    }

    protected static function getReceiptPaymentHistory(CustomerReceipt $record): Collection
    {
        return static::getReceiptInvoiceIds($record)->map(function (int $invoiceId) use ($record) {
            $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
            $payments = CustomerReceiptItem::withoutGlobalScopes()
                ->with(['customerReceipt'])
                ->where('invoice_id', $invoiceId)
                ->orderBy('payment_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            if ($payments->isEmpty()) {
                $invoiceReceipts = static::resolveInvoiceReceipts($record);
                $fallbackAmount = (float) ($invoiceReceipts[$invoiceId] ?? 0);

                if ($fallbackAmount > 0) {
                    $payments = collect([
                        (object) [
                            'customer_receipt_id' => $record->id,
                            'payment_date' => $record->payment_date,
                            'method' => $record->payment_method,
                            'amount' => $fallbackAmount,
                        ],
                    ]);
                }
            }

            return [
                'invoice_number' => $invoice?->invoice_number ?? ('#' . $invoiceId),
                'payments' => $payments,
                'total_payments' => (float) $payments->sum('amount'),
                'current_receipt_id' => $record->id,
            ];
        });
    }

    protected static function getReceiptJournalEntries(CustomerReceipt $record): Collection
    {
        $itemIds = static::getReceiptItemsForDisplay($record)->pluck('id')->filter()->values();

        return JournalEntry::withoutGlobalScopes()
            ->with('coa')
            ->where(function ($query) use ($record, $itemIds) {
                $query->where('source_type', CustomerReceipt::class)
                    ->where('source_id', $record->id);

                if ($itemIds->isNotEmpty()) {
                    $query->orWhere(function ($itemQuery) use ($itemIds) {
                        $itemQuery->where('source_type', CustomerReceiptItem::class)
                            ->whereIn('source_id', $itemIds->all());
                    });
                }
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    private static function formatMoneyState($value): string
    {
        return number_format((float) MoneyHelper::parse($value ?? 0), 0, ',', '.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Informasi Customer Receipt')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer'),
                        TextEntry::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->date(),
                        TextEntry::make('total_payment')
                            ->label('Total Pembayaran')
                            ->rupiah(),
                        TextEntry::make('ntpn')
                            ->label('NTPN')
                            ->placeholder('Not set')
                            ->hidden()
                            ->copyable(),
                        TextEntry::make('coa.name')
                            ->label('Chart of Account')
                            ->formatStateUsing(function ($state, $record) {
                                $coa = $record->coa;

                                return $coa ? "({$coa->code}) {$coa->name}" : 'Not set';
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Draft' => 'gray',
                                'Partial' => 'warning',
                                'Paid' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                InfoSection::make('Detail Pembayaran per Invoice')
                    ->schema([
                        RepeatableEntry::make('customerReceiptItem')
                            ->label('')
                            ->schema([
                                TextEntry::make('invoice.invoice_number')
                                    ->label('No. Invoice')
                                    ->placeholder('No invoice linked'),
                                TextEntry::make('amount')
                                    ->label('Jumlah Pembayaran')
                                    ->rupiah(),
                                TextEntry::make('method')
                                    ->label('Metode')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'Cash' => 'success',
                                        'Bank Transfer' => 'info',
                                        'Credit' => 'warning',
                                        'Deposit' => 'primary',
                                        default => 'gray',
                                    }),
                                TextEntry::make('payment_date')
                                    ->label('Tanggal')
                                    ->date(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->customerReceiptItem()->exists()),

                InfoSection::make('Status Account Receivable')
                    ->schema([
                        TextEntry::make('payment_summary')
                            ->label('Ringkasan Pembayaran')
                            ->columnSpanFull()
                            ->state(function ($record) {
                                $summaryData = static::getReceiptAccountReceivableSummary($record);

                                if (empty($summaryData)) {
                                    return 'Tidak ada pembayaran tercatat';
                                }

                                $html = '';
                                foreach ($summaryData as $data) {
                                    $statusColor = static::isPaidAccountReceivableStatus($data['status']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                    $progressWidth = min(100, $data['percentage']);

                                    $html .= '<div class="w-full min-w-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm mb-4">';
                                    $html .= '<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between mb-4">';
                                    $html .= '<h4 class="text-base font-semibold text-gray-900">' . $data['invoice_number'] . '</h4>';
                                    $html .= '<span class="w-fit px-2 py-1 text-xs font-semibold rounded-full ' . $statusColor . '">' . $data['status'] . '</span>';
                                    $html .= '</div>';

                                    $html .= '<div class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4 mb-4">';
                                    $html .= '<div class="min-w-0">';
                                    $html .= '<span class="text-gray-600">Total Invoice:</span>';
                                    $html .= '<div class="mt-1 font-semibold text-lg">Rp ' . number_format($data['invoice_total'], 0, ',', '.') . '</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="min-w-0">';
                                    $html .= '<span class="text-gray-600">Pembayaran Ini:</span>';
                                    $html .= '<div class="mt-1 font-semibold text-lg text-blue-600">Rp ' . number_format($data['this_payment'], 0, ',', '.') . '</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="min-w-0">';
                                    $html .= '<span class="text-gray-600">Total Sudah Dibayar:</span>';
                                    $html .= '<div class="mt-1 font-semibold text-lg text-green-600">Rp ' . number_format($data['total_paid'], 0, ',', '.') . '</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="min-w-0">';
                                    $html .= '<span class="text-gray-600">Sisa Pembayaran:</span>';
                                    $html .= '<div class="mt-1 font-semibold text-lg text-red-600">Rp ' . number_format($data['remaining'], 0, ',', '.') . '</div>';
                                    $html .= '</div>';
                                    $html .= '</div>';

                                    // Progress Bar
                                    $html .= '<div class="space-y-1">';
                                    $html .= '<div class="flex justify-between text-xs text-gray-600">';
                                    $html .= '<span>Progress Pembayaran</span>';
                                    $html .= '<span>' . number_format($data['percentage'], 1) . '%</span>';
                                    $html .= '</div>';
                                    $html .= '<div class="h-3 w-full rounded-full bg-gray-200 overflow-hidden">';
                                    $html .= '<div class="h-3 rounded-full bg-blue-500 transition-all duration-300" style="width: ' . $progressWidth . '%"></div>';
                                    $html .= '</div>';
                                    $html .= '</div>';

                                    $html .= '</div>';
                                }

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => static::getReceiptInvoiceIds($record)->isNotEmpty()),

                InfoSection::make('History Pembayaran Invoice')
                    ->schema([
                        TextEntry::make('payment_history')
                            ->label('Riwayat Semua Pembayaran')
                            ->columnSpanFull()
                            ->state(function ($record) {
                                $invoiceHistories = static::getReceiptPaymentHistory($record);

                                if ($invoiceHistories->isEmpty()) {
                                    return 'Tidak ada pembayaran';
                                }

                                $html = '';
                                foreach ($invoiceHistories as $history) {
                                    $html .= '<div class="w-full min-w-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm mb-4">';
                                    $html .= '<h4 class="mb-4 text-base font-semibold text-gray-900">Invoice: ' . e($history['invoice_number']) . '</h4>';

                                    if ($history['payments']->isNotEmpty()) {
                                        $html .= '<div class="space-y-3">';

                                        foreach ($history['payments'] as $payment) {
                                            $isCurrentPayment = (int) $payment->customer_receipt_id === (int) $history['current_receipt_id'];
                                            $bgColor = $isCurrentPayment ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200';
                                            $textColor = $isCurrentPayment ? 'text-blue-900' : 'text-gray-900';
                                            $badge = $isCurrentPayment ? '<span class="ml-2 px-2 py-1 text-xs bg-blue-500 text-white rounded-full">Pembayaran Ini</span>' : '';

                                            $html .= '<div class="grid grid-cols-1 gap-3 rounded-xl border p-4 ' . $bgColor . ' sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">';
                                            $html .= '<div class="' . $textColor . '">';
                                            $html .= '<div class="font-medium">Receipt #' . e((string) ($payment->customer_receipt_id ?: 'N/A')) . $badge . '</div>';
                                            $html .= '<div class="text-sm">Tanggal: ' . e(date('d M Y', strtotime((string) $payment->payment_date))) . '</div>';
                                            $html .= '<div class="text-sm">Metode: ' . e((string) $payment->method) . '</div>';
                                            $html .= '</div>';
                                            $html .= '<div class="text-left sm:text-right ' . $textColor . '">';
                                            $html .= '<div class="text-base font-semibold sm:text-lg">Rp ' . number_format((float) $payment->amount, 0, ',', '.') . '</div>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                        }

                                        $html .= '<div class="mt-4 border-t border-gray-200 pt-4">';
                                        $html .= '<div class="flex flex-col gap-1 font-semibold text-gray-900 sm:flex-row sm:items-center sm:justify-between">';
                                        $html .= '<span>Total Pembayaran (' . $history['payments']->count() . ' transaksi):</span>';
                                        $html .= '<span class="text-lg">Rp ' . number_format($history['total_payments'], 0, ',', '.') . '</span>';
                                        $html .= '</div>';
                                        $html .= '</div>';

                                        $html .= '</div>';
                                    } else {
                                        $html .= '<p class="text-gray-500 italic">Belum ada pembayaran tercatat</p>';
                                    }

                                    $html .= '</div>';
                                }

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => static::getReceiptInvoiceIds($record)->isNotEmpty()),

                InfoSection::make('Journal Entries')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('journal_entries_display')
                            ->label('Jurnal Akuntansi')
                            ->columnSpanFull()
                            ->state(function ($record) {
                                $entries = static::getReceiptJournalEntries($record);

                                if ($entries->isEmpty()) {
                                    return '<p class="text-gray-400 italic text-sm">Belum ada journal entry tercatat untuk receipt ini. <a href="/admin/journal-entries" class="text-primary-600 underline">Lihat semua jurnal →</a></p>';
                                }

                                $html = '<div class="w-full overflow-x-auto">';
                                $html .= '<table class="w-full min-w-full border-collapse text-sm">';
                                $html .= '<thead><tr class="bg-gray-100 text-gray-700">';
                                $html .= '<th class="border border-gray-200 px-4 py-3 text-left">Tanggal</th>';
                                $html .= '<th class="border border-gray-200 px-4 py-3 text-left">Akun</th>';
                                $html .= '<th class="border border-gray-200 px-4 py-3 text-left">Keterangan</th>';
                                $html .= '<th class="border border-gray-200 px-4 py-3 text-right">Debit (Rp)</th>';
                                $html .= '<th class="border border-gray-200 px-4 py-3 text-right">Kredit (Rp)</th>';
                                $html .= '</tr></thead><tbody>';

                                $totalDebit = 0;
                                $totalCredit = 0;
                                foreach ($entries as $entry) {
                                    $accountCode = $entry->coa?->code ?? '-';
                                    $accountName = $entry->coa?->name ?? '-';
                                    $debit = (float) ($entry->debit ?? 0);
                                    $credit = (float) ($entry->credit ?? 0);
                                    $totalDebit += $debit;
                                    $totalCredit += $credit;

                                    $html .= '<tr class="align-top hover:bg-gray-50">';
                                    $html .= '<td class="border border-gray-200 px-4 py-3 whitespace-nowrap">' . ($entry->date ? \Carbon\Carbon::parse($entry->date)->format('d M Y') : '-') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-3 font-medium">' . $accountCode . ' — ' . $accountName . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-3 text-gray-600">' . e($entry->description ?? '') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-3 text-right">' . ($debit > 0 ? 'Rp ' . number_format($debit, 0, ',', '.') : '-') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-3 text-right">' . ($credit > 0 ? 'Rp ' . number_format($credit, 0, ',', '.') : '-') . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '<tr class="bg-gray-50 font-semibold">';
                                $html .= '<td colspan="3" class="border border-gray-200 px-4 py-3 text-right">Total</td>';
                                $html .= '<td class="border border-gray-200 px-4 py-3 text-right">Rp ' . number_format($totalDebit, 0, ',', '.') . '</td>';
                                $html .= '<td class="border border-gray-200 px-4 py-3 text-right">Rp ' . number_format($totalCredit, 0, ',', '.') . '</td>';
                                $html .= '</tr>';

                                $html .= '</tbody></table></div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\CustomerReceiptResource\RelationManagers\CustomerReceiptItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerReceipts::route('/'),
            'create' => Pages\CreateCustomerReceipt::route('/create'),
            'view' => ViewCustomerReceipt::route('/{record}'),
            'edit' => Pages\EditCustomerReceipt::route('/{record}/edit'),
        ];
    }
}
