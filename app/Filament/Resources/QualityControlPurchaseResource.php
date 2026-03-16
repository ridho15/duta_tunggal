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
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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

    protected static ?int $navigationSort = 3;

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
                                        $query = PurchaseOrderItem::with(['purchaseOrder.supplier', 'product', 'qualityControls']);

                                        if ($context === 'create') {
                                            // Tampilkan item dari PO yang approved (termasuk yang sudah ada QC partial)
                                            $query->whereHas('purchaseOrder', function ($q) {
                                                $q->where('status', 'approved');
                                            });
                                        }
                                        // Saat edit, tampilkan semua item dari PO

                                        return $query->get()
                                            ->filter(function ($item) use ($context) {
                                                // Filter out items with missing relationships to prevent errors
                                                if (!$item->purchaseOrder || !$item->purchaseOrder->supplier || !$item->product) {
                                                    return false;
                                                }
                                                // Saat create: hanya tampilkan jika masih ada sisa qty yang perlu diinspeksi
                                                if ($context === 'create') {
                                                    $inspected = $item->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
                                                    return ($item->quantity - $inspected) > 0;
                                                }
                                                return true;
                                            })
                                            ->mapWithKeys(function ($item) {
                                                $po           = $item->purchaseOrder;
                                                $supplier     = $po->supplier;
                                                $product      = $item->product;
                                                $poNumber     = $po->po_number ?? 'N/A';
                                                $supplierName = $supplier->perusahaan ?? ($supplier->name ?? 'N/A');
                                                $productName  = $product->name ?? 'N/A';
                                                $ordered      = $item->quantity ?? 0;
                                                $inspected    = $item->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
                                                $remaining    = max(0, $ordered - $inspected);

                                                $label = "PO: {$poNumber} - {$supplierName} - {$productName}"
                                                       . " (Ordered: {$ordered} | Inspected: {$inspected} | Sisa: {$remaining})";
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

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::with(['product.uom', 'qualityControls', 'purchaseOrder.referModel'])->find($purchaseOrderItemId);
                                            if ($item) {
                                                // Populate product information fields
                                                $set('product_name', $item->product->name ?? '');
                                                $set('sku', $item->product->sku ?? '');
                                                $set('uom', $item->product->uom->name ?? '');
                                                $set('product_id', $item->product_id ?? null);

                                                // Auto-fill warehouse from PurchaseOrder, with fallback to OrderRequest
                                                $purchaseOrder = $item->purchaseOrder;
                                                $warehouseId = $purchaseOrder->warehouse_id ?? null;
                                                // If PO refers to an OrderRequest, prefer its warehouse
                                                if (
                                                    $purchaseOrder->refer_model_type === 'App\Models\OrderRequest'
                                                    && $purchaseOrder->refer_model_id
                                                ) {
                                                    $orderRequest = \App\Models\OrderRequest::find($purchaseOrder->refer_model_id);
                                                    if ($orderRequest && $orderRequest->warehouse_id) {
                                                        $warehouseId = $orderRequest->warehouse_id;
                                                    }
                                                }
                                                $set('warehouse_id', $warehouseId);

                                                // Calculate remaining qty based on existing QC records (partial QC support)
                                                $alreadyInspected = $item->qualityControls->sum(
                                                    fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
                                                );
                                                $remainingQty = max(0, ($item->quantity ?? 0) - $alreadyInspected);

                                                // Show remaining as "quantity to inspect this time"
                                                $set('quantity_received', $remainingQty);
                                                // Auto-fill passed_quantity with remaining so user just confirms
                                                $set('passed_quantity', $remainingQty);
                                                $set('rejected_quantity', 0);
                                                $set('total_inspected', $remainingQty);
                                            }
                                        } else {
                                            $set('total_inspected', 0);
                                        }
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
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->helperText('Jumlah barang yang datang/diterima dari supplier')
                                    ->validationMessages([
                                        'required' => 'Quantity Received wajib diisi',
                                        'numeric'  => 'Quantity Received harus berupa angka',
                                    ])
                                    ->dehydrated(true),
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
                                                    $item = PurchaseOrderItem::with('qualityControls')->find($purchaseOrderItemId);
                                                    if ($item) {
                                                        $alreadyInspected = $item->qualityControls->sum(
                                                            fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
                                                        );
                                                        $remainingQty = max(0, ($item->quantity ?? 0) - $alreadyInspected);

                                                        if ((float) $value > $remainingQty) {
                                                            $fail("Passed quantity ({$value}) melebihi sisa qty yang perlu diinspeksi ({$remainingQty}).");
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
                                        $passed   = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::with('qualityControls')->find($purchaseOrderItemId);
                                            if ($item) {
                                                $alreadyInspected = $item->qualityControls->sum(
                                                    fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
                                                );
                                                $remainingQty = max(0, ($item->quantity ?? 0) - $alreadyInspected);

                                                if ($remainingQty > 0 && ($passed + $rejected) > $remainingQty) {
                                                    $set('passed_quantity', max(0, $remainingQty - $rejected));
                                                    $passed = max(0, $remainingQty - $rejected);
                                                }
                                            }
                                        }

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
                                                $passed   = (float) $get('passed_quantity');
                                                $rejected = (float) $value;

                                                $purchaseOrderItemId = $get('from_model_id');
                                                if ($purchaseOrderItemId) {
                                                    $item = PurchaseOrderItem::with('qualityControls')->find($purchaseOrderItemId);
                                                    if ($item) {
                                                        $alreadyInspected = $item->qualityControls->sum(
                                                            fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
                                                        );
                                                        $remainingQty = max(0, ($item->quantity ?? 0) - $alreadyInspected);

                                                        if (($passed + $rejected) > $remainingQty) {
                                                            $fail("Total inspected ({$passed} + {$rejected}) melebihi sisa qty yang perlu diinspeksi ({$remainingQty}).");
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
                                        $passed   = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::with('qualityControls')->find($purchaseOrderItemId);
                                            if ($item) {
                                                $alreadyInspected = $item->qualityControls->sum(
                                                    fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
                                                );
                                                $remainingQty = max(0, ($item->quantity ?? 0) - $alreadyInspected);

                                                if ($remainingQty > 0 && ($passed + $rejected) > $remainingQty) {
                                                    $set('rejected_quantity', max(0, $remainingQty - $passed));
                                                    $rejected = max(0, $remainingQty - $passed);
                                                }
                                            }
                                        }

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
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('qc_number')
                    ->label('QC Number')
                    ->searchable(),
                TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->getStateUsing(function ($record) {
                        $supplier = $record->fromModel?->purchaseOrder?->supplier;
                        if ($supplier) {
                            return "({$supplier->code}) " . ($supplier->perusahaan ?? $supplier->name ?? 'N/A');
                        }
                        return 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('fromModel.purchaseOrder.supplier', function ($query) use ($search) {
                            return $query->where('perusahaan', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('code', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->getStateUsing(function ($record) {
                        return $record->fromModel?->purchaseOrder?->po_number
                            ?? $record->fromModel?->purchaseReceipt?->purchaseOrder?->po_number
                            ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('fromModel.purchaseOrder', function ($query) use ($search) {
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
            ->headerActions([
                Action::make('batch_create_qc')
                    ->label('Batch Buat QC')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalWidth('5xl')
                    ->modalHeading('Batch Pembuatan Quality Control Purchase')
                    ->modalDescription('Pilih beberapa Purchase Order Item sekaligus untuk membuat QC secara massal. Setiap item akan menghasilkan satu QC record.')
                    ->form([
                        Section::make('Pilih PO Items')
                            ->description('Menampilkan item dari PO yang sudah approved dan masih memiliki sisa qty yang perlu diinspeksi (termasuk QC partial).')
                            ->schema([
                                Repeater::make('qc_items')
                                    ->label('Item yang akan di-QC')
                                    ->addActionLabel('Tambah Item')
                                    ->minItems(1)
                                    ->columns(2)
                                    ->schema([
                                        Select::make('purchase_order_item_id')
                                            ->label('Purchase Order Item')
                                            ->options(function () {
                                                return PurchaseOrderItem::with(['purchaseOrder.supplier', 'product', 'qualityControls'])
                                                    ->whereHas('purchaseOrder', fn($q) => $q->where('status', 'approved'))
                                                    ->get()
                                                    ->filter(function ($item) {
                                                        if (!$item->purchaseOrder || !$item->purchaseOrder->supplier || !$item->product) {
                                                            return false;
                                                        }
                                                        $inspected = $item->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
                                                        return ($item->quantity - $inspected) > 0;
                                                    })
                                                    ->mapWithKeys(function ($item) {
                                                        $po           = $item->purchaseOrder;
                                                        $supplier     = $po->supplier->perusahaan ?? 'N/A';
                                                        $product      = $item->product->name ?? 'N/A';
                                                        $inspected    = $item->qualityControls->sum(fn($qc) => $qc->passed_quantity + $qc->rejected_quantity);
                                                        $remaining    = max(0, $item->quantity - $inspected);
                                                        return [$item->id => "PO: {$po->po_number} | {$supplier} | {$product} (Ordered: {$item->quantity} | Sisa: {$remaining})"];
                                                    });
                                            })
                                            ->searchable()
                                            ->required()
                                            ->validationMessages(['required' => 'PO Item harus dipilih'])
                                            ->columnSpanFull(),
                                        TextInput::make('passed_quantity')
                                            ->label('Passed Qty')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),
                                        TextInput::make('rejected_quantity')
                                            ->label('Rejected Qty')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),
                                    ]),
                            ]),
                        Section::make('Pengaturan Umum')
                            ->description('Pengaturan ini berlaku untuk semua QC yang dibuat dalam batch ini.')
                            ->columns(2)
                            ->schema([
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(Warehouse::all()->mapWithKeys(fn($w) => [$w->id => "({$w->kode}) {$w->name}"]))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->validationMessages(['required' => 'Gudang harus dipilih']),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');
                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(fn($rak) => [$rak->id => "({$rak->code}) {$rak->name}"]);
                                        }
                                        return [];
                                    })
                                    ->searchable(),
                                \Filament\Forms\Components\DatePicker::make('inspection_date')
                                    ->label('Tanggal Inspeksi')
                                    ->default(now())
                                    ->required(),
                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $created = 0;
                        foreach ($data['qc_items'] ?? [] as $qcItem) {
                            $poItemId = $qcItem['purchase_order_item_id'] ?? null;
                            if (!$poItemId) continue;

                            $poItem = PurchaseOrderItem::with(['product', 'qualityControls'])->find($poItemId);
                            if (!$poItem) continue;

                            // Check remaining qty (partial QC support)
                            $alreadyInspected = $poItem->qualityControls->sum(
                                fn($qc) => $qc->passed_quantity + $qc->rejected_quantity
                            );
                            $remainingQty = max(0, $poItem->quantity - $alreadyInspected);
                            if ($remainingQty <= 0) continue; // no more qty to inspect

                            $qcNumber = HelperController::generateUniqueCode(
                                'quality_controls', 'qc_number',
                                'QC-P-' . date('Ymd') . '-', 4
                            );

                            $passedQty   = min((float) ($qcItem['passed_quantity'] ?? 0), $remainingQty);
                            $rejectedQty = min((float) ($qcItem['rejected_quantity'] ?? 0), max(0, $remainingQty - $passedQty));

                            QualityControl::create([
                                'from_model_type'   => \App\Models\PurchaseOrderItem::class,
                                'from_model_id'     => $poItemId,
                                'qc_number'         => $qcNumber,
                                'product_id'        => $poItem->product_id,
                                'warehouse_id'      => $data['warehouse_id'],
                                'rak_id'            => $data['rak_id'] ?? null,
                                'passed_quantity'   => $passedQty,
                                'rejected_quantity' => $rejectedQty,
                                'quantity_received' => $passedQty + $rejectedQty,
                                'status'            => 0,
                                'notes'             => $data['notes'] ?? null,
                                'date_send_stock'   => $data['inspection_date'] ?? now(),
                            ]);
                            $created++;
                        }

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Batch QC Berhasil',
                            message: "{$created} Quality Control Purchase berhasil dibuat."
                        );
                    })
                    ->visible(fn() => Auth::user()?->can('create quality control purchase')),
            ])
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
                Filter::make('supplier')
                    ->label('Supplier')
                    ->form([
                        \Filament\Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return \App\Models\Supplier::all()->mapWithKeys(function ($supplier) {
                                    return [$supplier->id => "({$supplier->code}) " . ($supplier->perusahaan ?? $supplier->name ?? '')];
                                });
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['supplier_id'])) {
                            return $query;
                        }
                        return $query->whereHas('fromModel.purchaseOrder', function (Builder $query) use ($data) {
                            $query->where('supplier_id', $data['supplier_id']);
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['supplier_id'])) return null;
                        $supplier = \App\Models\Supplier::find($data['supplier_id']);
                        return $supplier ? 'Supplier: ' . ($supplier->perusahaan ?? $supplier->name) : null;
                    }),
                Filter::make('po_number_filter')
                    ->label('PO Number')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->placeholder('Cari PO Number...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['po_number'])) {
                            return $query;
                        }
                        return $query->whereHas('fromModel.purchaseOrder', function (Builder $query) use ($data) {
                            $query->where('po_number', 'LIKE', '%' . $data['po_number'] . '%');
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return !empty($data['po_number']) ? 'PO: ' . $data['po_number'] : null;
                    }),
                Filter::make('created_at')
                    ->label('Tanggal QC')
                    ->form([
                        DatePicker::make('created_from')->label('Dari Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal'),
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
                                            ->whereHas('purchaseOrderItem', function ($q) use ($record) {
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Purchase Completed. Proses selanjutnya: Tim Gudang perlu memperbarui stok penerimaan barang dan memastikan Purchase Order ditandai sebagai selesai.");
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
                        TextEntry::make('warehouse.cabang.nama')->label('Cabang'),
                        TextEntry::make('rak.name')->label('Rack'),
                        TextEntry::make('status_formatted')->label('Status')->badge(),
                        TextEntry::make('inspectedBy.name')->label('Inspected By'),
                        TextEntry::make('notes'),
                    ])->columns(2),
                InfolistSection::make('Purchase Information')
                    ->schema([
                        // QC Purchase is created from a PurchaseOrderItem, not a receipt item.
                        TextEntry::make('fromModel.purchaseOrder.po_number')->label('PO Number'),
                        TextEntry::make('fromModel.purchaseOrder.supplier.perusahaan')->label('Supplier'),
                        TextEntry::make('fromModel.quantity')->label('Ordered Quantity'),
                        TextEntry::make('fromModel.unit_price')->label('Unit Price')->formatStateUsing(fn ($state) => "Rp " . number_format($state, 0, ',', '.'))
                    ])->columns(2),
                InfolistSection::make('Quality Control Results')
                    ->schema([
                        TextEntry::make('fromModel.quantity')->label('Qty Order'),
                        TextEntry::make('quantity_received')->label('Qty Received'),
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
                                TextEntry::make('debit')->rupiah()->label('Debit')->color('success'),
                                TextEntry::make('credit')->rupiah()->label('Credit')->color('danger'),
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
                'warehouse.cabang',
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
