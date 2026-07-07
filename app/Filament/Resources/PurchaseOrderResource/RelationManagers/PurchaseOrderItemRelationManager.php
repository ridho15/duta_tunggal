<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Support\OrderRequestQuantityLock;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Facades\Filament;
use App\Services\QualityControlService;
use App\Notifications\FilamentDatabaseNotification;
use App\Models\QualityControl;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PurchaseOrderItemRelationManager extends RelationManager
{
    protected static string $relationship = 'PurchaseOrderItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchasee Order Item')
                    ->schema([
                        Hidden::make('refer_item_model_type')
                            ->dehydrated(true),
                        Hidden::make('refer_item_model_id')
                            ->dehydrated(true),
                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->sku} - {$record->name}";
                            })
                            ->relationship('product', 'name')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, $livewire) {
                                $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                // Use supplier price from product_supplier pivot; fallback to cost_price
                                $unitPrice = (float) ($product->cost_price ?? 0);
                                $supplierId = $livewire->ownerRecord->supplier_id ?? null;
                                if ($supplierId && $product) {
                                    $supplierProduct = $product->suppliers()->where('suppliers.id', $supplierId)->first();
                                    if ($supplierProduct) {
                                        $unitPrice = (float) $supplierProduct->pivot->supplier_price;
                                    }
                                }
                                $set('unit_price', $unitPrice);

                                $po = $livewire->ownerRecord;
                                $referItem = null;
                                if ($po?->refer_model_type === 'App\\Models\\OrderRequest' && $po?->refer_model_id) {
                                    $referItem = PurchaseOrderResource::resolveOrderRequestItemReference(
                                        (int) $po->refer_model_id,
                                        (int) $state,
                                        $supplierId ? (int) $supplierId : null
                                    );
                                }

                                $set('refer_item_model_type', $referItem ? \App\Models\OrderRequestItem::class : null);
                                $set('refer_item_model_id', $referItem?->id);

                                $tipePajak = \App\Filament\Resources\PurchaseOrderResource::normalizeTaxTypeValue($get('tipe_pajak') ?? null);
                                $taxType = match ($tipePajak) {
                                    'none' => 'None',
                                    'inklusif' => 'PPN Included',
                                    default => 'PPN Excluded',
                                };
                                $resolvedTax = \App\Support\TaxDefaultResolver::resolveForProductId(
                                    $state ? (int) $state : null,
                                    $taxType
                                );
                                $set('tax', $resolvedTax);

                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $resolvedTax,
                                    'discount' => $get('discount'),
                                    'tipe_pajak' => $tipePajak,
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->required(),
                        Select::make('currency_id')
                            ->label('Mata Uang')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->relationship('currency', 'name')
                            ->getOptionLabelFromRecordUsing(function (Currency $currency) {
                                return "{$currency->name} ({$currency->symbol})";
                            })
                            ->validationMessages([
                                'required' => 'Mata uang belum dipilih'
                            ]),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->default(0)
                            ->reactive()
                            ->helperText(function (Get $get) {
                                $orItemId = $get('refer_item_model_id');
                                if (! $orItemId) {
                                    return null;
                                }

                                $max = OrderRequestQuantityLock::orderRequestItemLimit((int) $orItemId)['remaining_for_po'];
                                return "Maks: {$max} (sisa OR)";
                            })
                            ->rules([function (Get $get, $record) {
                                return function ($attribute, $value, $fail) use ($get, $record) {
                                    $orItemId = $get('refer_item_model_id');
                                    if (! $orItemId) {
                                        return;
                                    }

                                    $max = OrderRequestQuantityLock::orderRequestItemLimit(
                                        (int) $orItemId,
                                        $record?->id ? (int) $record->id : null
                                    )['remaining_for_po'];

                                    if ((float) $value > $max) {
                                        $fail("Qty tidak boleh melebihi sisa Order Request ({$max}).");
                                    }
                                };
                            }])
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount'),
                                    'tipe_pajak' => $get('tipe_pajak') ?? null,
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->numeric(),
                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->live(debounce: 500)
                            ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 2)
        JS))
                            ->formatStateUsing(function ($state) {
                                if ($state === null || $state === '') {
                                    return '';
                                }
                                return number_format(\App\Helpers\MoneyHelper::safeParse($state), 2, ',', '.');
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if ($state === null || $state === '') {
                                    return null;
                                }
                                return \App\Helpers\MoneyHelper::safeParse($state);
                            })
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            }),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->indonesianMoney()
                            ->default(0),
                        TextInput::make('tax')
                            ->label('Tax')
                            ->reactive()
                            ->disabled()
                            ->dehydrated(true)
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->indonesianMoney()
                            ->default(fn () => \App\Support\TaxDefaultResolver::resolveFallbackRate()),
                        TextInput::make('subtotal')
                            ->label('Sub Total')
                            ->reactive()
                            ->indonesianMoney()
                            ->default(0)
                            ->readOnly(),
                        Radio::make('tipe_pajak')
                            ->label('Tipe Pajak per Item')
                            ->inline()
                            ->required()
                            ->options([
                                'none' => 'Non Pajak',
                                'inklusif' => 'Inklusif',
                                'eklusif' => 'Eklusif'
                            ])
                            ->default('inklusif')
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $normalizedState = \App\Filament\Resources\PurchaseOrderResource::normalizeTaxTypeValue($state);
                                $taxType = match ($normalizedState) {
                                    'none' => 'None',
                                    'inklusif' => 'PPN Included',
                                    default => 'PPN Excluded',
                                };
                                $resolvedTax = \App\Support\TaxDefaultResolver::resolveForProductId(
                                    $get('product_id') ? (int) $get('product_id') : null,
                                    $taxType
                                );
                                $set('tax', $resolvedTax);

                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $resolvedTax,
                                    'discount' => $get('discount'),
                                    'tipe_pajak' => $normalizedState,
                                ]);
                                $set('subtotal', $subtotal);
                            })
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'product:id,sku,name,uom_id,cabang_id',
                'product.uom:id,name,abbreviation',
                'product.cabang:id,kode,nama',
                'currency:id,code,name,symbol',
                'referItemModel.cabang:id,kode,nama',
                'purchaseOrder.cabang:id,kode,nama',
                'purchaseReceiptItem',
                'qualityControls',
            ]))
            ->columns([
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(45)
                    ->tooltip(fn ($record) => $record->product?->name),
                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn ($record) => static::sourceLabel($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $search = Str::lower($search);

                        if (str_contains('order request', $search) || str_contains('dari order request', $search)) {
                            return $query->orWhereNotNull('refer_item_model_id');
                        }

                        if (str_contains('manual', $search)) {
                            return $query->orWhereNull('refer_item_model_id');
                        }

                        return $query;
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'Order Request' ? 'info' : 'gray'),
                TextColumn::make('refer_item')
                    ->label('Refer Item')
                    ->getStateUsing(fn ($record) => static::referItemLabel($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $numericSearch = preg_replace('/\D+/', '', $search);

                        return $query
                            ->when($numericSearch !== '', fn (Builder $query) => $query->orWhere('refer_item_model_id', (int) $numericSearch))
                            ->orWhere('refer_item_model_type', 'like', "%{$search}%");
                    })
                    ->toggleable(),
                TextColumn::make('currency')
                    ->label('Mata Uang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('currency', function ($query) use ($search) {
                            $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('symbol', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(fn ($state) => $state?->exists ? "{$state->name} ({$state->symbol})" : '-')
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('UOM')
                    ->getStateUsing(fn ($record) => $record->product?->uom?->abbreviation ?? $record->product?->uom?->name ?? '-')
                    ->toggleable(),
                TextColumn::make('item_cabang')
                    ->label('Cabang')
                    ->getStateUsing(fn ($record) => static::itemCabangLabel($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $branchQuery) use ($search) {
                            $branchQuery->whereHasMorph('referItemModel', [OrderRequestItem::class], function (Builder $query) use ($search) {
                                $query->whereHas('cabang', function (Builder $query) use ($search) {
                                    $query->where('kode', 'like', "%{$search}%")
                                        ->orWhere('nama', 'like', "%{$search}%");
                                });
                            })->orWhereHas('product.cabang', function (Builder $query) use ($search) {
                                $query->where('kode', 'like', "%{$search}%")
                                    ->orWhere('nama', 'like', "%{$search}%");
                            })->orWhereHas('purchaseOrder.cabang', function (Builder $query) use ($search) {
                                $query->where('kode', 'like', "%{$search}%")
                                    ->orWhere('nama', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(),
                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->formatStateUsing(fn ($state, $record) => static::moneyLabel($record, $state))
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tax')
                    ->label('Tax')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->formatStateUsing(fn ($state) => Str::upper(PurchaseOrderResource::normalizeTaxTypeValue($state)))
                    ->badge()
                    ->sortable(),
                TextColumn::make('subtotal_preview')
                    ->label('Subtotal')
                    ->getStateUsing(fn ($record) => static::subtotalLabel($record)),
                TextColumn::make('qty_received')
                    ->label('Qty Received')
                    ->getStateUsing(fn ($record) => static::formatQty(static::receiptTotals($record)['received']))
                    ->toggleable(),
                TextColumn::make('qty_accepted')
                    ->label('Qty Accepted')
                    ->getStateUsing(fn ($record) => static::formatQty(static::receiptTotals($record)['accepted']))
                    ->toggleable(),
                TextColumn::make('qty_rejected')
                    ->label('Qty Rejected')
                    ->getStateUsing(fn ($record) => static::formatQty(static::receiptTotals($record)['rejected']))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('remaining_qty')
                    ->label('Remaining Qty')
                    ->getStateUsing(fn ($record) => static::formatQty(static::remainingQty($record)))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->options([
                        'inklusif' => 'Inklusif',
                        'eklusif' => 'Eklusif',
                        'none' => 'Non Pajak',
                    ]),
                SelectFilter::make('source')
                    ->label('Sumber Item')
                    ->options([
                        'order_request' => 'Dari Order Request',
                        'manual' => 'Manual',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'order_request' => $query->whereNotNull('refer_item_model_id'),
                            'manual' => $query->whereNull('refer_item_model_id'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => Cabang::orderBy('kode')->limit(100)->get()->mapWithKeys(
                        fn (Cabang $cabang) => [$cabang->id => "({$cabang->kode}) {$cabang->nama}"]
                    ))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $cabangId = $data['value'] ?? null;

                        if (! $cabangId) {
                            return $query;
                        }

                        return $query->where(function (Builder $branchQuery) use ($cabangId) {
                            $branchQuery->whereHasMorph('referItemModel', [OrderRequestItem::class], function (Builder $query) use ($cabangId) {
                                $query->where('cabang_id', $cabangId);
                            })->orWhereHas('product', function (Builder $query) use ($cabangId) {
                                $query->where('cabang_id', $cabangId);
                            })->orWhereHas('purchaseOrder', function (Builder $query) use ($cabangId) {
                                $query->where('cabang_id', $cabangId);
                            });
                        });
                    }),
                SelectFilter::make('receipt_qc_status')
                    ->label('Receipt / QC')
                    ->options([
                        'has_receipt' => 'Sudah ada receipt',
                        'no_receipt' => 'Belum ada receipt',
                        'has_qc' => 'Sudah ada QC',
                        'no_qc' => 'Belum ada QC',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'has_receipt' => $query->whereHas('purchaseReceiptItem'),
                            'no_receipt' => $query->whereDoesntHave('purchaseReceiptItem'),
                            'has_qc' => $query->whereHas('qualityControls'),
                            'no_qc' => $query->whereDoesntHave('qualityControls'),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([])
            ->actions([
                ActionGroup::make([
                    // Send PO item to Quality Control (QC-before-receipt flow)
                    \Filament\Tables\Actions\Action::make('kirim_qc')
                        ->label('Kirim ke QC')
                        ->color('success')
                        ->icon('heroicon-o-paper-airplane')
                        ->modalHeading('Kirim ke Quality Control')
                        ->modalSubmitActionLabel('Buat QC')
                        ->visible(fn ($record) => $record->purchaseOrder->status === 'approved' && !$record->qualityControl)
                        ->form(function ($record) {
                            $po = $record->purchaseOrder;
                            $alreadyInspected = $record->qualityControls->sum(
                                fn ($qc) => $qc->passed_quantity + $qc->rejected_quantity
                            );
                            $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $record->id);
                            $remaining = min(max(0, ($record->quantity ?? 0) - $alreadyInspected), $limit['remaining_received']);

                            // Resolve default warehouse (Order Request > PO)
                            $defaultWarehouseId = $po->warehouse_id;
                            if ($po->refer_model_type === 'App\Models\OrderRequest' && $po->refer_model_id) {
                                $or = \App\Models\OrderRequest::find($po->refer_model_id);
                                if ($or && $or->warehouse_id) {
                                    $defaultWarehouseId = $or->warehouse_id;
                                }
                            }

                            return [
                                \Filament\Forms\Components\Fieldset::make('Informasi Produk')
                                    ->columns(3)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('_product_name')
                                            ->label('Produk')
                                            ->default($record->product->name ?? '-')
                                            ->disabled()
                                            ->dehydrated(false),
                                        \Filament\Forms\Components\TextInput::make('_quantity_ordered')
                                            ->label('Qty Dipesan')
                                            ->default($record->quantity ?? 0)
                                            ->disabled()
                                            ->dehydrated(false),
                                        \Filament\Forms\Components\TextInput::make('_warehouse_po')
                                            ->label('Gudang PO')
                                            ->default(optional($po->warehouse)->name ?? '-')
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                                \Filament\Forms\Components\Fieldset::make('Data QC')
                                    ->columns(2)
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('warehouse_id')
                                            ->label('Gudang Tujuan')
                                            ->options(function ($record) {
                                                 $po = $record->purchaseOrder;
                                                 $query = \App\Models\Warehouse::where('status', 1);
                                                 if ($po) {
                                                     $cabangId = $po->cabang_id ?? $po->supplier->cabang_id ?? null;
                                                     if ($cabangId) {
                                                         $query->where('cabang_id', $cabangId);
                                                     }
                                                 }
                                                return $query->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(fn ($w) => [$w->id => "({$w->kode}) {$w->name}"]);
                                            })
                                            ->default($defaultWarehouseId)
                                            ->searchable()
                                            ->required()
                                            ->validationMessages(['required' => 'Gudang wajib dipilih']),
                                        \Filament\Forms\Components\Select::make('inspected_by')
                                            ->label('Diperiksa Oleh')
                                            ->options(\App\Models\User::pluck('name', 'id'))
                                            ->default(Auth::id())
                                            ->disabled(fn () => Auth::user()?->hasRole(['Super Admin', 'Owner']) !== true)
                                            ->dehydrated(true)
                                            ->required()
                                            ->validationMessages(['required' => 'Pemeriksa wajib dipilih']),
                                        \Filament\Forms\Components\TextInput::make('quantity_received')
                                            ->label('Qty Diterima')
                                            ->numeric()
                                            ->default($remaining)
                                            ->required()
                                            ->minValue(1)
                                            ->reactive()
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                $received = (float) $state;
                                                $set('passed_quantity', min((float) ($get('passed_quantity') ?? $received), $received));
                                                QualityControlPurchaseResource::syncQcQuantityAgainstReceived($set, $get, 'quantity_received');
                                            })
                                            ->validationMessages([
                                                'required' => 'Qty diterima wajib diisi',
                                                'min' => 'Qty diterima minimal 1',
                                            ]),
                                        \Filament\Forms\Components\TextInput::make('passed_quantity')
                                            ->label('Qty Lulus QC')
                                            ->numeric()
                                            ->default($remaining)
                                            ->required()
                                            ->minValue(0)
                                            ->reactive()
                                            ->rules([
                                                fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    QualityControlPurchaseResource::validateQcQuantityAgainstReceived($get, $fail, $value);
                                                },
                                            ])
                                            ->afterStateUpdated(function ($set, $get) {
                                                QualityControlPurchaseResource::syncQcQuantityAgainstReceived($set, $get, 'passed_quantity');
                                            })
                                            ->validationMessages(['required' => 'Qty lulus wajib diisi']),
                                        \Filament\Forms\Components\TextInput::make('rejected_quantity')
                                            ->label('Qty Ditolak')
                                            ->numeric()
                                            ->default(0)
                                            ->disabled()
                                            ->dehydrated(true),
                                        \Filament\Forms\Components\Select::make('condition')
                                            ->label('Kondisi')
                                            ->options([
                                                'good'    => 'Baik',
                                                'damaged' => 'Rusak Sebagian',
                                                'reject'  => 'Ditolak',
                                            ])
                                            ->default('good')
                                            ->required(),
                                        \Filament\Forms\Components\Textarea::make('notes')
                                            ->label('Catatan QC')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ];
                        })
                        ->action(function ($record, array $data) {
                            $qualityControlService = app(QualityControlService::class);
                            $canChooseInspector = Auth::user()?->hasRole(['Super Admin', 'Owner']) === true;

                            $qc = $qualityControlService->createQCFromPurchaseOrderItem($record, [
                                'inspected_by'     => $canChooseInspector ? ($data['inspected_by'] ?? Auth::id()) : Auth::id(),
                                'passed_quantity'  => (float) ($data['passed_quantity'] ?? 0),
                                'rejected_quantity' => (float) ($data['rejected_quantity'] ?? 0),
                                'quantity_received' => (float) ($data['quantity_received'] ?? 0),
                                'warehouse_id'     => $data['warehouse_id'],
                                'notes'            => $data['notes'] ?? null,
                            ]);

                            if ($qc) {
                                \Filament\Notifications\Notification::make()
                                    ->title('QC Berhasil Dibuat')
                                    ->body('Quality Control untuk ' . optional($record->product)->name . ' telah dibuat.')
                                    ->icon('heroicon-o-check-badge')
                                    ->color('success')
                                    ->send();
                            }
                        }),
                ])
            ])
            ->bulkActions([]);
    }

    protected static function sourceLabel($record): string
    {
        return filled($record->refer_item_model_id) ? 'Order Request' : 'Manual';
    }

    protected static function referItemLabel($record): string
    {
        if (! filled($record->refer_item_model_id)) {
            return '-';
        }

        return class_basename($record->refer_item_model_type ?: OrderRequestItem::class) . ' #' . $record->refer_item_model_id;
    }

    protected static function itemCabangLabel($record): string
    {
        $record->loadMissing('referItemModel.cabang', 'product.cabang', 'purchaseOrder.cabang');

        $cabang = $record->referItemModel?->cabang;
        if (! $cabang || ! $cabang->exists) {
            $cabang = $record->product?->cabang;
        }
        if (! $cabang || ! $cabang->exists) {
            $cabang = $record->purchaseOrder?->cabang;
        }

        if (! $cabang || ! $cabang->exists) {
            return '-';
        }

        return $cabang->kode ? "({$cabang->kode}) {$cabang->nama}" : ($cabang->nama ?? '-');
    }

    protected static function currencyLabel($record): string
    {
        $record->loadMissing('currency');

        if (! $record->currency || ! $record->currency->exists) {
            return '-';
        }

        $code = $record->currency->code ? "{$record->currency->code} - " : '';

        return "{$code}{$record->currency->name} ({$record->currency->symbol})";
    }

    protected static function moneyLabel($record, mixed $amount): string
    {
        $currencyId = is_numeric($record->currency_id ?? null) ? (int) $record->currency_id : null;

        return \App\Support\CurrencyConversionResolver::resolveSymbol($currencyId) . ' '
            . PurchaseOrderResource::formatCurrencyPreviewState($amount ?? 0, $currencyId);
    }

    protected static function preview($record): array
    {
        $currencyId = is_numeric($record->currency_id ?? null) ? (int) $record->currency_id : null;

        return PurchaseOrderResource::calculateCurrencyPreview(
            (float) ($record->quantity ?? 0),
            (float) ($record->unit_price ?? 0),
            (float) ($record->discount ?? 0),
            (float) ($record->tax ?? 0),
            PurchaseOrderResource::normalizeTaxTypeValue($record->tipe_pajak ?? null),
            $currencyId
        );
    }

    protected static function subtotalLabel($record): string
    {
        return static::moneyLabel($record, static::preview($record)['subtotal'] ?? 0);
    }

    protected static function taxNominalLabel($record): string
    {
        return static::moneyLabel($record, static::preview($record)['tax_nominal'] ?? 0);
    }

    protected static function discountNominalLabel($record): string
    {
        return static::moneyLabel($record, static::preview($record)['discount_nominal'] ?? 0);
    }

    protected static function totalLabel($record): string
    {
        return static::moneyLabel($record, static::preview($record)['total'] ?? 0);
    }

    protected static function receiptTotals($record): array
    {
        $record->loadMissing('purchaseReceiptItem');

        return [
            'received' => (float) $record->purchaseReceiptItem->sum('qty_received'),
            'accepted' => (float) $record->purchaseReceiptItem->sum('qty_accepted'),
            'rejected' => (float) $record->purchaseReceiptItem->sum('qty_rejected'),
        ];
    }

    protected static function remainingQty($record): float
    {
        return max(0, (float) ($record->quantity ?? 0) - static::receiptTotals($record)['accepted']);
    }

    protected static function formatQty(mixed $qty): string
    {
        return number_format((float) $qty, 2, ',', '.');
    }
}
