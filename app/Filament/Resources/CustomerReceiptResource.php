<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerReceiptResource\Pages;
use App\Filament\Resources\CustomerReceiptResource\Pages\ViewCustomerReceipt;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Deposit;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\RepeatableEntry;
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

class CustomerReceiptResource extends Resource
{
    protected static ?string $model = CustomerReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance - Pembayaran';

    protected static ?int $navigationSort = 5;

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
                                        'required' => 'Customer belum dipilih'
                                    ])
                                    ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                                        return "({$customer->code}) {$customer->name}";
                                    })
                                    ->relationship('customer', 'name')
                                    ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                        $set('selected_invoices', []);
                                        $set('total_payment', 0);
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
                                        'required' => 'Tanggal pembayaran wajib diisi'
                                    ]),
                                Select::make('cabang_id')
                                    ->label('Cabang')
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
                                        'required' => 'Cabang wajib dipilih'
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
                                            'record_id' => $record ? $record->id : null
                                        ]);
                                        
                                        if (!$customerId) {
                                            logger()->info('No customer_id, returning message');
                                            return [
                                                'invoices' => [], 
                                                'selectedInvoices' => [],
                                                'message' => 'Silahkan pilih customer terlebih dahulu'
                                            ];
                                        }

                                        // Get existing invoice receipts data
                                        $existingInvoiceReceipts = [];
                                        if ($record && !empty($record->invoice_receipts)) {
                                            $existingInvoiceReceipts = is_array($record->invoice_receipts) 
                                                ? $record->invoice_receipts 
                                                : json_decode($record->invoice_receipts, true) ?? [];
                                        }

                                        // Get existing selected invoices for edit mode
                                        $existingSelectedInvoices = [];
                                        if ($record && !empty($record->selected_invoices)) {
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
                                            ->join('sale_orders', function($join) use ($customerId) {
                                                $join->on('invoices.from_model_id', '=', 'sale_orders.id')
                                                     ->where('sale_orders.customer_id', '=', $customerId)
                                                     ->whereIn('sale_orders.status', ['confirmed', 'received', 'completed']) // Only invoiceable orders
                                                     ->whereNull('sale_orders.deleted_at');
                                            })
                                            ->whereHas('accountReceivable', function ($query) {
                                                $query->where('remaining', '>', 0);
                                            })
                                            ->select('invoices.*') // Select only invoice columns to avoid conflicts
                                            ->distinct(); // Ensure no duplicates
                                        
                                        logger()->info('Invoice query built', [
                                            'sql' => $invoicesQuery->toSql(),
                                            'bindings' => $invoicesQuery->getBindings()
                                        ]);

                                        $invoices = $invoicesQuery->get()
                                            ->load(['accountReceivable']) // Load relations separately to avoid conflicts
                                            ->map(function ($invoice) use ($existingInvoiceReceipts, $existingSelectedInvoices) {
                                                $receiptAmount = $existingInvoiceReceipts[$invoice->id] ?? '';
                                                $accountReceivable = $invoice->accountReceivable;
                                                
                                                // Calculate remaining amount
                                                if ($accountReceivable) {
                                                    $remaining = $accountReceivable->remaining;
                                                    // If this invoice was previously selected and has receipt amount,
                                                    // add back the receipt amount to show original remaining
                                                    if (in_array($invoice->id, $existingSelectedInvoices) && $receiptAmount) {
                                                        $remaining = $remaining + $receiptAmount;
                                                    }
                                                } else {
                                                    $remaining = $invoice->total;
                                                }
                                                
                                                $balance = $receiptAmount ? ($remaining - $receiptAmount) : '';
                                                
                                                return [
                                                    'id' => $invoice->id,
                                                    'invoice_number' => $invoice->invoice_number,
                                                    'customer_name' => $invoice->customer_name_display,
                                                    'total' => $invoice->total,
                                                    'remaining' => $remaining,
                                                    'receipt' => $receiptAmount,
                                                    'balance' => $balance,
                                                    'payment_balance' => '',
                                                    'adjustment_description' => '',
                                                ];
                                            });
                                        
                                        logger()->info('Invoices query result', [
                                            'count' => $invoices->count(),
                                            'invoice_ids' => $invoices->pluck('id')->toArray(),
                                            'customer_id' => $customerId
                                        ]);

                                        // Convert Collection to Array for Blade component
                                        $invoicesArray = $invoices->toArray();

                                        $message = $invoices->isEmpty() 
                                            ? 'Invoice customer belum ada' 
                                            : '';

                                        $selectedInvoices = $get('selected_invoices') ?? [];
                                        
                                        // Ensure selectedInvoices is always an array
                                        if (is_string($selectedInvoices)) {
                                            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
                                        }
                                        if (!is_array($selectedInvoices)) {
                                            $selectedInvoices = [];
                                        }

                                        return [
                                            'invoices' => $invoicesArray,
                                            'selectedInvoices' => $selectedInvoices,
                                            'message' => $message
                                        ];
                                    })
                                    ->visible(fn ($get) => !empty($get('customer_id'))),

                                TextInput::make('selected_invoices')
                                    ->label('Selected Invoices (JSON)')
                                    ->default('[]')
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->readOnly()
                                    ->extraAttributes([
                                        'wire:model' => 'data.selected_invoices',
                                        'data-field' => 'selected_invoices',
                                        'style' => 'font-family: monospace; font-size: 12px;'
                                    ])
                                    ->helperText('Invoice IDs yang dipilih (diupdate otomatis oleh JavaScript)'),
                                    
                                TextInput::make('invoice_receipts')
                                    ->label('Invoice Receipts (JSON)')
                                    ->default('{}')
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->readOnly()
                                    ->extraAttributes([
                                        'wire:model' => 'data.invoice_receipts',
                                        'data-field' => 'invoice_receipts',
                                        'style' => 'font-family: monospace; font-size: 12px;'
                                    ])
                                    ->helperText('Data pembayaran per invoice (diupdate otomatis oleh JavaScript)'),
                                    
                                ViewField::make('javascript_init_main')
                                    ->view('components.customer-receipt-javascript-init')
                                    ->dehydrated(false),
                            ]),

                        // Payment Details Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('ntpn')
                                    ->label('NTPN')
                                    ->maxLength(255)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'NTPN wajib diisi',
                                        'max' => 'NTPN maksimal 255 karakter'
                                    ]),

                                TextInput::make('total_payment')
                                    ->label('Total Pembayaran')
                                    ->required()
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->reactive()
                                    ->disabled(false) // Allow JavaScript to update this field
                                    ->extraAttributes([
                                        'class' => 'auto-calculated-field',
                                        'data-field' => 'total_payment',
                                        'style' => 'background-color: #f9fafb;' // Light gray background to indicate it's auto-calculated
                                    ])
                                    ->helperText('Total ini dihitung otomatis berdasarkan invoice yang dipilih')
                                    ->validationMessages([
                                        'required' => 'Total pembayaran wajib diisi',
                                        'numeric' => 'Total pembayaran harus berupa angka'
                                    ]),

                                Select::make('coa_id')
                                    ->label('COA')
                                    ->preload()
                                    ->searchable(['code', 'name'])
                                    ->reactive()
                                    ->options(function () {
                                        return ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                            return [$coa->id => "({$coa->code}) {$coa->name}"];
                                        });
                                    })
                                    ->extraAttributes([
                                        'id' => 'main-coa-field'
                                    ])
                                    ->validationMessages([
                                        'required' => 'COA belum dipilih'
                                    ])
                                    ->required(fn ($get) => $get('payment_method') !== 'Deposit')
                                    ->hidden(fn ($get) => $get('payment_method') === 'Deposit'),
                            ]),

                        // Notes and Payment Method Section
                        Section::make()
                            ->columns(2)
                            ->schema([
                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(3)
                                    ->columnSpan(1),

                                Radio::make('payment_method')
                                    ->label('Payment Method')
                                    ->inline()
                                    ->required()
                                    ->options([
                                        'Cash' => 'Cash',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'Credit' => 'Credit',
                                        'Deposit' => 'Deposit'
                                    ])
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'required' => 'Metode pembayaran wajib dipilih'
                                    ]),
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
                                    ->columnSpanFull()
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

                TextColumn::make('selected_invoices')
                    ->label('Invoices')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state || empty($state)) {
                            // Fallback to single invoice for backward compatibility
                            return $record->invoice ? $record->invoice->invoice_number : '-';
                        }
                        
                        // Handle if state is string (JSON) or already array
                        $invoiceIds = is_array($state) ? $state : json_decode($state, true);
                        
                        // Safety check - ensure we have a valid array
                        if (!is_array($invoiceIds) || empty($invoiceIds)) {
                            return $record->invoice ? $record->invoice->invoice_number : '-';
                        }
                        
                        $invoices = Invoice::whereIn('id', $invoiceIds)->pluck('invoice_number')->toArray();
                        return implode(', ', $invoices);
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->where(function ($query) use ($search) {
                            // Search in invoice_id for backward compatibility
                            $query->whereHas('invoice', function ($q) use ($search) {
                                $q->where('invoice_number', 'LIKE', '%' . $search . '%');
                            })
                            // Search in selected_invoices JSON
                            ->orWhere(function ($q) use ($search) {
                                $invoiceIds = Invoice::where('invoice_number', 'LIKE', '%' . $search . '%')->pluck('id')->toArray();
                                if (!empty($invoiceIds)) {
                                    foreach ($invoiceIds as $id) {
                                        $q->orWhereJsonContains('selected_invoices', $id);
                                    }
                                }
                            });
                        });
                    }),

                TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_payment')
                    ->label('Total Payment')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('payment_count')
                    ->label('Invoice Count')
                    ->getStateUsing(function ($record) {
                        return $record->customerReceiptItem()->count() . ' invoice';
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(function ($state) {
                        return $state ?? 'Cash';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash' => 'success',
                        'Bank Transfer' => 'info',
                        'Credit' => 'warning',
                        'Deposit' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('remaining_balance')
                    ->label('AR Status')
                    ->getStateUsing(function ($record) {
                        $items = $record->customerReceiptItem;
                        if ($items->isEmpty()) return 'No Items';
                        
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
                    ->toggleable()
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
                    ->label('Metode Pembayaran')
                    ->options([
                        'Cash' => 'Cash',
                        'Bank Transfer' => 'Bank Transfer',
                        'Credit' => 'Credit',
                        'Deposit' => 'Deposit',
                    ]),
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
                ])
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
                            '<li><strong>Metode Pembayaran:</strong> <em>Cash</em> (tunai), <em>Bank Transfer</em> (transfer bank), <em>Check</em> (cek), <em>Giro</em> (bilyet giro), atau <em>Other</em> (metode lainnya).</li>' .
                            '<li><strong>Komponen Utama:</strong> <em>Customer</em> (pelanggan pembayar), <em>Invoice(s)</em> (invoice yang dibayar - bisa multiple), <em>Payment Date</em> (tanggal pembayaran), <em>Total Payment</em> (total nominal), <em>Payment Method</em> (metode pembayaran).</li>' .
                            '<li><strong>Multiple Invoices:</strong> Satu customer receipt dapat digunakan untuk membayar beberapa invoice sekaligus. Sistem akan otomatis mengalokasikan pembayaran ke masing-masing invoice.</li>' .
                            '<li><strong>Payment Allocation:</strong> Pembayaran dialokasikan ke invoice berdasarkan urutan tanggal invoice (FIFO - First In First Out) atau dapat diatur manual per item invoice.</li>' .
                            '<li><strong>Validasi:</strong> <em>Invoice Validation</em> - memastikan invoice masih outstanding. <em>Amount Check</em> - total payment tidak melebihi total outstanding invoice. <em>Customer Match</em> - invoice harus milik customer yang sama.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan <em>Invoice</em> (pelunasan), <em>Account Receivable</em> (pengurangan piutang), <em>Journal Entry</em> (otomatis buat jurnal), <em>Cash/Bank Account</em> (penambahan saldo), dan <em>Deposit</em> (untuk overpayment).</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail receipt), <em>Edit</em> (ubah receipt), <em>Delete</em> (hapus receipt), <em>Print Receipt</em> (cetak bukti pembayaran), <em>Generate Journal</em> (buat jurnal entry).</li>' .
                            '<li><strong>Permissions:</strong> <em>view any customer receipt</em>, <em>create customer receipt</em>, <em>update customer receipt</em>, <em>delete customer receipt</em>, <em>restore customer receipt</em>, <em>force-delete customer receipt</em>.</li>' .
                            '<li><strong>Journal Impact:</strong> Otomatis membuat journal entry dengan debit Cash/Bank Account dan credit Account Receivable. Overpayment akan dicatat sebagai customer deposit.</li>' .
                            '<li><strong>Reporting:</strong> Menyediakan data untuk accounts receivable aging, cash receipt journal, dan customer payment history tracking.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
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
                            ->money('IDR'),
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->formatStateUsing(function ($state) {
                                return $state ?? 'Cash';
                            })
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Cash' => 'success',
                                'Bank Transfer' => 'info',
                                'Credit' => 'warning', 
                                'Deposit' => 'primary',
                                default => 'gray',
                            }),
                        TextEntry::make('ntpn')
                            ->label('NTPN')
                            ->placeholder('Not set')
                            ->copyable(),
                        TextEntry::make('coa.name')
                            ->label('Chart of Account')
                            ->formatStateUsing(function ($state, $record) {
                                $coa = $record->coa;
                                return $coa ? "({$coa->code}) {$coa->name}" : 'Not set';
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
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
                                    ->money('IDR'),
                                TextEntry::make('method')
                                    ->label('Metode')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
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
                    ->visible(fn ($record) => $record->customerReceiptItem->count() > 0),

                InfoSection::make('Status Account Receivable')
                    ->schema([
                        TextEntry::make('payment_summary')
                            ->label('Ringkasan Pembayaran')
                            ->formatStateUsing(function ($state, $record) {
                                $items = $record->customerReceiptItem;
                                if ($items->isEmpty()) return 'Tidak ada pembayaran tercatat';
                                
                                $summaryData = [];
                                
                                foreach ($items as $item) {
                                    if ($item->invoice) {
                                        // Get Account Receivable for this invoice
                                        $ar = \App\Models\AccountReceivable::where('invoice_id', $item->invoice_id)->first();
                                        
                                        if ($ar) {
                                            $percentage = $ar->total > 0 ? ($ar->paid / $ar->total) * 100 : 0;
                                            $summaryData[] = [
                                                'invoice_number' => $item->invoice->invoice_number,
                                                'invoice_total' => $ar->total,
                                                'total_paid' => $ar->paid,
                                                'remaining' => $ar->remaining,
                                                'percentage' => $percentage,
                                                'status' => $ar->status,
                                                'this_payment' => $item->amount
                                            ];
                                        } else {
                                            // If no AR record, show basic info
                                            $summaryData[] = [
                                                'invoice_number' => $item->invoice->invoice_number,
                                                'invoice_total' => $item->invoice->total,
                                                'total_paid' => $item->amount,
                                                'remaining' => $item->invoice->total - $item->amount,
                                                'percentage' => ($item->amount / $item->invoice->total) * 100,
                                                'status' => 'Partial',
                                                'this_payment' => $item->amount
                                            ];
                                        }
                                    }
                                }
                                
                                if (empty($summaryData)) {
                                    return 'Data pembayaran tidak ditemukan';
                                }
                                
                                $html = '';
                                foreach ($summaryData as $data) {
                                    $statusColor = $data['status'] === 'Lunas' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                    $progressWidth = min(100, $data['percentage']);
                                    
                                    $html .= '<div class="border border-gray-200 rounded-lg p-4 mb-3 bg-white">';
                                    $html .= '<div class="flex justify-between items-start mb-3">';
                                    $html .= '<h4 class="font-semibold text-gray-900">' . $data['invoice_number'] . '</h4>';
                                    $html .= '<span class="px-2 py-1 text-xs font-semibold rounded-full ' . $statusColor . '">' . $data['status'] . '</span>';
                                    $html .= '</div>';
                                    
                                    $html .= '<div class="grid grid-cols-2 gap-4 text-sm mb-3">';
                                    $html .= '<div>';
                                    $html .= '<span class="text-gray-600">Total Invoice:</span><br>';
                                    $html .= '<span class="font-semibold text-lg">Rp ' . number_format($data['invoice_total'], 0, ',', '.') . '</span>';
                                    $html .= '</div>';
                                    $html .= '<div>';
                                    $html .= '<span class="text-gray-600">Pembayaran Ini:</span><br>';
                                    $html .= '<span class="font-semibold text-lg text-blue-600">Rp ' . number_format($data['this_payment'], 0, ',', '.') . '</span>';
                                    $html .= '</div>';
                                    $html .= '<div>';
                                    $html .= '<span class="text-gray-600">Total Sudah Dibayar:</span><br>';
                                    $html .= '<span class="font-semibold text-lg text-green-600">Rp ' . number_format($data['total_paid'], 0, ',', '.') . '</span>';
                                    $html .= '</div>';
                                    $html .= '<div>';
                                    $html .= '<span class="text-gray-600">Sisa Pembayaran:</span><br>';
                                    $html .= '<span class="font-semibold text-lg text-red-600">Rp ' . number_format($data['remaining'], 0, ',', '.') . '</span>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    
                                    // Progress Bar
                                    $html .= '<div class="mb-2">';
                                    $html .= '<div class="flex justify-between text-xs text-gray-600 mb-1">';
                                    $html .= '<span>Progress Pembayaran</span>';
                                    $html .= '<span>' . number_format($data['percentage'], 1) . '%</span>';
                                    $html .= '</div>';
                                    $html .= '<div class="w-full bg-gray-200 rounded-full h-3">';
                                    $html .= '<div class="bg-blue-500 h-3 rounded-full transition-all duration-300" style="width: ' . $progressWidth . '%"></div>';
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
                    ->visible(fn ($record) => $record->customerReceiptItem->count() > 0),

                InfoSection::make('History Pembayaran Invoice')
                    ->schema([
                        TextEntry::make('payment_history')
                            ->label('Riwayat Semua Pembayaran')
                            ->formatStateUsing(function ($state, $record) {
                                $items = $record->customerReceiptItem;
                                if ($items->isEmpty()) return 'Tidak ada pembayaran';
                                
                                $html = '';
                                
                                foreach ($items as $item) {
                                    if ($item->invoice) {
                                        // Get all payments for this invoice
                                        $allPayments = \App\Models\CustomerReceiptItem::with('customerReceipt')
                                            ->where('invoice_id', $item->invoice_id)
                                            ->orderBy('payment_date', 'desc')
                                            ->get();
                                        
                                        $html .= '<div class="border border-gray-200 rounded-lg p-4 mb-4 bg-white">';
                                        $html .= '<h4 class="font-semibold text-gray-900 mb-3">Invoice: ' . $item->invoice->invoice_number . '</h4>';
                                        
                                        if ($allPayments->count() > 0) {
                                            $html .= '<div class="space-y-2">';
                                            
                                            foreach ($allPayments as $payment) {
                                                $isCurrentPayment = $payment->customer_receipt_id == $record->id;
                                                $bgColor = $isCurrentPayment ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200';
                                                $textColor = $isCurrentPayment ? 'text-blue-900' : 'text-gray-900';
                                                $badge = $isCurrentPayment ? '<span class="ml-2 px-2 py-1 text-xs bg-blue-500 text-white rounded-full">Pembayaran Ini</span>' : '';
                                                
                                                $html .= '<div class="flex justify-between items-center p-3 border rounded ' . $bgColor . '">';
                                                $html .= '<div class="' . $textColor . '">';
                                                $html .= '<div class="font-medium">Receipt #' . ($payment->customer_receipt_id ?: 'N/A') . $badge . '</div>';
                                                $html .= '<div class="text-sm">Tanggal: ' . date('d M Y', strtotime($payment->payment_date)) . '</div>';
                                                $html .= '<div class="text-sm">Metode: ' . $payment->method . '</div>';
                                                $html .= '</div>';
                                                $html .= '<div class="text-right ' . $textColor . '">';
                                                $html .= '<div class="font-semibold text-lg">Rp ' . number_format($payment->amount, 0, ',', '.') . '</div>';
                                                $html .= '</div>';
                                                $html .= '</div>';
                                            }
                                            
                                            // Total payments
                                            $totalPayments = $allPayments->sum('amount');
                                            $html .= '<div class="border-t border-gray-200 pt-3 mt-3">';
                                            $html .= '<div class="flex justify-between items-center font-semibold text-gray-900">';
                                            $html .= '<span>Total Pembayaran (' . $allPayments->count() . ' transaksi):</span>';
                                            $html .= '<span class="text-lg">Rp ' . number_format($totalPayments, 0, ',', '.') . '</span>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            
                                            $html .= '</div>';
                                        } else {
                                            $html .= '<p class="text-gray-500 italic">Belum ada pembayaran tercatat</p>';
                                        }
                                        
                                        $html .= '</div>';
                                    }
                                }
                                
                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->visible(fn ($record) => $record->customerReceiptItem->count() > 0),

                InfoSection::make('Informasi Invoice yang Dibayar')
                    ->schema([
                        TextEntry::make('customerReceiptItem')
                            ->label('Invoice yang Dipilih')
                            ->formatStateUsing(function ($state, $record) {
                                $items = $record->customerReceiptItem;
                                if ($items->isEmpty()) return 'Tidak ada';
                                
                                $invoiceNumbers = $items->map(function ($item) {
                                    return $item->invoice ? $item->invoice->invoice_number : 'Invoice tidak ditemukan';
                                });
                                
                                return $invoiceNumbers->join(', ');
                            }),
                        TextEntry::make('customerReceiptItem')
                            ->label('Detail Pembayaran')
                            ->formatStateUsing(function ($state, $record) {
                                $items = $record->customerReceiptItem;
                                if ($items->isEmpty()) return 'Tidak ada data';
                                
                                $details = $items->map(function ($item) {
                                    $invoiceNumber = $item->invoice ? $item->invoice->invoice_number : 'Unknown Invoice';
                                    return "$invoiceNumber: Rp " . number_format($item->amount, 0, ',', '.');
                                });
                                
                                return implode("\n", $details->toArray());
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
