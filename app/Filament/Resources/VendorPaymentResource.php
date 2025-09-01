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
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

class VendorPaymentResource extends Resource
{
    protected static ?string $model = VendorPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 25;

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
                                    ->view('components.vendor-payment-invoice-table')
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

                                Hidden::make('selected_invoices')
                                    ->reactive(),
                            ]),

                        // Payment Details Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('ntpn')
                                    ->label('NTPN')
                                    ->maxLength(255)
                                    ->required(),

                                TextInput::make('total_payment')
                                    ->label('Total Pembayaran')
                                    ->required()
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->reactive(),

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
                                    ->columnSpan(1),
                            ]),

                        // Hidden fields for backward compatibility
                        Hidden::make('invoice_id'),
                        Hidden::make('status')->default('Draft'),
                        Hidden::make('payment_adjustment')->default(0),
                        Hidden::make('diskon')->default(0),

                        // Keep repeater for compatibility but make it collapsible
                        Section::make('Detail Payment Items')
                            ->collapsed()
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
                                            ->numeric()
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
                                            ->prefix('Rp')
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
                        
                        $invoices = Invoice::whereIn('id', $state)->pluck('invoice_number')->toArray();
                        return implode(', ', $invoices);
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->where(function ($query) use ($search) {
                            // Search in invoice_id for backward compatibility
                            $query->whereHas('invoice', function ($q) use ($search) {
                                $q->where('invoice_number', 'LIKE', '%' . $search . '%');
                            })
                            // Search in selected_invoices JSON
                            ->orWhereHas('invoice', function ($q) use ($search) {
                                $invoiceIds = Invoice::where('invoice_number', 'LIKE', '%' . $search . '%')->pluck('id');
                                foreach ($invoiceIds as $id) {
                                    $q->orWhereJsonContains('selected_invoices', $id);
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

                TextColumn::make('payment_adjustment')
                    ->label('Adjustment')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ntpn')
                    ->searchable()
                    ->toggleable(),

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
