<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPaymentResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\VendorPayment;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\View;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

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
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Supplier belum dipilih'
                                    ])
                                    ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                                        return "({$supplier->code}) {$supplier->name}";
                                    })
                                    ->relationship('supplier', 'name')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_invoices', []);
                                        $set('total_payment', 0);
                                        $set('payment_adjustment', 0);
                                    })
                                    ->required(),

                                DatePicker::make('payment_date')
                                    ->label('Payment Date')
                                    ->required()
                                    ->default(now()),
                            ]),

                        // Invoice Selection Section
                        Section::make('Silahkan Pilih Invoice')
                            ->schema([
                                ViewField::make('vendor_invoice_selection_table')
                                    ->label('')
                                    ->view('components.vendor-payment-invoice-table-clean')
                                    ->viewData(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) {
                                            return [
                                                'invoices' => [], 
                                                'selectedInvoices' => [],
                                                'message' => 'Silahkan pilih supplier terlebih dahulu'
                                            ];
                                        }

                                        // Get unpaid/partial invoices for selected supplier
                                        $invoices = Invoice::join('purchase_orders', function($join) {
                                                $join->on('invoices.from_model_id', '=', 'purchase_orders.id')
                                                     ->where('invoices.from_model_type', '=', 'App\Models\PurchaseOrder');
                                            })
                                            ->where('purchase_orders.supplier_id', $supplierId)
                                            ->whereHas('accountPayable', function ($query) {
                                                $query->where('remaining', '>', 0);
                                            })
                                            ->with(['accountPayable'])
                                            ->select('invoices.*')
                                            ->get()
                                            ->map(function ($invoice) {
                                                return [
                                                    'id' => $invoice->id,
                                                    'invoice_number' => $invoice->invoice_number,
                                                    'total' => $invoice->total,
                                                    'remaining' => $invoice->accountPayable->remaining ?? $invoice->total,
                                                    'receipt' => '',
                                                    'balance' => '',
                                                    'payment_balance' => '',
                                                    'adjustment_description' => '',
                                                ];
                                            });

                                        $message = $invoices->isEmpty() 
                                            ? 'Invoice supplier belum ada' 
                                            : '';

                                        return [
                                            'invoices' => $invoices,
                                            'selectedInvoices' => $get('selected_invoices') ?? [],
                                            'message' => $message
                                        ];
                                    })
                                    ->visible(fn ($get) => !empty($get('supplier_id'))),
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

                                TextInput::make('total_payment')
                                    ->label('Total Pembayaran')
                                    ->required()
                                    ->indonesianMoney()
                                    ->reactive()
                                    ->readOnly() // Make it read-only since it's calculated automatically
                                    ->extraAttributes([
                                        'class' => 'auto-calculated-field',
                                        'data-field' => 'total_payment'
                                    ])
                                    ->helperText('Total ini dihitung otomatis berdasarkan invoice yang dipilih'),

                                Select::make('coa_id')
                                    ->label('COA')
                                    ->preload()
                                    ->searchable(['code', 'name'])
                                    ->reactive()
                                    ->options(function ($get) {
                                        $paymentMethod = $get('payment_method');

                                        return match($paymentMethod) {
                                            'Cash' => ChartOfAccount::where('code', 'LIKE', '11%')
                                                ->where(function ($q) {
                                                    $q->where('name', 'LIKE', '%kas%')
                                                      ->orWhere('name', 'LIKE', '%tunai%');
                                                })
                                                ->get()
                                                ->mapWithKeys(function ($coa) {
                                                    return [$coa->id => "({$coa->code}) {$coa->name}"];
                                                }),
                                            'Bank Transfer' => ChartOfAccount::where('code', 'LIKE', '11%')
                                                ->where(function ($q) {
                                                    $q->where('name', 'LIKE', '%bank%')
                                                      ->orWhere('name', 'LIKE', '%rekening%');
                                                })
                                                ->get()
                                                ->mapWithKeys(function ($coa) {
                                                    return [$coa->id => "({$coa->code}) {$coa->name}"];
                                                }),
                                            'Credit' => ChartOfAccount::where('code', 'LIKE', '11%')
                                                ->where('name', 'LIKE', '%piutang%')
                                                ->get()
                                                ->mapWithKeys(function ($coa) {
                                                    return [$coa->id => "({$coa->code}) {$coa->name}"];
                                                }),
                                            'Deposit' => ChartOfAccount::where('type', 'asset')
                                                ->where('name', 'LIKE', '%deposit%')
                                                ->get()
                                                ->mapWithKeys(function ($coa) {
                                                    return [$coa->id => "({$coa->code}) {$coa->name}"];
                                                }),
                                            default => ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                                return [$coa->id => "({$coa->code}) {$coa->name}"];
                                            })
                                        };
                                    })
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
                        // Removed: invoice_id is no longer used, focus on selected_invoices for multiple invoices
                        Hidden::make('status')->default('Draft'),
                        Hidden::make('payment_adjustment')->default(0),
                        Hidden::make('diskon')->default(0),
                        
                        // Wire model fields for JavaScript communication (dehydrated for saving)
                        Hidden::make('selected_invoices')
                            ->reactive()
                            ->dehydrated(true)
                            ->live(onBlur: true)
                            ->extraAttributes([
                                'wire:model' => 'selected_invoices',
                                'x-data' => '',
                                'x-init' => 'console.log("selected_invoices field initialized")'
                            ])
                            ->afterStateUpdated(function ($state, $set) {
                                // Debug: Log when selected_invoices changes
                                \Illuminate\Support\Facades\Log::info('selected_invoices updated', ['value' => $state]);
                            }),
                        Hidden::make('invoice_receipts')
                            ->reactive()
                            ->dehydrated(true)
                            ->live(onBlur: true)
                            ->extraAttributes([
                                'wire:model' => 'invoice_receipts',
                                'x-data' => '',
                                'x-init' => 'console.log("invoice_receipts field initialized")'
                            ])
                            ->afterStateUpdated(function ($state, $set) {
                                // Debug: Log when invoice_receipts changes
                                \Illuminate\Support\Facades\Log::info('invoice_receipts updated', ['value' => $state]);
                            }),

                        // Keep repeater for compatibility but make it hidden since we use table selection
                        Section::make('Detail Payment Items (Legacy)')
                            ->collapsed()
                            ->visible(false) // Hide this section as we use the table selection instead
                            ->schema([
                                Repeater::make('vendorPaymentDetail')
                                    ->label('Payment Detail')
                                    ->relationship()
                                    ->addAction(function (Action $action) {
                                        return $action->color('primary')
                                            ->icon('heroicon-o-plus-circle');
                                    })
                                    ->columnSpanFull()
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                        return $data;
                                    })
                                    ->addActionLabel('Tambah Pembayaran')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->label('Pembayaran')
                                            ->indonesianMoney()
                                            ->reactive()
                                            ->validationMessages([
                                                'numeric' => 'Pembayaran tidak valid !'
                                            ])
                                            ->afterStateUpdated(function ($get, $set, $state) {
                                                if ($get('method') == 'Deposit') {
                                                    $deposit = Deposit::where('from_model_type', 'App\Models\Supplier')
                                                        ->where('from_model_id', $get('../../supplier_id'))->where('status', 'active')->first();
                                                    if (!$deposit) {
                                                        HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Deposit tidak tersedia pada supplier ini");
                                                        $set('method', null);
                                                    } else {
                                                        if ($get('amount') > $deposit->remaining_amount) {
                                                            HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Saldo deposit tidak cukup");
                                                            $set('amount', 0);
                                                        }
                                                    }
                                                }

                                                // Validate against total payment
                                                $totalPayment = $get('../../total_payment') ?? 0;
                                                if ($state > $totalPayment) {
                                                    HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Pembayaran melebihi total pembayaran");
                                                    $set('amount', 0);
                                                }
                                            })
                                            ->indonesianMoney()
                                            ->default(0),

                                        Select::make('coa_id')
                                            ->label('COA')
                                            ->preload()
                                            ->searchable(['code', 'name'])
                                            ->options(function () {
                                                return ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                                    return [$coa->id => "({$coa->code}) {$coa->name}"];
                                                });
                                            })
                                            ->validationMessages([
                                                'required' => 'COA belum dipilih'
                                            ])
                                            ->required(),

                                        DatePicker::make('payment_date')
                                            ->label('Tanggal Pembayaran')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tanggal pembayaran tidak boleh kosong'
                                            ]),

                                        Radio::make('method')
                                            ->inline()
                                            ->label("Payment Method")
                                            ->required()
                                            ->reactive()
                                            ->validationMessages([
                                                'required' => 'Payment method belum dipilih'
                                            ])
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                if ($state == 'Deposit') {
                                                    $deposit = Deposit::where('from_model_type', 'App\Models\Supplier')
                                                        ->where('from_model_id', $get('../../supplier_id'))->where('status', 'active')->first();
                                                    if (!$deposit) {
                                                        HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Deposit tidak tersedia pada supplier ini");
                                                        $set('method', null);
                                                    } else {
                                                        if ($get('amount') > $deposit->remaining_amount) {
                                                            HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Saldo deposit tidak cukup");
                                                            $set('amount', 0);
                                                        }
                                                    }
                                                }
                                            })
                                            ->helperText(function ($get) {
                                                if ($get('method') == 'Deposit') {
                                                    $deposit = Deposit::where('from_model_type', 'App\Models\Supplier')
                                                        ->where('from_model_id', $get('../../supplier_id'))->where('status', 'active')->first();
                                                    if ($deposit) {
                                                        return "Saldo : Rp." . number_format($deposit->remaining_amount, 0, ',', '.');
                                                    }
                                                }
                                            })
                                            ->options([
                                                'Cash' => 'Cash',
                                                'Bank Transfer' => 'Bank Transfer',
                                                'Credit' => 'Credit',
                                                'Deposit' => 'Deposit'
                                            ]),
                                    ])
                            ]),
                            
                        // JavaScript Component for vendor payment calculation
                        View::make('components.vendor-payment-javascript-init'),
                    ])
            ]);
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
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state || empty($state)) {
                            // Fallback to single invoice for backward compatibility
                            return $record->invoice ? $record->invoice->invoice_number : '-';
                        }
                        
                        // Handle both JSON string and array formats
                        $invoiceIds = is_array($state) ? $state : json_decode($state, true);
                        
                        if (!is_array($invoiceIds) || empty($invoiceIds)) {
                            return $record->invoice ? $record->invoice->invoice_number : '-';
                        }
                        
                        $invoices = Invoice::whereIn('id', $invoiceIds)->pluck('invoice_number')->toArray();
                        return count($invoices) > 0 ? implode(', ', $invoices) : '-';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->where(function ($query) use ($search) {
                            // Search in selected_invoices JSON
                            $invoiceIds = Invoice::where('invoice_number', 'LIKE', '%' . $search . '%')->pluck('id');
                            foreach ($invoiceIds as $id) {
                                $query->orWhereJsonContains('selected_invoices', $id);
                            }
                        });
                    }),

                TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date()
                    ->sortable(),

                IconColumn::make('is_import_payment')
                    ->label('Impor?')
                    ->boolean()
                    ->tooltip('Menandakan pembayaran ini mencakup pajak impor')
                    ->toggleable(),

                TextColumn::make('ppn_import_amount')
                    ->label('PPN Impor')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($record) => $record && (float) $record->ppn_import_amount > 0),

                TextColumn::make('pph22_amount')
                    ->label('PPh 22 Impor')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($record) => $record && (float) $record->pph22_amount > 0),

                TextColumn::make('bea_masuk_amount')
                    ->label('Bea Masuk')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($record) => $record && (float) $record->bea_masuk_amount > 0),

                TextColumn::make('total_payment')
                    ->label('Total Payment')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('payment_count')
                    ->label('Invoice Count')
                    ->getStateUsing(function ($record) {
                        return $record->vendorPaymentDetail()->count() . ' invoice';
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

                TextColumn::make('ap_status')
                    ->label('AP Status')
                    ->getStateUsing(function ($record) {
                        $invoiceIds = $record->selected_invoices;

                        // Normalize selected_invoices which may be stored as JSON string, CSV string, single id, or array
                        if (is_string($invoiceIds)) {
                            $decoded = json_decode($invoiceIds, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $invoiceIds = $decoded;
                            } else {
                                $trim = trim($invoiceIds);
                                if ($trim === '') {
                                    $invoiceIds = [];
                                } elseif (str_contains($trim, ',')) {
                                    $invoiceIds = array_filter(array_map('trim', explode(',', $trim)));
                                } elseif (is_numeric($trim)) {
                                    $invoiceIds = [(int) $trim];
                                } else {
                                    $invoiceIds = [];
                                }
                            }
                        }

                        if (!is_array($invoiceIds)) {
                            $invoiceIds = $invoiceIds ? [$invoiceIds] : [];
                        }

                        if (empty($invoiceIds)) return 'No Invoices';

                        $totalRemaining = 0;
                        $allPaid = true;
                        $hasData = false;

                        foreach ($invoiceIds as $invoiceId) {
                            if ($invoiceId) {
                                $ap = \App\Models\AccountPayable::where('invoice_id', $invoiceId)->first();
                                if ($ap) {
                                    $hasData = true;
                                    $totalRemaining += $ap->remaining;
                                    if ($ap->remaining > 0) {
                                        $allPaid = false;
                                    }
                                }
                            }
                        }
                        
                        if (!$hasData) {
                            return 'No AP Data';
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
                //
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
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Informasi Umum')
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('supplier.name')
                            ->label('Supplier')
                            ->formatStateUsing(function ($state, $record) {
                                $supplier = $record->supplier;
                                return $supplier ? "({$supplier->code}) {$supplier->name}" : '-';
                            })
                            ->weight('bold')
                            ->icon('heroicon-o-building-office')
                            ->columnSpanFull(),
                        TextEntry::make('supplier.name')
                            ->label('Informasi Supplier')
                            ->formatStateUsing(function ($state, $record) {
                                $supplier = $record->supplier;
                                if (!$supplier) return '-';

                                $info = [];
                                if ($supplier->address) $info[] = "Alamat: {$supplier->address}";
                                if ($supplier->phone) $info[] = "Telepon: {$supplier->phone}";
                                if ($supplier->email) $info[] = "Email: {$supplier->email}";

                                return !empty($info) ? implode(' | ', $info) : 'Data tidak lengkap';
                            })
                            ->columnSpanFull()
                            ->placeholder('Tidak ada informasi tambahan'),
                        TextEntry::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->date('d F Y')
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('ntpn')
                            ->label('NTPN')
                            ->placeholder('Tidak diisi')
                            ->copyable()
                            ->icon('heroicon-o-document-text'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Draft' => 'gray',
                                'Partial' => 'warning', 
                                'Paid' => 'success',
                                default => 'gray',
                            })
                            ->icon('heroicon-o-information-circle'),
                    ])
                    ->columns(3),

                InfoSection::make('Rincian Keuangan')
                    ->schema([
                        TextEntry::make('total_payment')
                            ->label('Total Pembayaran')
                            ->money('IDR')
                            ->weight('bold')
                            ->size('lg')
                            ->color('success')
                            ->icon('heroicon-o-banknotes'),
                        TextEntry::make('payment_adjustment')
                            ->label('Penyesuaian')
                            ->money('IDR')
                            ->placeholder('Rp 0')
                            ->color(fn ($record) => $record->payment_adjustment != 0 ? 'warning' : 'gray'),
                        TextEntry::make('diskon')
                            ->label('Diskon')
                            ->money('IDR')
                            ->placeholder('Rp 0')
                            ->color(fn ($record) => $record->diskon != 0 ? 'info' : 'gray'),
                        TextEntry::make('total_payment')
                            ->label('Pembayaran Bersih')
                            ->formatStateUsing(function ($state, $record) {
                                $net = $record->total_payment - ($record->payment_adjustment ?? 0) - ($record->diskon ?? 0);
                                return 'Rp ' . number_format($net, 0, ',', '.');
                            })
                            ->weight('bold')
                            ->size('lg')
                            ->color('primary')
                            ->icon('heroicon-o-calculator'),
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->formatStateUsing(function ($state) {
                                return $state ?? 'Cash';
                            })
                            ->badge()
                            ->size('lg')
                            ->color(fn (string $state): string => match ($state) {
                                'Cash' => 'success',
                                'Bank Transfer' => 'info',
                                'Credit' => 'warning', 
                                'Deposit' => 'primary',
                                default => 'gray',
                            }),
                        TextEntry::make('coa.name')
                            ->label('Chart of Account')
                            ->formatStateUsing(function ($state, $record) {
                                $coa = $record->coa;
                                return $coa ? "({$coa->code}) {$coa->name}" : 'Tidak diset';
                            })
                            ->icon('heroicon-o-chart-bar')
                            ->weight('medium'),
                    ])
                    ->columns(3),

                InfoSection::make('Informasi Pajak Impor')
                    ->schema([
                        TextEntry::make('is_import_payment')
                            ->label('Termasuk Pajak Impor?')
                            ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                            ->badge()
                            ->color(fn ($state) => $state ? 'info' : 'gray')
                            ->icon('heroicon-o-globe-alt'),
                        TextEntry::make('ppn_import_amount')
                            ->label('PPN Impor')
                            ->money('IDR')
                            ->placeholder('Rp 0')
                            ->color(fn ($record) => (float) $record->ppn_import_amount > 0 ? 'success' : 'gray')
                            ->icon('heroicon-o-receipt-refund'),
                        TextEntry::make('pph22_amount')
                            ->label('PPh 22 Impor')
                            ->money('IDR')
                            ->placeholder('Rp 0')
                            ->color(fn ($record) => (float) $record->pph22_amount > 0 ? 'warning' : 'gray')
                            ->icon('heroicon-o-scale'),
                        TextEntry::make('bea_masuk_amount')
                            ->label('Bea Masuk')
                            ->money('IDR')
                            ->placeholder('Rp 0')
                            ->color(fn ($record) => (float) $record->bea_masuk_amount > 0 ? 'info' : 'gray')
                            ->icon('heroicon-o-truck'),
                        TextEntry::make('total_import_tax')
                            ->label('Total Pajak Impor')
                            ->formatStateUsing(function ($state, $record) {
                                $total = ($record->ppn_import_amount ?? 0) + 
                                        ($record->pph22_amount ?? 0) + 
                                        ($record->bea_masuk_amount ?? 0);
                                return 'Rp ' . number_format($total, 0, ',', '.');
                            })
                            ->weight('bold')
                            ->color('primary')
                            ->icon('heroicon-o-calculator'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->is_import_payment),

                InfoSection::make('Ringkasan Invoice')
                    ->schema([
                        TextEntry::make('selected_invoices')
                            ->label('Jumlah Invoice')
                            ->formatStateUsing(function ($state, $record) {
                                $count = $record->vendorPaymentDetail->count();
                                return $count . ' invoice';
                            })
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-document-duplicate'),
                        TextEntry::make('selected_invoices')
                            ->label('Nomor Invoice')
                            ->formatStateUsing(function ($state, $record) {
                                $invoiceIds = $record->selected_invoices;

                                // Normalize to array
                                if (is_string($invoiceIds)) {
                                    $decoded = json_decode($invoiceIds, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $invoiceIds = $decoded;
                                    } else {
                                        $invoiceIds = $invoiceIds ? [$invoiceIds] : [];
                                    }
                                }

                                if (empty($invoiceIds)) return 'Tidak ada invoice';

                                $invoices = \App\Models\Invoice::whereIn('id', $invoiceIds)
                                    ->pluck('invoice_number')
                                    ->toArray();

                                return !empty($invoices) ? implode(', ', $invoices) : 'Tidak ada invoice';
                            })
                            ->columnSpanFull()
                            ->copyable(),
                        TextEntry::make('total_payment')
                            ->label('Total Nilai Invoice')
                            ->formatStateUsing(function ($state, $record) {
                                $invoiceIds = $record->selected_invoices;

                                // Normalize to array
                                if (is_string($invoiceIds)) {
                                    $decoded = json_decode($invoiceIds, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $invoiceIds = $decoded;
                                    } else {
                                        $invoiceIds = $invoiceIds ? [$invoiceIds] : [];
                                    }
                                }

                                if (empty($invoiceIds)) return 'Rp 0';

                                $total = \App\Models\Invoice::whereIn('id', $invoiceIds)->sum('total');
                                return 'Rp ' . number_format($total, 0, ',', '.');
                            })
                            ->weight('bold')
                            ->color('info'),
                        TextEntry::make('total_payment')
                            ->label('Total Sisa Hutang')
                            ->formatStateUsing(function ($state, $record) {
                                $invoiceIds = $record->selected_invoices;

                                // Normalize to array
                                if (is_string($invoiceIds)) {
                                    $decoded = json_decode($invoiceIds, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $invoiceIds = $decoded;
                                    } else {
                                        $invoiceIds = $invoiceIds ? [$invoiceIds] : [];
                                    }
                                }

                                if (empty($invoiceIds)) return 'Rp 0';

                                $total = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->sum('remaining');
                                return 'Rp ' . number_format($total, 0, ',', '.');
                            })
                            ->weight('bold')
                            ->color(function ($state, $record) {
                                $invoiceIds = $record->selected_invoices;

                                // Normalize to array
                                if (is_string($invoiceIds)) {
                                    $decoded = json_decode($invoiceIds, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $invoiceIds = $decoded;
                                    } else {
                                        $invoiceIds = $invoiceIds ? [$invoiceIds] : [];
                                    }
                                }

                                if (empty($invoiceIds)) return 'gray';

                                $total = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->sum('remaining');
                                return $total <= 0 ? 'success' : 'warning';
                            }),
                        TextEntry::make('status')
                            ->label('Status Pembayaran')
                            ->formatStateUsing(function ($state, $record) {
                                $invoiceIds = $record->selected_invoices;

                                // Normalize to array
                                if (is_string($invoiceIds)) {
                                    $decoded = json_decode($invoiceIds, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $invoiceIds = $decoded;
                                    } else {
                                        $invoiceIds = $invoiceIds ? [$invoiceIds] : [];
                                    }
                                }

                                if (empty($invoiceIds)) return 'Tidak ada invoice';

                                $total = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->sum('remaining');

                                if ($total <= 0) return 'Lunas';
                                if ($total > 0) return 'Belum Lunas';
                                return 'Unknown';
                            })
                            ->badge()
                            ->color(function ($state) {
                                return match($state) {
                                    'Lunas' => 'success',
                                    'Belum Lunas' => 'warning',
                                    default => 'gray',
                                };
                            }),
                    ])
                    ->columns(2),

                InfoSection::make('Catatan & Metadata')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->markdown(),
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d F Y, H:i:s')
                            ->since()
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('updated_at')
                            ->label('Diperbarui Pada')
                            ->dateTime('d F Y, H:i:s')
                            ->since()
                            ->icon('heroicon-o-arrow-path'),
                        TextEntry::make('deleted_at')
                            ->label('Dihapus Pada')
                            ->dateTime('d F Y, H:i:s')
                            ->placeholder('Tidak dihapus')
                            ->icon('heroicon-o-trash')
                            ->visible(fn ($record) => $record->deleted_at !== null),
                    ])
                    ->columns(3)
                    ->collapsible(),

                InfoSection::make('Detail Pembayaran per Invoice')
                    ->description('Rincian lengkap pembayaran untuk setiap invoice yang terkait')
                    ->schema([
                        RepeatableEntry::make('vendorPaymentDetail')
                            ->label('')
                            ->schema([
                                TextEntry::make('invoice.invoice_number')
                                    ->label('No. Invoice')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->icon('heroicon-o-document-text'),
                                TextEntry::make('invoice.total')
                                    ->label('Total Invoice')
                                    ->money('IDR')
                                    ->weight('medium'),
                                TextEntry::make('amount')
                                    ->label('Jumlah Pembayaran')
                                    ->money('IDR')
                                    ->weight('bold')
                                    ->color('success')
                                    ->icon('heroicon-o-banknotes'),
                                TextEntry::make('amount')
                                    ->label('Info Hutang (AP)')
                                    ->formatStateUsing(function ($state, $record) {
                                        $invoiceIds = $record->selected_invoices;
                                        if (is_string($invoiceIds)) {
                                            $invoiceIds = json_decode($invoiceIds, true) ?? [];
                                        }
                                        if (empty($invoiceIds)) return 'Tidak ada invoice terkait';
                                        
                                        $aps = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->get();
                                        if ($aps->isEmpty()) return 'Data AP tidak ditemukan';
                                        
                                        $total = $aps->sum('total');
                                        $paid = $aps->sum('paid');
                                        $remaining = $aps->sum('remaining');
                                        
                                        return sprintf(
                                            'Total: %s | Terbayar: %s | Sisa: %s',
                                            'Rp ' . number_format($total, 0, ',', '.'),
                                            'Rp ' . number_format($paid, 0, ',', '.'),
                                            'Rp ' . number_format($remaining, 0, ',', '.')
                                        );
                                    })
                                    ->columnSpanFull(),
                                TextEntry::make('amount')
                                    ->label('Sisa Setelah Bayar')
                                    ->formatStateUsing(function ($state, $record) {
                                        $invoiceIds = $record->selected_invoices;
                                        if (is_string($invoiceIds)) {
                                            $invoiceIds = json_decode($invoiceIds, true) ?? [];
                                        }
                                        if (empty($invoiceIds)) return '-';
                                        
                                        $remaining = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->sum('remaining');
                                        return 'Rp ' . number_format($remaining, 0, ',', '.');
                                    })
                                    ->badge()
                                    ->color(function ($state, $record) {
                                        $invoiceIds = $record->selected_invoices;
                                        if (is_string($invoiceIds)) {
                                            $invoiceIds = json_decode($invoiceIds, true) ?? [];
                                        }
                                        if (empty($invoiceIds)) return 'gray';
                                        
                                        $remaining = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->sum('remaining');
                                        return $remaining <= 0 ? 'success' : 'warning';
                                    }),
                                TextEntry::make('amount')
                                    ->label('Progress Pembayaran')
                                    ->formatStateUsing(function ($state, $record) {
                                        $invoiceIds = $record->selected_invoices;
                                        if (is_string($invoiceIds)) {
                                            $invoiceIds = json_decode($invoiceIds, true) ?? [];
                                        }
                                        if (empty($invoiceIds)) return '0%';
                                        
                                        $aps = \App\Models\AccountPayable::whereIn('invoice_id', $invoiceIds)->get();
                                        if ($aps->isEmpty()) return '0%';
                                        
                                        $total = $aps->sum('total');
                                        $paid = $aps->sum('paid');
                                        
                                        if ($total > 0) {
                                            $percentage = ($paid / $total) * 100;
                                            return number_format($percentage, 1) . '%';
                                        }
                                        return '0%';
                                    })
                                    ->badge()
                                    ->color(function ($state, $record) {
                                        $ap = \App\Models\AccountPayable::where('invoice_id', $record->invoice_id)->first();
                                        if ($ap && $ap->total > 0) {
                                            $percentage = ($ap->paid / $ap->total) * 100;
                                            if ($percentage >= 100) return 'success';
                                            if ($percentage >= 50) return 'warning';
                                            return 'danger';
                                        }
                                        return 'gray';
                                    })
                                    ->icon('heroicon-o-chart-bar'),
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
                                    ->date('d M Y'),
                                TextEntry::make('coa.name')
                                    ->label('COA')
                                    ->formatStateUsing(function ($state, $record) {
                                        $coa = $record->coa;
                                        return $coa ? "({$coa->code}) {$coa->name}" : '-';
                                    })
                                    ->limit(40)
                                    ->tooltip(function ($state, $record) {
                                        $coa = $record->coa;
                                        return $coa ? "({$coa->code}) {$coa->name}" : '-';
                                    }),
                                TextEntry::make('coa.name')
                                    ->label('Info Purchase Order')
                                    ->formatStateUsing(function ($state, $record) {
                                        $invoice = $record->invoice;
                                        if (!$invoice) return 'Tidak ada data PO';
                                        
                                        // Get PO from invoice
                                        if ($invoice->from_model_type === 'App\Models\PurchaseOrder') {
                                            $po = \App\Models\PurchaseOrder::find($invoice->from_model_id);
                                            if ($po) {
                                                return "PO: {$po->purchase_order_code} | Status: {$po->status}";
                                            }
                                        }
                                        return 'Tidak ada PO terkait';
                                    })
                                    ->columnSpanFull()
                                    ->placeholder('Tidak ada PO'),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->contained(true),
                    ])
                    ->visible(fn ($record) => $record->vendorPaymentDetail->count() > 0),

                InfoSection::make('Jurnal & Transaksi Terkait')
                    ->description('Jurnal akuntansi yang dihasilkan dari pembayaran ini')
                    ->schema([
                        RepeatableEntry::make('journalEntries')
                            ->label('Entri Jurnal')
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID Jurnal')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn ($state) => "#$state"),
                                TextEntry::make('date')
                                    ->label('Tanggal')
                                    ->date('d M Y')
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('description')
                                    ->label('Deskripsi')
                                    ->limit(50)
                                    ->tooltip(fn ($state) => $state),
                                TextEntry::make('journal_type')
                                    ->label('Status')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('coa.code')
                                    ->label('Kode COA')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('coa.name')
                                    ->label('Nama COA')
                                    ->limit(40)
                                    ->tooltip(fn ($state) => $state),
                                TextEntry::make('debit')
                                    ->label('Debit')
                                    ->money('IDR')
                                    ->placeholder('-')
                                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                                    ->visible(fn ($record) => $record->debit > 0),
                                TextEntry::make('credit')
                                    ->label('Kredit')
                                    ->money('IDR')
                                    ->placeholder('-')
                                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                                    ->visible(fn ($record) => $record->credit > 0),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->contained(false)
                            ->placeholder('Belum ada jurnal'),
                        RepeatableEntry::make('deposits')
                            ->label('Transaksi Deposit')
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID Deposit')
                                    ->badge()
                                    ->color('primary')
                                    ->formatStateUsing(fn ($state) => "#$state"),
                                TextEntry::make('amount')
                                    ->label('Total Deposit')
                                    ->money('IDR')
                                    ->weight('bold')
                                    ->color('success'),
                                TextEntry::make('used_amount')
                                    ->label('Terpakai')
                                    ->money('IDR')
                                    ->color('warning')
                                    ->placeholder('Rp 0'),
                                TextEntry::make('remaining_amount')
                                    ->label('Sisa')
                                    ->money('IDR')
                                    ->weight('medium')
                                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                                    ->placeholder('Rp 0'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'used' => 'warning',
                                        'expired' => 'danger',
                                        default => 'gray',
                                    }),
                            ])
                            ->getStateUsing(function ($record) {
                                if ($record->payment_method !== 'Deposit') {
                                    return [];
                                }
                                return \App\Models\Deposit::where('from_model_type', 'App\Models\Supplier')
                                    ->where('from_model_id', $record->supplier_id)
                                    ->get();
                            })
                            ->columns(5)
                            ->columnSpanFull()
                            ->contained(false)
                            ->visible(fn ($record) => $record->payment_method === 'Deposit')
                            ->placeholder('Tidak ada data deposit'),
                    ])
                    ->columns(1)
                    ->collapsible(),
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
            'view' => Pages\ViewVendorPayment::route('/{record}'),
            'edit' => Pages\EditVendorPayment::route('/{record}/edit'),
        ];
    }
}
