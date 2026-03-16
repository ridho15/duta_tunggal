<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use App\Models\Currency;
use App\Models\Product;
use Filament\Forms\Components\Fieldset;
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
                                $product = Product::find($state);
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

                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount'),
                                    'tipe_pajak' => $get('tipe_pajak') ?? null,
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
                            ->reactive()
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
                        TextInput::make('discount')
                            ->label('Discount')
                            ->reactive()
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
                            ->default(fn () => \App\Models\TaxSetting::activeRate('PPN')),
                        TextInput::make('subtotal')
                            ->label('Sub Total')
                            ->reactive()
                            ->indonesianMoney()
                            ->default(0)
                            ->readOnly(),
                        Radio::make('tipe_pajak')
                            ->label('Tipe Pajak')
                            ->inline()
                            ->required()
                            ->options([
                                'Non Pajak' => 'Non Pajak',
                                'Inklusif' => 'Inklusif',
                                'Eklusif' => 'Eklusif'
                            ])
                            ->default('Inklusif')
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable(),
                TextColumn::make('currency')
                    ->label('Mata Uang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('currency', function ($query) use ($search) {
                            $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('symbol', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "{$state->name} ({$state->symbol})";
                    }),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->rupiah()
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('tax')
                    ->label('Tax')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->badge(),
            ])
            ->filters([
                //
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
                            $remaining = max(0, ($record->quantity ?? 0) - $alreadyInspected);

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
                                            ->options(\App\Models\Warehouse::where('status', 1)->get()->mapWithKeys(fn ($w) => [$w->id => "({$w->kode}) {$w->name}"]))
                                            ->default($defaultWarehouseId)
                                            ->searchable()
                                            ->required()
                                            ->validationMessages(['required' => 'Gudang wajib dipilih']),
                                        \Filament\Forms\Components\Select::make('inspected_by')
                                            ->label('Diperiksa Oleh')
                                            ->options(\App\Models\User::pluck('name', 'id'))
                                            ->default(Auth::id())
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
                                                $set('passed_quantity', $received);
                                                $set('rejected_quantity', 0);
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
                                            ->validationMessages(['required' => 'Qty lulus wajib diisi']),
                                        \Filament\Forms\Components\TextInput::make('rejected_quantity')
                                            ->label('Qty Ditolak')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->minValue(0)
                                            ->validationMessages(['required' => 'Qty ditolak wajib diisi']),
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

                            $qc = $qualityControlService->createQCFromPurchaseOrderItem($record, [
                                'inspected_by'     => $data['inspected_by'],
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
}
