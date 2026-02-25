<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\TaxSetting;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
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
use Illuminate\Support\Str;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 6;
    
    // Hide from navigation since we now have separate resources
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Invoice')
                    ->schema([
                        Section::make('Sumber Invoice')
                            ->description('Silahkan Pilih Sumber Invoice')
                            ->columns(2)
                            ->schema([
                                Radio::make('from_model_type')
                                    ->label('From Type')
                                    ->inlineLabel()
                                    ->options([
                                        'App\Models\PurchaseOrder' => 'Pembelian',
                                        'App\Models\SaleOrder' => 'Penjualan'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        if ($get('from_model_type') == 'App\Models\SaleOrder') {
                                            $taxSetting = TaxSetting::whereDate('effective_date', '<=', Carbon::now())
                                                ->where('status', true)
                                                ->where('type', 'PPN')
                                                ->first();
                                            if ($taxSetting) {
                                                $set('tax', $taxSetting->rate);
                                            }
                                        } elseif ($get('from_model_type') == 'App\Models\PurchaseOrder') {
                                            $taxSetting = TaxSetting::whereDate('effective_date', '<=', Carbon::now())
                                                ->where('status', true)
                                                ->where('type', 'PPH')
                                                ->first();
                                            if ($taxSetting) {
                                                $set('tax', $taxSetting->rate);
                                            }
                                        }
                                    })
                                    ->required(),
                                Select::make('from_model_id')
                                    ->label(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\PurchaseOrder') {
                                            return 'Pilih PO';
                                        } elseif ($get('from_model_type') == 'App\Models\SaleOrder') {
                                            return 'Pilih SO';
                                        }

                                        return "Pilih";
                                    })
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'From Pembelian / Penjualan tidak boleh kosong'
                                    ])
                                    ->required()
                                    ->options(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\PurchaseOrder') {
                                            return PurchaseOrder::where('status', 'completed')->get()->pluck('po_number', 'id');
                                        } elseif ($get('from_model_type') == 'App\Models\SaleOrder') {
                                            return SaleOrder::where('status', 'completed')->get()->pluck('so_number', 'id');
                                        }

                                        return [];
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $items = [];
                                        $total = 0;
                                        $otherFee = 0;
                                        if ($get('from_model_type') == 'App\Models\PurchaseOrder') {
                                            $purchaseOrder = PurchaseOrder::find($state);
                                            if ($purchaseOrder) {
                                                foreach ($purchaseOrder->purchaseOrderItem as $item) {
                                                    $price = $item->unit_price - $item->discount + $item->tax;
                                                    $subtotal = $price * $item->quantity;
                                                    array_push($items, [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->quantity,
                                                        'price' => $price,
                                                        'total' => $subtotal
                                                    ]);

                                                    $total += $subtotal;
                                                }

                                                foreach ($purchaseOrder->purchaseOrderBiaya as $biaya) {
                                                    if ($biaya->masuk_invoice == 1) {
                                                        $otherFee += ($biaya->total * $biaya->currency->to_rupiah);
                                                    }
                                                }

                                                if ($purchaseOrder->ppn_option === 'non_ppn') {
                                                    $set('tax', 0);
                                                    $set('ppn_rate', 0);
                                                }
                                            }
                                        } elseif ($get('from_model_type') == 'App\Models\SaleOrder') {
                                            $saleOrder = SaleOrder::find($state);
                                            if ($saleOrder) {
                                                foreach ($saleOrder->saleOrderItem as $item) {
                                                    $price = $item->unit_price - $item->discount + $item->tax;
                                                    $subtotal = $price * $item->quantity;
                                                    array_push($items, [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->quantity,
                                                        'price' => $price,
                                                        'total' => $subtotal
                                                    ]);
                                                    $total += $subtotal;
                                                }
                                            }
                                        }

                                        $set('invoiceItem', $items);
                                        $set('subtotal', $total);
                                        $set('dpp', $total);
                                        // Seed other_fee repeater when coming from source
                                        $set('other_fee', $otherFee > 0 ? [[
                                            'name' => 'Biaya Lainnya',
                                            'amount' => $otherFee,
                                        ]] : []);
                                        $set('total', $total + $otherFee);

                                        static::updateDueDate($get, $set);
                                    })
                            ]),
                        TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Invoice number tidak boleh kosong',
                                'unique' => 'Invoice number sudah digunakan'
                            ])
                            ->suffixAction(ActionsAction::make('generateInvoiceNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Invoice Number')
                                ->action(function ($set, $get, $state) {
                                    $invoiceService = app(InvoiceService::class);
                                    $set('invoice_number', $invoiceService->generateInvoiceNumber());
                                }))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        DatePicker::make('invoice_date')
                            ->label('Invoice Date')
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Invoice date tidak boleh kosong'
                            ])
                            ->afterStateUpdated(function ($get, $set) {
                                static::updateDueDate($get, $set);
                            })
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->validationMessages([
                                'required' => 'Due Date tidak boleh kosong'
                            ])
                            ->reactive()
                            ->required(),
                        TextInput::make('subtotal')
                            ->required()
                            ->numeric()
                            ->validationMessages([
                                'required' => 'Subtotal tidak boleh kosong',
                                'numeric' => 'Subtotal tidak valid !'
                            ])
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get) {
                                $set('total', static::hitungTotal($get));
                            })
                            ->indonesianMoney()
                            ->default(0),
                        TextInput::make('dpp')
                            ->label('DPP')
                            ->helperText('Dasar Penggunaan Pajak')
                            ->required()
                            ->validationMessages([
                                'required' => 'DPP tidak boleh kosong',
                                'numeric' => 'DPP tidak valid !'
                            ])
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get) {
                                $set('total', static::hitungTotal($get));
                            })
                            ->indonesianMoney()
                            ->default(0),
                        Repeater::make('other_fee')
                            ->label('Other Fees')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Biaya')
                                    ->required()
                                    ->maxLength(120),
                                TextInput::make('amount')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->minValue(0)
                                    ->indonesianMoney()
                                    ->reactive()
                            ])
                            ->default([])
                            ->addActionLabel('Tambah Biaya')
                            ->columns(2)
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get) {
                                $set('total', static::hitungTotal($get));
                            })
                            ->columnSpanFull(),
                        TextInput::make('tax')
                            ->label('Tax (%)')
                            ->validationMessages([
                                'required' => 'Tax tidak boleh kosong',
                                'numeric' => 'Tax tidak valid'
                            ])
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get) {
                                $set('total', static::hitungTotal($get));
                            })
                            ->numeric()
                            ->default(function ($get) {
                                return 0;
                            }),
                        TextInput::make('ppn_rate')
                            ->label('PPN Rate (%)')
                            ->validationMessages([
                                'required' => 'PPN Rate tidak boleh kosong',
                                'numeric' => 'PPN Rate tidak valid'
                            ])
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get) {
                                $set('total', static::hitungTotal($get));
                            })
                            ->numeric()
                            ->default(function () {
                                $taxSetting = TaxSetting::where('status', true)
                                    ->where('effective_date', '<=', now())
                                    ->where('type', 'PPN')
                                    ->orderByDesc('effective_date')
                                    ->first();
                                if ($taxSetting) {
                                    return $taxSetting->rate;
                                }
                            }),
                        TextInput::make('total')
                            ->required()
                            ->indonesianMoney()
                            ->reactive()
                            ->numeric(),
                        Repeater::make('invoiceItem')
                            ->columnSpanFull()
                            ->relationship()
                            ->columns(4)
                            ->defaultItems(0)
                            ->reactive()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                TextInput::make('price')
                                    ->label('Price (Rp)')
                                    ->indonesianMoney()
                                    ->default(0)
                                    ->required()
                                    ->numeric(),
                                TextInput::make('total')
                                    ->label('Total (Rp)')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->indonesianMoney()
                            ])
                    ])
            ]);
    }

    public static function updateDueDate($get, $set)
    {
        if ($get('from_model_type') == 'App\Models\SaleOrder') {
            $saleOrder = SaleOrder::find($get('from_model_id'));
            if ($get('invoice_date') != null && $saleOrder) {
                $set('due_date', Carbon::parse($get('invoice_date'))->addDays($saleOrder->customer->tempo_kredit)->format('Y-m-d'));
            }
        } elseif ($get('from_model_type') == 'App\Models\PurchaseOrder') {
            $purchaseOrder = PurchaseOrder::find($get('from_model_id'));
            if ($get('invoice_date') != null && $purchaseOrder) {
                $set('due_date', Carbon::parse($get('invoice_date'))->addDays($purchaseOrder->supplier->tempo_hutang)->format('Y-m-d'));
            }
        }
    }

    public static function hitungTotal($get)
    {
        $otherFee = static::sumOtherFee($get);
        $subtotal = (int) $get('subtotal');
        $taxRate = (int) $get('tax');
        $totalTax = ($subtotal + $otherFee) * ($taxRate / 100);
        return $subtotal + $otherFee + $totalTax;
    }

    protected static function sumOtherFee($get): int
    {
        $fees = $get('other_fee');
        if (!$fees) return 0;
        // Accept either array of numbers or array of {amount}
        if (is_array($fees)) {
            $sum = 0;
            foreach ($fees as $fee) {
                if (is_array($fee)) {
                    $sum += (int) ($fee['amount'] ?? 0);
                } else {
                    $sum += (int) $fee;
                }
            }
            return $sum;
        }
        return (int) $fees;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['fromModel']);
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice Number')
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->date()
                    ->label('Invoice Date')
                    ->sortable(),
                TextColumn::make('from_model_type')
                    ->label('Pembelian / Penjualan')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'App\Models\PurchaseOrder') {
                            return 'Pembelian';
                        } elseif ($state == 'App\Models\SaleOrder') {
                            return 'Penjualan';
                        }

                        return '-';
                    }),
                TextColumn::make('fromModel')
                    ->label("Number Pembelian / Penjualan")
                    ->formatStateUsing(function ($record) {
                        if ($record->from_model_type == 'App\Models\PurchaseOrder') {
                            return $record->fromModel->po_number;
                        } elseif ($record->from_model_type == 'App\Models\SaleOrder') {
                            return $record->fromModel->so_number;
                        }

                        return null;
                    }),
                TextColumn::make('customer_name_display')
                    ->label("Customer")
                    ->getStateUsing(function ($record) {
                        return $record->customer_name_display;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('fromModel.customer', function (Builder $query) use ($search) {
                            $query->where('perusahaan', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('subtotal')
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('tax')
                    ->numeric()
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('other_fee_total')
                    ->label('Other Fee')
                    ->getStateUsing(fn ($record) => $record->other_fee_total ?? 0)
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('total')
                    ->numeric()
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'sent' => 'warning',
                            'paid' => 'success',
                            'partially_paid' => 'primary',
                            'overdue' => 'danger',
                            default => '-'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft' => 'DRAF',
                            'sent' => 'TERKIRIM',
                            'paid' => 'DIBAYAR',
                            'partially_paid' => 'DIBAYAR SEBAGIAN',
                            'overdue' => 'TERLAMBAT',
                            default => '-'
                        };
                    }),
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
                    Action::make('cetak_invoice')
                        ->label('Cetak Invoice')
                        ->color('primary')
                        ->icon('heroicon-o-document-text')
                        ->action(function ($record) {
                            if ($record->from_model_type == 'App\Models\PurchaseOrder') {
                                $pdf = Pdf::loadView('pdf.purchase-order-invoice-2', [
                                    'invoice' => $record
                                ])->setPaper('A4', 'potrait');

                                return response()->streamDownload(function () use ($pdf) {
                                    echo $pdf->stream();
                                }, 'Invoice_PO_' . $record->invoice_number . '.pdf');
                            } elseif ($record->from_model_type == 'App\Models\SaleOrder') {
                                $pdf = Pdf::loadView('pdf.sale-order-invoice', [
                                    'invoice' => $record
                                ])->setPaper('A4', 'potrait');

                                return response()->streamDownload(function () use ($pdf) {
                                    echo $pdf->stream();
                                }, 'Invoice_SO_' . $record->invoice_number . '.pdf');
                            }
                        })
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('invoice_date', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
