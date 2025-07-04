<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
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
                                        $set('other_fee', $otherFee);
                                        $set('total', $total + $otherFee);
                                    })
                            ]),
                        TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->required()
                            ->reactive()
                            ->suffixAction(Action::make('generateInvoiceNumber')
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
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->required(),
                        TextInput::make('subtotal')
                            ->required()
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $total = $state + $get('tax') + $get('other_fee');
                                $set('total', $total);
                            })
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('tax')
                            ->required()
                            ->prefix('Rp.')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $total = $get('subtotal') + $state + $get('other_fee');
                                $set('total', $total);
                            })
                            ->numeric()
                            ->default(0),
                        TextInput::make('other_fee')
                            ->required()
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $total = $get('subtotal') + $get('tax') + $state;
                                $set('total', $total);
                            })
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('total')
                            ->required()
                            ->prefix('Rp.')
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
                                    ->prefix('Rp.')
                                    ->default(0)
                                    ->required()
                                    ->numeric(),
                                TextInput::make('total')
                                    ->label('Total (Rp)')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->prefix('Rp.')
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                TextColumn::make('subtotal')
                    ->numeric()
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('tax')
                    ->numeric()
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('other_fee')
                    ->numeric()
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('total')
                    ->numeric()
                    ->money('idr')
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
                        return Str::upper($state);
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
