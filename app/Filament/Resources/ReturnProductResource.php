<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReturnProductResource\Pages;
use App\Filament\Resources\ReturnProductResource\Pages\ViewReturnProduct;
use App\Http\Controllers\HelperController;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\ReturnProduct;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\ReturnProductService;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ReturnProductResource extends Resource
{
    protected static ?string $model = ReturnProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Return Product')
                    ->schema([
                        TextInput::make('return_number')
                            ->label('Return Number')
                            ->required()
                            ->reactive()
                            ->prefixAction(ActionsAction::make('generateReturnNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Return Number')
                                ->action(function ($set, $get, $state) {
                                    $returnProductService = app(ReturnProductService::class);
                                    $set('return_number', $returnProductService->generateReturnNumber());
                                }))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Nomor return wajib diisi.',
                                'unique' => 'Nomor return sudah digunakan, silakan generate nomor baru.',
                                'max' => 'Nomor return maksimal 255 karakter.',
                            ]),
                        Radio::make('from_model_type')
                            ->label('From Order')
                            ->inlineLabel()
                            ->reactive()
                            ->options(function () {
                                return [
                                    'App\Models\DeliveryOrder' => 'Delivery Order',
                                    'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                                ];
                            })
                            ->required()
                            ->validationMessages([
                                'required' => 'Tipe order sumber wajib dipilih.',
                            ]),
                        Select::make('from_model_id')
                            ->label(function ($set, $get) {
                                return 'From Order';
                            })
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->options(function ($set, $get, $state) {
                                if ($get('from_model_type') == 'App\Models\DeliveryOrder') {
                                    return DeliveryOrder::select(['id', 'do_number'])->get()->pluck('do_number', 'id');
                                } elseif ($get('from_model_type') == 'App\Models\PurchaseReceipt') {
                                    return PurchaseReceipt::with(['purchaseOrder' => function ($query) {
                                        $query->select(['id', 'po_number', 'order_date']);
                                    }])->select(['id', 'purchase_order_id'])->get()->pluck('purchaseOrder.po_number', 'id');
                                }
                                return [];
                            })
                            ->preload()
                            ->validationMessages([
                                'required' => 'Order sumber wajib dipilih.',
                            ]),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = \App\Models\Warehouse::where('status', 1);
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = \App\Models\Warehouse::where('status', 1)
                                    ->where(function ($q) use ($search) {
                                        $q->where('perusahaan', 'like', "%{$search}%")
                                          ->orWhere('kode', 'like', "%{$search}%");
                                    });
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->required()
                            ->validationMessages([
                                'required' => 'Gudang tujuan retur wajib dipilih.',
                            ]),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->string()
                            ->nullable(),
                        Radio::make('return_action')
                            ->label('Return Action')
                            ->inlineLabel()
                            ->options([
                                'reduce_quantity_only' => 'Reduce Quantity Only (Allow Partial DO)',
                                'close_do_partial' => 'Close DO Partial (Force Complete DO)',
                                'close_so_complete' => 'Close SO Complete (Force Complete SO)',
                            ])
                            ->default('reduce_quantity_only')
                            ->helperText('Choose what happens after return approval. "Reduce Quantity Only" keeps DO open for remaining items. "Close DO Partial" forces DO completion. "Close SO Complete" forces SO completion.')
                            ->required()
                            ->validationMessages([
                                'required' => 'Aksi retur wajib dipilih.',
                            ]),
                        Repeater::make('returnProductItem')
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->columns(2)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                return $data;
                            })
                            ->relationship()
                            ->schema([
                                Radio::make('from_item_model_type')
                                    ->label('From Item Model')
                                    ->options([
                                        'App\Models\DeliveryOrderItem' => 'Sale Order item',
                                        'App\Models\PurchaseReceiptItem' => 'Purchase Receipt Item'
                                    ])->reactive()
                                    ->inlineLabel()
                                    ->default(function ($set, $get) {
                                        $from_model_type = $get('../../from_model_type');
                                        if ($from_model_type == 'App\Models\DeliveryOrder') {
                                            return 'App\Models\DeliveryOrderItem';
                                        } elseif ($from_model_type == 'App\Models\PurchaseReceipt') {
                                            return 'App\Models\PurchaseReceiptItem';
                                        }

                                        return '';
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tipe item sumber wajib dipilih.',
                                    ]),
                                Select::make('from_item_model_id')
                                    ->label('From Item Model')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->options(function ($set, $get) {
                                        if ($get('from_item_model_type') == 'App\Models\DeliveryOrderItem') {
                                            $saleOrderId = $get('../../from_model_id');
                                            $listSaleOrderItem = SaleOrderItem::with(['product'])->where('sale_order_id', $saleOrderId)->select(['id', 'product_id'])->get();
                                            $items = [];
                                            foreach ($listSaleOrderItem as $index => $saleOrderItem) {
                                                $items[$saleOrderItem->id] = "({$saleOrderItem->product->sku}) {$saleOrderItem->product->name}";
                                            }

                                            return $items;
                                        } elseif ($get('from_item_model_type') == 'App\Models\PurchaseReceiptItem') {
                                            $items = [];
                                            $purchaseReceiptId = $get('../../from_model_id');
                                            $listPurchaseReceiptItem = PurchaseReceiptItem::with(['purchaseReceipt.purchaseOrder'])->where('purchase_receipt_id', $purchaseReceiptId)->get();
                                            foreach ($listPurchaseReceiptItem as $purchaseReceiptItem) {
                                                $items[$purchaseReceiptItem->id] = "({$purchaseReceiptItem->product->sku}) {$purchaseReceiptItem->product->name}";
                                            }
                                            return $items;
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $from_item_model_type = $get('from_item_model_type');
                                        $fromModelItem = null;
                                        if ($from_item_model_type == 'App\Models\DeliveryOrderItem') {
                                            $fromModelItem = SaleOrderItem::find($get('from_item_model_id'));
                                        } elseif ($from_item_model_type == 'App\Models\PurchaseReceiptItem') {
                                            $fromModelItem = PurchaseReceiptItem::find($get('from_item_model_id'));
                                        }

                                        if ($fromModelItem) {
                                            $set('product_id', $fromModelItem->product_id);
                                            $set('max_quantity', $fromModelItem->quantity);
                                            $set('quantity', $fromModelItem->quantity);
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Item produk yang akan diretur wajib dipilih.',
                                    ]),
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->disabled()
                                    ->relationship('product', 'id')
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if ($state > $get('max_quantity')) {
                                            $set('quantity', $get('max_quantity'));
                                            HelperController::sendNotification(isSuccess: false, title: "Information", message: "Quantity yang kamu masukkan lebih besar dari sumber order");
                                        }
                                    })
                                    ->default(0)
                                    ->required()
                                    ->minValue(1)
                                    ->validationMessages([
                                        'required' => 'Quantity retur wajib diisi.',
                                        'numeric' => 'Quantity harus berupa angka.',
                                        'min' => 'Quantity minimal 1.',
                                    ]),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('rak', 'name', function ($get, Builder $query) {
                                        $query->where('warehouse_id', $get('../../warehouse_id'));
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Rak penyimpanan wajib dipilih.',
                                    ]),
                                Radio::make('condition')
                                    ->label('Condition')
                                    ->options([
                                        'good' => 'Good',
                                        'damage' => "Damage",
                                        'repair' => "Repair"
                                    ])->inline()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Kondisi produk retur wajib dipilih.',
                                    ]),
                                Textarea::make('note')
                                    ->label('Note')
                                    ->string()
                                    ->nullable()
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label('Return Number')
                    ->searchable(),
                TextColumn::make('from_model_type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('From Model'),
                TextColumn::make('from_model_id')
                    ->label('From Resource')
                    ->formatStateUsing(function ($record) {
                        if ($record->from_model_type == 'App\Models\DeliveryOrder') {
                            return $record->fromModel->do_number;
                        } elseif ($record->from_model_type == 'App\Models\PurchaseReceipt') {
                            return $record->fromModel->receipt_number;
                        } elseif ($record->from_model_type == 'App\Models\QualityControl') {
                            return $record->fromModel->qc_number;
                        }

                        return '-';
                    }),
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->searchable(),
                TextColumn::make('status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->label('Status')
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'approved' => 'success'
                        };
                    })
                    ->badge(),
                TextColumn::make('return_action')
                    ->label('Return Action')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'reduce_quantity_only' => 'Reduce Qty Only',
                            'close_do_partial' => 'Close DO Partial',
                            'close_so_complete' => 'Close SO Complete',
                            default => 'Auto Close'
                        };
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'reduce_quantity_only' => 'warning',
                            'close_do_partial' => 'info',
                            'close_so_complete' => 'danger',
                            default => 'gray'
                        };
                    })
                    ->badge()
                    ->tooltip(function ($state) {
                        return match ($state) {
                            'reduce_quantity_only' => 'Only reduce quantity, keep DO open for remaining items',
                            'close_do_partial' => 'Force close DO regardless of remaining quantity',
                            'close_so_complete' => 'Force close both DO and SO',
                            default => 'Auto close when all quantities returned'
                        };
                    }),
                TextColumn::make('returnProductItem')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->product->sku}) {$state->product->name}";
                    })
                    ->searchable()
                    ->badge(),
                TextColumn::make('returnProductItem')
                    ->label('Gudang & Rak')
                    ->formatStateUsing(function ($record) {
                        $items = $record->returnProductItem;
                        $warehouseRakInfo = [];

                        foreach ($items as $item) {
                            $warehouseName = $item->rak->warehouse->name ?? 'Unknown';
                            $rakName = $item->rak->name ?? 'Unknown';
                            $warehouseRakInfo[] = "{$warehouseName} - {$rakName}";
                        }

                        return implode(', ', array_unique($warehouseRakInfo));
                    })
                    ->tooltip(function ($record) {
                        $items = $record->returnProductItem;
                        $details = [];

                        foreach ($items as $item) {
                            $productName = $item->product->name;
                            $warehouseName = $item->rak->warehouse->name ?? 'Unknown';
                            $rakName = $item->rak->name ?? 'Unknown';
                            $quantity = $item->quantity;
                            $condition = $item->condition ?? 'Unknown';

                            $details[] = "{$productName}: {$quantity} pcs ({$condition}) - {$warehouseName} > {$rakName}";
                        }

                        return implode("\n", $details);
                    })
                    ->badge()
                    ->color('info'),
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
                        ->visible(function ($record) {
                            return $record->status == 'draft' || Auth::user()->hasRole('Super Admin');
                        })
                        ->requiresConfirmation(function ($action, $record) {
                            if ($record->status == 'approved') {
                                $action->modalHeading('Hapus Return Product Approved')
                                       ->modalDescription("⚠️ PERHATIAN: Return product ini sudah diapprove dan mungkin telah mempengaruhi quantity order.\n\n" .
                                                        "Menghapus return product yang sudah diapprove dapat menyebabkan:\n" .
                                                        "- Quantity order menjadi tidak akurat\n" .
                                                        "- Stock movement records menjadi invalid\n\n" .
                                                        "Pastikan Anda memahami konsekuensinya sebelum melanjutkan.")
                                       ->modalSubmitActionLabel('Ya, Hapus Saja');
                            } else {
                                $action->modalHeading('Hapus Return Product Draft')
                                       ->modalDescription("Apakah Anda yakin ingin menghapus return product draft ini?\n\nData yang dihapus tidak dapat dikembalikan.")
                                       ->modalSubmitActionLabel('Ya, Hapus');
                            }
                        })
                        ->before(function ($record) {
                            if ($record->status == 'approved') {
                                // Log the deletion of approved return for audit trail
                                Log::warning("Approved return product deleted", [
                                    'return_number' => $record->return_number,
                                    'deleted_by' => Auth::id(),
                                    'warehouse' => $record->warehouse->name ?? 'Unknown',
                                    'item_count' => $record->returnProductItem->count(),
                                    'total_quantity' => $record->returnProductItem->sum('quantity')
                                ]);
                            }
                        })
                        ->successNotificationTitle(function ($record) {
                            return $record->status == 'approved' 
                                ? "Return product approved berhasil dihapus (dengan risiko)" 
                                : "Return product draft berhasil dihapus";
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('approve return product') && $record->status == 'draft';
                        })
                        ->requiresConfirmation(function ($action, $record) {
                            $itemCount = $record->returnProductItem->count();
                            $totalQuantity = $record->returnProductItem->sum('quantity');
                            
                            $action->modalHeading('Approve Return Product')
                                   ->modalDescription("Apakah Anda yakin ingin menyetujui return product ini?\n\n" .
                                                    "Detail Return:\n" .
                                                    "- Nomor: {$record->return_number}\n" .
                                                    "- Jumlah Item: {$itemCount}\n" .
                                                    "- Total Quantity: {$totalQuantity}\n" .
                                                    "- Aksi: " . match($record->return_action) {
                                                        'reduce_quantity_only' => 'Hanya kurangi quantity',
                                                        'close_do_partial' => 'Tutup DO paksa',
                                                        'close_so_complete' => 'Tutup SO paksa',
                                                        default => 'Auto close'
                                                    } . "\n\n" .
                                                    "⚠️ Pastikan semua data sudah benar sebelum approve.")
                                   ->modalSubmitActionLabel('Ya, Approve Return');
                        })
                        ->action(function ($record) {
                            // Additional validation before approve
                            if ($record->returnProductItem->isEmpty()) {
                                HelperController::sendNotification(
                                    isSuccess: false, 
                                    title: "Approval Failed", 
                                    message: "Tidak dapat approve return product tanpa item. Silakan tambahkan minimal satu item retur."
                                );
                                return;
                            }
                            
                            // Check if warehouse has available space (basic validation)
                            $warehouse = $record->warehouse;
                            if (!$warehouse) {
                                HelperController::sendNotification(
                                    isSuccess: false, 
                                    title: "Approval Failed", 
                                    message: "Gudang tidak valid atau tidak ditemukan."
                                );
                                return;
                            }
                            
                            try {
                                $returnProductService = app(ReturnProductService::class);
                                $returnProductService->updateQuantityFromModel($record);
                                
                                HelperController::sendNotification(
                                    isSuccess: true, 
                                    title: "Return Product Approved", 
                                    message: "Return product {$record->return_number} berhasil diapprove. Quantity telah diperbarui sesuai aksi retur yang dipilih."
                                );
                            } catch (\Exception $e) {
                                HelperController::sendNotification(
                                    isSuccess: false, 
                                    title: "Approval Failed", 
                                    message: "Terjadi kesalahan saat approve return product: " . $e->getMessage()
                                );
                            }
                        }),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([])
            ->description(new HtmlString('
                <details style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #dee2e6;">
                    <summary style="cursor: pointer; font-weight: bold; color: #495057; font-size: 14px;">
                        📦 Panduan Manajemen Return Produk
                    </summary>
                    <div style="margin-top: 12px; color: #495057; font-size: 13px; line-height: 1.5;">
                        <div style="margin-bottom: 12px;">
                            <strong style="color: #dc3545;">🎯 Tujuan & Fungsi:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li>Mengelola return produk dari berbagai sumber (sales order, purchase receipt, quality control)</li>
                                <li>Menangani berbagai skenario return dengan penyesuaian inventory yang tepat</li>
                                <li>Mempertahankan audit trail untuk semua transaksi return</li>
                                <li>Mendukung manajemen gudang dengan rekonsiliasi stok yang proper</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #28a745;">🔄 Jenis Return & Sumber:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Dari Delivery Order:</strong> Return pelanggan dari pengiriman yang telah selesai</li>
                                <li><strong>Dari Purchase Receipt:</strong> Return vendor untuk barang yang diterima</li>
                                <li><strong>Dari Quality Control:</strong> Produk yang ditolak selama inspeksi QC</li>
                                <li><strong>Nomor Return:</strong> Identifier unik yang di-generate otomatis untuk tracking</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #007bff;">⚙️ Aksi Return:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Reduce Quantity Only:</strong> Hanya kurangi inventory, biarkan order tetap terbuka</li>
                                <li><strong>Close DO Partial:</strong> Paksa tutup delivery order terlepas dari quantity tersisa</li>
                                <li><strong>Close SO Complete:</strong> Paksa tutup delivery order dan sales order</li>
                                <li><strong>Auto Close:</strong> Otomatis tutup order ketika semua quantity sudah di-return</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #ffc107;">📊 Alur Status:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Draft:</strong> Return dibuat, dapat diedit/dihapus</li>
                                <li><strong>Approved:</strong> Return diproses, inventory diperbarui, order disesuaikan</li>
                                <li><strong>Kode Warna:</strong> Abu-abu=Draft, Hijau=Approved</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #17a2b8;">🔗 Integrasi & Dependensi:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Manajemen Inventory:</strong> Memperbarui level stok dan lokasi gudang</li>
                                <li><strong>Sales Order:</strong> Dapat menutup atau menutup sebagian sales order</li>
                                <li><strong>Delivery Order:</strong> Menyesuaikan quantity dan status delivery order</li>
                                <li><strong>Purchase Receipt:</strong> Menangani skenario return vendor</li>
                                <li><strong>Quality Control:</strong> Memproses produk yang ditolak dari QC</li>
                                <li><strong>Gudang & Rak:</strong> Mengelola penempatan produk di gudang</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #6c757d;">🔐 Permission & Aksi:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Create:</strong> User dapat membuat entri return produk</li>
                                <li><strong>Edit:</strong> Return draft dapat dimodifikasi</li>
                                <li><strong>Approve:</strong> Membutuhkan permission "approve return product"</li>
                                <li><strong>Delete:</strong> Return draft atau Super Admin dapat menghapus return yang sudah approved</li>
                                <li><strong>View:</strong> Semua user dapat melihat detail return produk</li>
                            </ul>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <strong style="color: #fd7e14;">📈 Reporting & Audit:</strong>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <li><strong>Audit Trail:</strong> Mencatat semua approval dan penghapusan return</li>
                                <li><strong>Pergerakan Stok:</strong> Melacak perubahan inventory dari return</li>
                                <li><strong>Dampak Order:</strong> Mencatat efek pada sales/purchase order</li>
                                <li><strong>Notifikasi:</strong> Notifikasi sukses/gagal untuk semua aksi</li>
                            </ul>
                        </div>

                        <div style="background: #fff3cd; padding: 8px; border-radius: 4px; border-left: 4px solid #ffc107;">
                            <strong style="color: #856404;">⚠️ Catatan Penting:</strong>
                            <ul style="margin: 4px 0; padding-left: 20px; color: #856404;">
                                <li>Return yang sudah approved tidak dapat diedit - buat return baru jika diperlukan</li>
                                <li>Menghapus return yang sudah approved dapat menyebabkan inkonsistensi inventory</li>
                                <li>Pastikan gudang memiliki ruang sebelum menyetujui return</li>
                                <li>Aksi return memiliki dampak berbeda pada lifecycle order</li>
                            </ul>
                        </div>
                    </div>
                </details>
            '));
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
            'index' => Pages\ListReturnProducts::route('/'),
            'create' => Pages\CreateReturnProduct::route('/create'),
            'view' => ViewReturnProduct::route('/{record}'),
            'edit' => Pages\EditReturnProduct::route('/{record}/edit'),
        ];
    }
}
