<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPaymentResource\Pages;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\VendorPayment;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
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
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\ActionGroup;

class VendorPaymentResource extends Resource
{
    protected static ?string $model = VendorPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance - Pembayaran';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Vendor Payment')
                    ->schema([
                        // Header Section - Vendor and Payment Date
                        Section::make()
                            ->columns(2)
                            ->schema([
                                Select::make('supplier_id')
                                    ->label('Vendor')
                                    ->options(function () {
                                        return Supplier::all()->mapWithKeys(function ($supplier) {
                                            return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                        })->toArray();
                                    })
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Supplier belum dipilih'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_invoices', []);
                                        $set('total_payment', 0);
                                        $set('payment_details', []);
                                    })
                                    ->required(),

                                DatePicker::make('payment_date')
                                    ->label('Payment Date')
                                    ->required()
                                    ->default(now()),
                            ]),

                        // Invoice Selection Section
                        Section::make('Pilih Invoice')
                            ->schema([
                                Hidden::make('is_processing_invoices')
                                    ->default(false)
                                    ->reactive(),
                                Hidden::make('has_invoices')
                                    ->default(false)
                                    ->reactive(),
                                Hidden::make('force_section_update')
                                    ->default(false)
                                    ->reactive(),
                                CheckboxList::make('selected_invoices')
                                    ->label('Pilih Invoice')
                                    ->options(function ($get, $set) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) {
                                            $set('has_invoices', false);
                                            return [];
                                        }

                                        try {
                                            // Get unpaid/partial invoices for selected supplier
                                            $invoices = Invoice::join('purchase_orders', function($join) use ($supplierId) {
                                                    $join->on('invoices.from_model_id', '=', 'purchase_orders.id')
                                                         ->where('invoices.from_model_type', '=', 'App\Models\PurchaseOrder')
                                                         ->where('purchase_orders.supplier_id', '=', $supplierId); // FIX: Filter by supplier
                                                })
                                                ->whereHas('accountPayable', function ($query) {
                                                    $query->where('remaining', '>', 0);
                                                })
                                                ->with(['accountPayable'])
                                                ->select('invoices.*')
                                                ->get();

                                            $options = [];
                                            foreach ($invoices as $invoice) {
                                                $remaining = $invoice->accountPayable->remaining ?? $invoice->total;
                                                $options[$invoice->id] = "Invoice {$invoice->invoice_number} - Total: Rp " . number_format($invoice->total, 0, ',', '.') . " - Sisa: Rp " . number_format($remaining, 0, ',', '.');
                                            }

                                            $set('has_invoices', !empty($options));
                                            return $options;
                                        } catch (\Exception $e) {
                                            $set('has_invoices', false);
                                            return [];
                                        }
                                    })
                                    ->visible(fn ($get) => !empty($get('supplier_id')))
                                    ->helperText(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) {
                                            return 'Pilih supplier terlebih dahulu untuk melihat invoice yang tersedia.';
                                        }

                                        $hasInvoices = $get('has_invoices');
                                        if ($hasInvoices === false) {
                                            return 'Tidak ada invoice yang tersedia untuk vendor ini. Pastikan vendor memiliki invoice dengan sisa pembayaran.';
                                        }

                                        $selectedInvoices = $get('selected_invoices');
                                        if (empty($selectedInvoices)) {
                                            return 'Pilih invoice yang akan dibayar. Hanya menampilkan invoice dengan sisa pembayaran.';
                                        }

                                        return 'Pilih invoice yang akan dibayar. Hanya menampilkan invoice dengan sisa pembayaran.';
                                    })
                                    ->reactive()
                                    ->live()
                                    ->disabled(fn ($get) => $get('is_processing_invoices'))
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        // Set loading state to show processing indicator
                                        $set('is_processing_invoices', true);

                                        // CheckboxList returns array of selected invoice IDs directly
                                        $invoiceIds = is_array($state) ? array_map('intval', $state) : [];

                                        \Illuminate\Support\Facades\Log::info('CheckboxList afterStateUpdated', [
                                            'selected_invoice_ids' => $invoiceIds,
                                            'supplier_id' => $get('supplier_id'),
                                        ]);

                                        // Calculate total payment based on selected invoices
                                        if (empty($invoiceIds)) {
                                            $set('total_payment', 0);
                                            $set('payment_details', []);
                                            $set('force_section_update', false); // Reset force update
                                            \Illuminate\Support\Facades\Log::info('No invoices selected, resetting totals');
                                            $set('is_processing_invoices', false);
                                            return;
                                        }

                                        try {
                                            $invoices = Invoice::whereIn('id', $invoiceIds)
                                                ->with('accountPayable')
                                                ->get();

                                            \Illuminate\Support\Facades\Log::info('Fetched invoices for payment calculation', [
                                                'invoice_count' => $invoices->count(),
                                                'invoices' => $invoices->map(function ($invoice) {
                                                    return [
                                                        'id' => $invoice->id,
                                                        'number' => $invoice->invoice_number,
                                                        'total' => $invoice->total,
                                                        'remaining' => $invoice->accountPayable->remaining ?? $invoice->total,
                                                    ];
                                                })->toArray(),
                                            ]);

                                            $total = $invoices->sum(function ($invoice) {
                                                return $invoice->accountPayable->remaining ?? $invoice->total;
                                            });

                                            $set('total_payment', $total);

                                            // Set payment details for each selected invoice
                                            $paymentDetails = $invoices->map(function ($invoice) {
                                                return [
                                                    'invoice_id' => $invoice->id,
                                                    'invoice_number' => $invoice->invoice_number,
                                                    'remaining_amount' => $invoice->accountPayable->remaining ?? $invoice->total,
                                                    'payment_amount' => $invoice->accountPayable->remaining ?? $invoice->total,
                                                ];
                                            })->toArray();

                                            $set('payment_details', $paymentDetails);
                                            \Illuminate\Support\Facades\Log::info('Payment details set successfully', [
                                                'total_payment' => $total,
                                                'payment_details_count' => count($paymentDetails),
                                            ]);

                                        } catch (\Exception $e) {
                                            \Illuminate\Support\Facades\Log::error('Error in invoice selection processing', [
                                                'error' => $e->getMessage(),
                                                'trace' => $e->getTraceAsString(),
                                                'invoice_ids' => $invoiceIds,
                                            ]);
                                            $set('total_payment', 0);
                                            $set('payment_details', []);
                                        }

                                        // End loading state and force section re-render
                                        $set('is_processing_invoices', false);
                                        $set('force_section_update', !$get('force_section_update'));
                                    })
                                    ->columns(2)
                                    ->searchable(),
                            ]),

                        // Payment Details per Invoice Section
                        Section::make('Detail Pembayaran per Invoice')
                            ->description(function ($get) {
                                $selected = $get('selected_invoices');
                                $isProcessing = $get('is_processing_invoices');

                                if ($isProcessing) {
                                    return 'Memproses pilihan invoice...';
                                }

                                if (!empty($selected) && (is_array($selected) ? count($selected) > 0 : true)) {
                                    $count = is_array($selected) ? count($selected) : 1;
                                    return "Tentukan jumlah pembayaran untuk {$count} invoice yang dipilih";
                                }
                                return 'Tentukan jumlah pembayaran untuk setiap invoice yang dipilih';
                            })
                            ->visible(function ($get) {
                                $selected = $get('selected_invoices');
                                $forceUpdate = $get('force_section_update'); // Add dependency
                                $isVisible = !empty($selected) && (is_array($selected) ? count($selected) > 0 : true);

                                \Illuminate\Support\Facades\Log::info('Section visibility check', [
                                    'selected_invoices' => $selected,
                                    'is_visible' => $isVisible,
                                    'force_update' => $forceUpdate,
                                    'type' => gettype($selected),
                                    'count' => is_array($selected) ? count($selected) : 'not_array'
                                ]);

                                return $isVisible;
                            })
                            ->reactive()
                            ->live()
                            ->schema([
                                Placeholder::make('processing_invoice_selection')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('
                                        <div class="flex items-center justify-center space-x-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                            <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <div class="text-blue-800 font-medium">
                                                Memproses pilihan invoice, mohon tunggu...
                                            </div>
                                        </div>
                                    '))
                                    ->visible(fn ($get) => $get('is_processing_invoices')),
                                Placeholder::make('loading_payment_details')
                                    ->label('')
                                    ->content('Memproses detail pembayaran...')
                                    ->visible(fn ($get) => !empty($get('selected_invoices')) && empty($get('payment_details')) && !$get('is_processing_invoices')),
                                Repeater::make('payment_details')
                                    ->label('')
                                    ->reactive()
                                    ->visible(fn ($get) => !empty($get('payment_details')) && !$get('is_processing_invoices'))
                                    ->disabled(fn ($get) => $get('is_processing_invoices'))
                                    ->schema([
                                        TextInput::make('invoice_number')
                                            ->label('No. Invoice')
                                            ->readonly()
                                            ->columnSpan(1),
                                        TextInput::make('remaining_amount')
                                            ->label('Sisa Hutang')
                                            ->readonly()
                                            ->indonesianMoney()
                                            ->columnSpan(1),
                                        TextInput::make('payment_amount')
                                            ->label('Jumlah Pembayaran')
                                            ->indonesianMoney()
                                            ->reactive()
                                            ->required()
                                            ->placeholder(function ($get) {
                                                $selectedInvoices = $get('../../selected_invoices');
                                                if (!empty($selectedInvoices)) {
                                                    return 'Masukkan jumlah pembayaran...';
                                                }
                                                return 'Pilih invoice terlebih dahulu';
                                            })
                                            ->validationMessages([
                                                'required' => 'Jumlah pembayaran harus diisi',
                                                'numeric' => 'Jumlah pembayaran harus berupa angka'
                                            ])
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                // Recalculate total payment when any payment amount changes
                                                // Use relative path to access repeater data from within repeater field
                                                $paymentDetails = $get('../../payment_details') ?? [];

                                                $totalPayment = 0;
                                                if (is_array($paymentDetails)) {
                                                    foreach ($paymentDetails as $detail) {
                                                        $amount = 0;
                                                        if (is_array($detail) && isset($detail['payment_amount'])) {
                                                            $amount = \App\Http\Controllers\HelperController::parseIndonesianMoney($detail['payment_amount'] ?? 0);
                                                        } elseif (is_object($detail) && isset($detail->payment_amount)) {
                                                            $amount = \App\Http\Controllers\HelperController::parseIndonesianMoney($detail->payment_amount ?? 0);
                                                        }
                                                        $totalPayment += $amount;
                                                    }
                                                }

                                                $set('../../total_payment', $totalPayment);

                                                \Illuminate\Support\Facades\Log::info('Payment amount updated, recalculating total', [
                                                    'new_payment_amount' => $state,
                                                    'total_payment' => $totalPayment,
                                                    'payment_details_count' => count($paymentDetails),
                                                ]);
                                            })
                                            ->columnSpan(1),
                                        Hidden::make('invoice_id'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->default([])
                                    ->key(fn ($get) => md5(json_encode($get('payment_details') ?? []))),
                            ]),

                        // Payment Details Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('ntpn')
                                    ->label('NTPN')
                                    ->suffixAction(
                                        Action::make('generateNTPN')
                                            ->icon('heroicon-m-arrow-path')
                                            ->tooltip('Generate NTPN')
                                            ->action(function ($set, $get) {
                                                // Generate NTPN format: NTPN + YYYYMMDD + random 6 digits
                                                $date = now()->format('Ymd');
                                                $random = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                                                $ntpn = 'NTPN' . $date . $random;
                                                $set('ntpn', $ntpn);
                                            })
                                    )
                                    ->maxLength(255)
                                    ->required(),

                                Placeholder::make('calculating_total')
                                    ->label('Total Pembayaran')
                                    ->content('Menghitung total pembayaran...')
                                    ->visible(fn ($get) => !empty($get('selected_invoices')) && $get('total_payment') == 0)
                                    ->columnSpan(1),

                                TextInput::make('total_payment')
                                    ->label('Total Pembayaran')
                                    ->required()
                                    ->indonesianMoney()
                                    ->reactive()
                                    ->readOnly() // Make it read-only since it's calculated automatically
                                    ->default(0)
                                    ->visible(fn ($get) => $get('total_payment') > 0)
                                    ->afterStateUpdated(function ($set, $get) {
                                        $selectedInvoices = $get('selected_invoices') ?? [];

                                        // Handle mixed data structure: objects from payment_details + strings
                                        $invoiceIds = [];
                                        if (is_array($selectedInvoices)) {
                                            foreach ($selectedInvoices as $item) {
                                                if (is_numeric($item)) {
                                                    $invoiceIds[] = (int) $item;
                                                } elseif (is_string($item)) {
                                                    $invoiceIds[] = (int) $item;
                                                } elseif (is_array($item) && isset($item['invoice_id'])) {
                                                    $invoiceIds[] = (int) $item['invoice_id'];
                                                } elseif (is_object($item) && isset($item->invoice_id)) {
                                                    $invoiceIds[] = (int) $item->invoice_id;
                                                }
                                            }
                                            $invoiceIds = array_unique($invoiceIds);
                                        }

                                        if (empty($invoiceIds)) {
                                            $set('total_payment', 0);
                                            $set('payment_details', []);
                                            return;
                                        }

                                        try {
                                            $invoices = Invoice::whereIn('id', $invoiceIds)
                                                ->with('accountPayable')
                                                ->get();

                                            $total = $invoices->sum(function ($invoice) {
                                                return $invoice->accountPayable->remaining ?? $invoice->total;
                                            });

                                            $set('total_payment', $total);

                                            // Set payment details for each selected invoice
                                            $paymentDetails = $invoices->map(function ($invoice) {
                                                return [
                                                    'invoice_id' => $invoice->id,
                                                    'invoice_number' => $invoice->invoice_number,
                                                    'remaining_amount' => $invoice->accountPayable->remaining ?? $invoice->total,
                                                    'payment_amount' => $invoice->accountPayable->remaining ?? $invoice->total,
                                                ];
                                            })->toArray();

                                            $set('payment_details', $paymentDetails);
                                        } catch (\Exception $e) {
                                            $set('total_payment', 0);
                                            $set('payment_details', []);
                                        }
                                    })
                                    ->extraAttributes([
                                        'class' => 'auto-calculated-field',
                                        'data-field' => 'total_payment'
                                    ])
                                    ->helperText('Total ini dihitung otomatis berdasarkan invoice yang dipilih'),

                                Select::make('coa_id')
                                    ->label('COA')
                                    ->options(function ($get) {
                                        $paymentMethod = $get('payment_method');

                                        try {
                                            $coas = match($paymentMethod) {
                                                'Cash' => ChartOfAccount::where('code', 'LIKE', '11%')
                                                    ->where(function ($q) {
                                                        $q->where('name', 'LIKE', '%kas%')
                                                          ->orWhere('name', 'LIKE', '%tunai%');
                                                    })
                                                    ->get(),
                                                'Bank Transfer' => ChartOfAccount::where('code', 'LIKE', '11%')
                                                    ->where(function ($q) {
                                                        $q->where('name', 'LIKE', '%bank%')
                                                          ->orWhere('name', 'LIKE', '%rekening%');
                                                    })
                                                    ->get(),
                                                'Credit' => ChartOfAccount::where('code', 'LIKE', '11%')
                                                    ->where('name', 'LIKE', '%piutang%')
                                                    ->get(),
                                                'Deposit' => ChartOfAccount::where('type', 'asset')
                                                    ->where('name', 'LIKE', '%deposit%')
                                                    ->get(),
                                                default => ChartOfAccount::all()
                                            };

                                            $options = [];
                                            foreach ($coas as $coa) {
                                                $options[$coa->id] = "({$coa->code}) {$coa->name}";
                                            }

                                            return $options;
                                        } catch (\Exception $e) {
                                            return [];
                                        }
                                    })
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->extraAttributes([
                                        'id' => 'main-coa-field'
                                    ])
                                    ->helperText('COA akan otomatis ter-select berdasarkan metode pembayaran, namun masih bisa diubah secara manual')
                                    ->validationMessages([
                                        'required' => 'COA belum dipilih'
                                    ])
                                    ->required(),
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
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Auto-select appropriate COA based on payment method
                                        $firstCoa = null;

                                        switch ($state) {
                                            case 'Cash':
                                                $firstCoa = ChartOfAccount::where('code', 'LIKE', '11%')
                                                    ->where(function ($q) {
                                                        $q->where('name', 'LIKE', '%kas%')
                                                          ->orWhere('name', 'LIKE', '%tunai%');
                                                    })
                                                    ->first();
                                                break;
                                            case 'Bank Transfer':
                                                $firstCoa = ChartOfAccount::where('code', 'LIKE', '11%')
                                                    ->where(function ($q) {
                                                        $q->where('name', 'LIKE', '%bank%')
                                                          ->orWhere('name', 'LIKE', '%rekening%');
                                                    })
                                                    ->first();
                                                break;
                                            case 'Credit':
                                                $firstCoa = ChartOfAccount::where('code', 'LIKE', '11%')
                                                    ->where('name', 'LIKE', '%piutang%')
                                                    ->first();
                                                break;
                                            case 'Deposit':
                                                $firstCoa = ChartOfAccount::where('type', 'asset')
                                                    ->where('name', 'LIKE', '%deposit%')
                                                    ->first();
                                                break;
                                        }

                                        if ($firstCoa) {
                                            $set('coa_id', $firstCoa->id);
                                        } else {
                                            $set('coa_id', null);
                                        }
                                    })
                                    ->options([
                                        'Cash' => 'Cash',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'Credit' => 'Credit',
                                        'Deposit' => 'Deposit'
                                    ])
                                    ->columnSpan(1),
                            ]),

                        Section::make('Pajak Impor (Opsional)')
                            ->description('Isi nilai pajak impor ketika pembayaran ini mencakup PPN Impor, PPh 22, atau Bea Masuk. Nilai akan dijurnal saat pembayaran Kas/Bank.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_import_payment')
                                    ->label('Pembayaran mencakup pajak impor?')
                                    ->helperText('Aktifkan bila pembayaran impor melibatkan PPN/PPh/Bea. Biarkan mati jika bukan transaksi impor.')
                                    ->reactive()
                                    ->default(false),
                                TextInput::make('ppn_import_amount')
                                    ->label('PPN Impor')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(fn ($get) => !$get('is_import_payment'))
                                    ->dehydrated()
                                    ->helperText('Masukkan nilai PPN Masukan impor yang akan diakui saat pembayaran'),
                                TextInput::make('pph22_amount')
                                    ->label('PPh 22 Impor')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(fn ($get) => !$get('is_import_payment'))
                                    ->dehydrated()
                                    ->helperText('Opsional: Pajak PPh 22 yang dibayarkan saat impor'),
                                TextInput::make('bea_masuk_amount')
                                    ->label('Bea Masuk')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(fn ($get) => !$get('is_import_payment'))
                                    ->dehydrated()
                                    ->helperText('Opsional: biaya Bea Masuk yang dibayarkan di bea cukai'),
                            ]),

                        // Hidden fields for backward compatibility
                        Hidden::make('status')->default('Draft'),
                        Hidden::make('payment_adjustment')->default(0),
                        Hidden::make('diskon')->default(0),

                        // Wire model fields for JavaScript communication (dehydrated for saving)
                        Hidden::make('selected_invoices')
                            ->reactive()
                            ->dehydrated(true)
                            ->live(onBlur: true),
                        Hidden::make('invoice_receipts')
                            ->reactive()
                            ->dehydrated(true)
                            ->live(onBlur: true),
                    ])
            ]);
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        // For edit mode, set payment_details based on selected_invoices
        if (!empty($data['selected_invoices'])) {
            try {
                // Handle both array and JSON string formats
                $selectedInvoices = $data['selected_invoices'];

                // If it's a JSON string, decode it
                if (is_string($selectedInvoices)) {
                    $selectedInvoices = json_decode($selectedInvoices, true);
                }

                // Ensure it's an array
                if (!is_array($selectedInvoices)) {
                    $selectedInvoices = [];
                }

                if (!empty($selectedInvoices)) {
                    $invoices = Invoice::whereIn('id', $selectedInvoices)
                        ->with('accountPayable')
                        ->get();

                    $paymentDetails = $invoices->map(function ($invoice) {
                        return [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'remaining_amount' => $invoice->accountPayable->remaining ?? $invoice->total,
                            'payment_amount' => $invoice->accountPayable->remaining ?? $invoice->total,
                        ];
                    })->toArray();

                    $data['payment_details'] = $paymentDetails;
                } else {
                    $data['payment_details'] = [];
                }
            } catch (\Exception $e) {
                // If there's an error, just continue without payment_details
                $data['payment_details'] = [];
                $data['selected_invoices'] = [];
            }
        } else {
            $data['payment_details'] = [];
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('supplier', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),

                TextColumn::make('selected_invoices')
                    ->label('Invoices')
                    ->getStateUsing(function ($record) {
                        // Ensure we get the casted array value
                        $selectedInvoices = $record->selected_invoices;

                        if (!$selectedInvoices || empty($selectedInvoices)) {
                            // Fallback to single invoice for backward compatibility
                            return $record->invoice ? $record->invoice->invoice_number : '-';
                        }

                        // Handle both JSON string and array formats
                        $invoiceIds = is_string($selectedInvoices) ? json_decode($selectedInvoices, true) : $selectedInvoices;
                        if (!is_array($invoiceIds)) {
                            return '-';
                        }

                        try {
                            $invoices = Invoice::whereIn('id', $invoiceIds)->pluck('invoice_number')->toArray();
                            return $invoices ? implode(', ', $invoices) : '-';
                        } catch (\Exception $e) {
                            return 'ERROR: ' . $e->getMessage();
                        }
                    }),

                TextColumn::make('payment_date')
                    ->label('Tanggal Pembayaran')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('total_payment')
                    ->label('Total Pembayaran')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash' => 'success',
                        'Bank Transfer' => 'info',
                        'Credit' => 'warning',
                        'Deposit' => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),])
            ],position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListVendorPayments::route('/'),
            'create' => Pages\CreateVendorPayment::route('/create'),
            'edit' => Pages\EditVendorPayment::route('/{record}/edit'),
        ];
    }
}
