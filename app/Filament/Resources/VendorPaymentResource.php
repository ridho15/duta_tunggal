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
                Fieldset::make('Form Vendor Payment')
                    ->schema([
                        Select::make('invoice_id')
                            ->label('Invoice')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $invoice = Invoice::find($state);
                                $set('supplier_id', $invoice->fromModel->supplier_id);
                                $set('total_payment', $invoice->total);
                            })
                            ->relationship('invoice', 'invoice_number', function (Builder $query) {
                                $query->where('from_model_type', 'App\Models\PurchaseOrder');
                            })
                            ->required(),
                        Select::make('supplier_id')
                            ->label('Supplier')
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
                            ->required(),
                        TextInput::make('ntpn')
                            ->label('NTPN')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('total_payment')
                            ->label('Total Pembayaran')
                            ->required()
                            ->reactive()
                            ->prefix('Rp.')
                            ->default(0)
                            ->numeric(),
                        TextInput::make('diskon')
                            ->label('Diskon')
                            ->default(0)
                            ->numeric()
                            ->prefix('Rp'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->string(),
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
                                                // Check saldo deposit
                                                if ($get('amount') > $deposit->remaining_amount) {
                                                    HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Saldo deposit tidak cukup");
                                                    $set('amount', 0);
                                                }
                                            }
                                        }

                                        $accountPayable = AccountPayable::where('invoice_id', $get('../../invoice_id'))->first();
                                        if ($accountPayable) {
                                            if ($accountPayable->remaining < $state) {
                                                HelperController::sendNotification(isSuccess: false, title: 'Information', message: "Pembayaran melebihi sisa pembayaran");
                                                $set('amount', 0);
                                            }
                                        } else {
                                            HelperController::sendNotification(isSuccess: false, title: "Information", message: "Account payable tidak ditemukan !");
                                        }
                                    })
                                    ->prefix('Rp')
                                    ->default(0),
                                Select::make('coa_id')
                                    ->label('COA')
                                    ->preload()
                                    ->validationMessages([
                                        'required' => 'COA belum dipilih'
                                    ])
                                    ->searchable(['code', 'name'])
                                    ->relationship('coa', 'code')
                                    ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                        return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                                    })
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
                                                // Check saldo deposit
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
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
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
                TextColumn::make('ntpn')
                    ->searchable(),
                TextColumn::make('total_payment')
                    ->label('Total Payment')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
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
