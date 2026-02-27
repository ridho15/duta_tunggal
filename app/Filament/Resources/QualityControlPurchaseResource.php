<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlPurchaseResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class QualityControlPurchaseResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?string $navigationLabel = 'Quality Control Purchase';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quality Control Purchase')
                    ->schema([
                        Section::make('From Purchase Order Item')
                            ->description('Quality Control dibuat dari Purchase Order Item. Alur: PO → QC → Purchase Receipt (dibuat otomatis).')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Select::make('from_model_id')
                                    ->label('Purchase Order Item')
                                    ->options(function ($context, $get) {
                                        $query = PurchaseOrderItem::with(['purchaseOrder.supplier', 'product']);

                                        if ($context === 'create') {
                                            // Hanya tampilkan item dari PO yang approved dan belum memiliki QC
                                            $query->whereHas('purchaseOrder', function ($q) {
                                                $q->where('status', 'approved');
                                            })->whereDoesntHave('qualityControl');
                                        }
                                        // Saat edit, tampilkan semua item dari PO

                                        return $query->get()
                                            ->filter(function ($item) {
                                                // Filter out items with missing relationships to prevent errors
                                                return $item->purchaseOrder &&
                                                       $item->purchaseOrder->supplier &&
                                                       $item->product;
                                            })
                                            ->mapWithKeys(function ($item) {
                                                $po = $item->purchaseOrder;
                                                $supplier = $po->supplier;
                                                $product = $item->product;

                                                $poNumber = $po->po_number ?? 'N/A';
                                                $supplierName = $supplier->perusahaan ?? ($supplier->name ?? 'N/A');
                                                $productName = $product->name ?? 'N/A';
                                                $quantity = $item->quantity ?? 0;

                                                $label = "PO: {$poNumber} - {$supplierName} - {$productName} (Qty: {$quantity})";
                                                return [$item->id => $label];
                                            });
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->disabled(fn ($context) => $context === 'edit') // Disable saat edit
                                    ->dehydrated(fn ($context) => $context !== 'edit') // Jangan kirim data saat edit
                                    ->afterStateUpdated(function ($set, $get, $state, $context) {
                                        // Skip afterStateUpdated in edit mode since field is disabled
                                        if ($context === 'edit') {
                                            return;
                                        }

                                        $passed = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');
                                        $totalInspected = $passed + $rejected;

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::with(['product.uom'])->find($purchaseOrderItemId);
                                            if ($item) {
                                                // Populate product information fields
                                                $set('product_name', $item->product->name ?? '');
                                                $set('sku', $item->product->sku ?? '');
                                                $set('uom', $item->product->uom->name ?? '');
                                                $set('quantity_received', $item->quantity ?? 0);
                                                $set('product_id', $item->product_id ?? null);

                                                $maxInspectable = $item->quantity ?? 0;

                                                if ($maxInspectable > 0 && $totalInspected > $maxInspectable) {
                                                    $set('passed_quantity', $maxInspectable - $rejected);
                                                }
                                            }
                                        }

                                        // Update total_inspected
                                        $set('total_inspected', $passed + $rejected);
                                    })
                                    ->required(fn ($context) => $context !== 'edit') // Required hanya saat create
                                    ->validationMessages([
                                        'required' => 'Purchase Order Item harus dipilih'
                                    ]),
                                \Filament\Forms\Components\Hidden::make('from_model_type')
                                    ->default('App\Models\PurchaseOrderItem')
                                    ->dehydrated(true),
                                TextInput::make('qc_number')
                                    ->label('QC Number')
                                    ->default(function () {
                                        return HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-P-' . date('Ymd') . '-', 4);
                                    })
                                    ->required(fn ($context) => $context !== 'edit') // Required hanya saat create
                                    ->disabled(fn ($context) => $context === 'edit')
                                    ->dehydrated(fn ($context) => $context !== 'edit')
                                    ->rules(function ($context) {
                                        // Tidak ada validasi apapun saat edit
                                        if ($context === 'edit') {
                                            return [];
                                        }
                                        // Validasi normal saat create
                                        return ['required', 'unique:quality_controls,qc_number'];
                                    })
                                    ->validationMessages([
                                        'required' => 'QC Number wajib diisi',
                                        'unique' => 'QC Number sudah digunakan'
                                    ])
                                    ->suffixAction(
                                        ActionsAction::make('generateQcNumber')
                                            ->label('Generate')
                                            ->icon('heroicon-o-arrow-path')
                                            ->action(function ($set) {
                                                $set('qc_number', HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-P-' . date('Ymd') . '-', 4));
                                            })
                                            ->hidden(fn ($context) => $context === 'edit')
                                    ),
                            ]),
                        Section::make('Product Information')
                            ->columns(2)
                            ->schema([
                                TextInput::make('product_name')
                                    ->label('Product')
                                    ->formatStateUsing(function ($state, $get) {
                                        return $state;
                                    })
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('uom')
                                    ->label('Unit of Measure')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('quantity_received')
                                    ->label('Quantity Received')
                                    ->disabled()
                                    ->dehydrated(false),
                                \Filament\Forms\Components\Hidden::make('product_id')
                                    ->dehydrated(true),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(Warehouse::all()->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    }))
                                    ->searchable(['kode', 'name'])
                                    ->required()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Warehouse harus dipilih'
                                    ]),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');
                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => "({$rak->code}) {$rak->name}"];
                                                });
                                        }
                                        return [];
                                    })
                                    ->searchable(['code', 'name'])
                                    ->preload(),
                            ]),
                        Section::make('Quality Control Result')
                            ->columns(3)
                            ->schema([
                                TextInput::make('passed_quantity')
                                    ->label('Passed Quantity')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->rules([
                                        function ($get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $purchaseOrderItemId = $get('from_model_id');
                                                if ($purchaseOrderItemId) {
                                                    $item = PurchaseOrderItem::find($purchaseOrderItemId);
                                                    if ($item) {
                                                        $maxInspectable = $item->quantity ?? 0;

                                                        if ((float) $value > $maxInspectable) {
                                                            $fail("Passed quantity ({$value}) cannot exceed ordered quantity ({$maxInspectable}) in purchase order.");
                                                        }
                                                    }
                                                }
                                            };
                                        }
                                    ])
                                    ->validationMessages([
                                        'required' => 'Passed Quantity wajib diisi',
                                        'numeric' => 'Passed Quantity harus berupa angka'
                                    ])
                                    ->afterStateUpdated(function ($set, $get) {
                                        $passed = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');
                                        $totalInspected = $passed + $rejected;

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::find($purchaseOrderItemId);
                                            if ($item) {
                                                $maxInspectable = $item->quantity ?? 0;

                                                if ($maxInspectable > 0 && $totalInspected > $maxInspectable) {
                                                    $set('passed_quantity', $maxInspectable - $rejected);
                                                }
                                            }
                                        }

                                        // Update total_inspected
                                        $set('total_inspected', $passed + $rejected);
                                    }),
                                TextInput::make('rejected_quantity')
                                    ->label('Rejected Quantity')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->rules([
                                        function ($get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $passed = (float) $get('passed_quantity');
                                                $rejected = (float) $value;
                                                $totalInspected = $passed + $rejected;

                                                $purchaseOrderItemId = $get('from_model_id');
                                                if ($purchaseOrderItemId) {
                                                    $item = PurchaseOrderItem::find($purchaseOrderItemId);
                                                    if ($item) {
                                                        $maxInspectable = $item->quantity ?? 0;

                                                        if ($totalInspected > $maxInspectable) {
                                                            $fail("Total inspected quantity ({$totalInspected}) cannot exceed ordered quantity ({$maxInspectable}) in purchase order.");
                                                        }
                                                    }
                                                }
                                            };
                                        }
                                    ])
                                    ->validationMessages([
                                        'required' => 'Rejected Quantity wajib diisi',
                                        'numeric' => 'Rejected Quantity harus berupa angka'
                                    ])
                                    ->afterStateUpdated(function ($set, $get) {
                                        $passed = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');
                                        $totalInspected = $passed + $rejected;

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::find($purchaseOrderItemId);
                                            if ($item) {
                                                $maxInspectable = $item->quantity ?? 0;

                                                if ($maxInspectable > 0 && $totalInspected > $maxInspectable) {
                                                    $set('rejected_quantity', $maxInspectable - $passed);
                                                }
                                            }
                                        }

                                        // Update total_inspected
                                        $set('total_inspected', $passed + $rejected);
                                    }),
                                TextInput::make('total_inspected')
                                    ->label('Total Inspected')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->reactive(),
                            ]),
                        Section::make('Additional Information')
                            ->columns(2)
                            ->schema([
                                Select::make('inspected_by')
                                    ->label('Inspected By')
                                    ->options(\App\Models\User::pluck('name', 'id'))
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Inspected By harus dipilih'
                                    ]),
                                DatePicker::make('date_send_stock')
                                    ->label('Date Send to Stock'),
                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(3),
                                Textarea::make('reason_reject')
                                    ->label('Reason Reject')
                                    ->rows(3),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qc_number')
                    ->label('QC Number')
                    ->searchable(),
                TextColumn::make('receipt_number')
                    ->label('Purchase Receipt')
                    ->getStateUsing(function ($record) {
                        return $record->fromModel?->purchaseReceipt?->receipt_number ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('fromModel.purchaseReceipt', function ($query) use ($search) {
                            return $query->where('receipt_number', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('po_number')
                    ->label('Purchase Order')
                    ->getStateUsing(function ($record) {
                        return $record->fromModel?->purchaseReceipt?->purchaseOrder?->po_number ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('fromModel.purchaseReceipt.purchaseOrder', function ($query) use ($search) {
                            return $query->where('po_number', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->getStateUsing(function ($record) {
                        return $record->product?->name ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('product', function ($query) use ($search) {
                            return $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('sku', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('inspectedBy.name')
                    ->label('Inspected By')
                    ->getStateUsing(function ($record) {
                        return $record->inspectedBy?->name ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('inspectedBy', function ($query) use ($search) {
                            return $query->where('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('passed_quantity')
                    ->label('Passed')
                    ->numeric(),
                TextColumn::make('rejected_quantity')
                    ->label('Rejected')
                    ->numeric(),
                TextColumn::make('status_formatted')
                    ->label('Status')
                    ->badge()
                    ->color(function (string $state): string {
                        return $state === 'Sudah diproses' ? 'success' : 'warning';
                    }),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Quality Control Purchase (QC Pembelian)</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Quality Control Purchase adalah proses inspeksi kualitas barang yang diterima dari supplier melalui Purchase Receipt.</li>' .
                            '<li><strong>Sumber:</strong> Dibuat otomatis dari <em>Purchase Receipt Item</em> saat barang diterima. Setiap item dalam receipt akan memiliki QC terpisah.</li>' .
                            '<li><strong>Komponen Utama:</strong> <em>QC Number</em> (nomor QC unik), <em>Purchase Receipt</em> (referensi penerimaan), <em>Product</em> (produk yang diinspeksi), <em>Inspected By</em> (petugas QC).</li>' .
                            '<li><strong>Quantity Control:</strong> <em>Passed Quantity</em> (jumlah lulus QC), <em>Rejected Quantity</em> (jumlah ditolak), <em>Total Quantity</em> (dari purchase receipt).</li>' .
                            '<li><strong>Status Flow:</strong> <em>Belum diproses</em> (menunggu inspeksi) → <em>Sudah diproses</em> (QC selesai, stock updated).</li>' .
                            '<li><strong>Validasi:</strong> <em>Quantity Check</em> - total passed + rejected harus sama dengan quantity receipt. <em>Stock Validation</em> - memastikan stock tersedia untuk update.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan <em>Purchase Receipt</em> (sumber), <em>Purchase Order</em> (referensi PO), <em>Inventory</em> (update stock), dan <em>Return Product</em> (untuk rejected items).</li>' .
                            '<li><strong>Actions:</strong> <em>Process QC</em> (proses inspeksi - hanya untuk status belum diproses), <em>View/Edit</em> (lihat detail QC), <em>Delete</em> (hapus QC record).</li>' .
                            '<li><strong>Permissions:</strong> <em>view any quality control purchase</em>, <em>create quality control purchase</em>, <em>update quality control purchase</em>, <em>delete quality control purchase</em>, <em>restore quality control purchase</em>, <em>force-delete quality control purchase</em>.</li>' .
                            '<li><strong>Stock Impact:</strong> <em>Passed items</em> → stock bertambah di inventory. <em>Rejected items</em> → otomatis membuat Return Product untuk dikembalikan ke supplier.</li>' .
                            '<li><strong>Reporting:</strong> Menyediakan data untuk quality metrics, supplier performance, dan inventory accuracy tracking.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        0 => 'Belum diproses',
                        1 => 'Sudah diproses',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->options(Warehouse::all()->mapWithKeys(function ($warehouse) {
                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                    })),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('process_qc')
                        ->label('Process QC')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(function ($record) {
                            // Sembunyikan action jika passed_quantity = 0 atau sudah diproses
                            return !$record->status && $record->passed_quantity > 0;
                        })
                        // Show a resolution form only when there are rejected items
                        ->form(function ($record) {
                            if (!$record || $record->rejected_quantity <= 0) {
                                return [];
                            }

                            return [
                                \Filament\Forms\Components\Placeholder::make('qc_summary')
                                    ->label('Ringkasan QC')
                                    ->content(function () use ($record) {
                                        return "Qty Diterima: {$record->passed_quantity} | Qty Ditolak: {$record->rejected_quantity}";
                                    }),
                                \Filament\Forms\Components\Radio::make('failed_qc_action')
                                    ->label('Tindakan untuk item yang ditolak')
                                    ->required()
                                    ->options(PurchaseReturn::qcActionOptions())
                                    ->descriptions([
                                        PurchaseReturn::QC_ACTION_REDUCE_STOCK
                                            => 'Qty pada PO item akan dikurangi sebesar qty yang ditolak. Barang dianggap tidak datang.',
                                        PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY
                                            => 'PO tetap terbuka; supplier diharapkan mengirim ulang item yang ditolak.',
                                        PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER
                                            => 'Qty yang ditolak digabung ke PO berikutnya dengan harga asli.',
                                    ])
                                    ->reactive(),
                                \Filament\Forms\Components\Select::make('merge_target_po_id')
                                    ->label('Target PO (untuk penggabungan)')
                                    ->searchable()
                                    ->preload()
                                    ->options(function () use ($record) {
                                        return PurchaseOrder::whereIn('status', ['draft', 'pending_approval', 'approved'])
                                            ->whereHas('purchaseOrderItems', function ($q) use ($record) {
                                                $q->where('product_id', $record->product_id);
                                            })
                                            ->orWhere(function ($q) {
                                                $q->whereIn('status', ['draft', 'pending_approval', 'approved']);
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($po) => [$po->id => "{$po->po_number} ({$po->supplier?->name})"])
                                            ->toArray();
                                    })
                                    ->visible(fn ($get) => $get('failed_qc_action') === PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER)
                                    ->required(fn ($get) => $get('failed_qc_action') === PurchaseReturn::QC_ACTION_MERGE_NEXT_ORDER),
                            ];
                        })
                        ->requiresConfirmation(function ($record) {
                            // Only show plain confirmation when there are NO rejected items
                            return $record && $record->rejected_quantity <= 0;
                        })
                        ->modalHeading(fn ($record) => $record && $record->rejected_quantity > 0
                            ? 'Proses QC – Pilih Tindakan untuk Item yang Ditolak'
                            : 'Konfirmasi Process QC'
                        )
                        ->modalDescription(fn ($record) => $record && $record->rejected_quantity <= 0
                            ? "Passed: {$record->passed_quantity}, Rejected: {$record->rejected_quantity}. Apakah Anda yakin ingin memproses QC ini?"
                            : null
                        )
                        ->modalSubmitActionLabel('Proses QC')
                        ->action(function ($record, array $data) {
                            $qcService     = new QualityControlService();
                            $returnService = app(PurchaseReturnService::class);

                            // If there are rejected items, create the purchase return first
                            if ($record->rejected_quantity > 0) {
                                $action     = $data['failed_qc_action'] ?? PurchaseReturn::QC_ACTION_REDUCE_STOCK;
                                $mergePoId  = $data['merge_target_po_id'] ?? null;

                                $returnService->createFromQualityControl($record, $action, $mergePoId ?: null);
                            }

                            $qcService->completeQualityControl($record, []);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Purchase Completed");
                        }),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal'),
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
                InfolistSection::make('Quality Control Details')
                    ->schema([
                        TextEntry::make('qc_number')->label('QC Number'),
                        TextEntry::make('created_at')->date()->label('QC Date'),
                        TextEntry::make('product.name')->label('Product'),
                        TextEntry::make('product.sku')->label('SKU'),
                        TextEntry::make('warehouse.name')->label('Warehouse'),
                        TextEntry::make('rak.name')->label('Rack'),
                        TextEntry::make('status_formatted')->label('Status')->badge(),
                        TextEntry::make('inspectedBy.name')->label('Inspected By'),
                        TextEntry::make('notes'),
                    ])->columns(2),
                InfolistSection::make('Purchase Information')
                    ->schema([
                        TextEntry::make('fromModel.purchaseReceipt.receipt_number')->label('Receipt Number'),
                        TextEntry::make('fromModel.purchaseReceipt.purchaseOrder.po_number')->label('PO Number'),
                        TextEntry::make('fromModel.purchaseReceipt.purchaseOrder.supplier.name')->label('Supplier'),
                        TextEntry::make('fromModel.qty_accepted')->label('Accepted Quantity'),
                        TextEntry::make('fromModel.purchaseReceipt.currency.code')->label('Currency'),
                    ])->columns(2),
                InfolistSection::make('Quality Control Results')
                    ->schema([
                        TextEntry::make('fromModel.qty_received')->label('Qty Received'),
                        TextEntry::make('passed_quantity')->label('Qty Accepted')->color('success'),
                        TextEntry::make('rejected_quantity')->label('Qty Rejected')->color('danger'),
                        TextEntry::make('reason_reject')->label('Rejection Reason'),
                        TextEntry::make('date_send_stock')->date()->label('Date Send to Stock'),
                    ])->columns(3),
                InfolistSection::make('Journal Entries')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                            ->label('View All Journal Entries')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(function ($record) {
                                // Redirect to JournalEntryResource with filter for this quality control
                                $sourceType = urlencode(\App\Models\QualityControl::class);
                                $sourceId = $record->id;

                                return "/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}";
                            })
                            ->openUrlInNewTab()
                            ->visible(function ($record) {
                                return $record->journalEntries()->exists();
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('journalEntries')
                            ->label('')
                            ->schema([
                                TextEntry::make('date')->date()->label('Date'),
                                TextEntry::make('coa.code')->label('COA'),
                                TextEntry::make('coa.name')->label('Account Name'),
                                TextEntry::make('debit')->money('IDR')->label('Debit')->color('success'),
                                TextEntry::make('credit')->money('IDR')->label('Credit')->color('danger'),
                                TextEntry::make('description')->label('Description'),
                                TextEntry::make('journal_type')->badge()->label('Type'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function ($record) {
                        return $record->journalEntries()->exists();
                    }),
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
            'index' => Pages\ListQualityControlPurchases::route('/'),
            'create' => Pages\CreateQualityControlPurchase::route('/create'),
            'view' => Pages\ViewQualityControlPurchase::route('/{record}'),
            'edit' => Pages\EditQualityControlPurchase::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('from_model_type', 'App\Models\PurchaseOrderItem')
            ->with([
                'product.uom',
                'fromModel.purchaseOrder.supplier',
                'inspectedBy',
                'warehouse',
                'rak'
            ]);

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('warehouse', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
    }
}
