<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlPurchaseResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\Warehouse;
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
                        Section::make('From Purchase Receipt Item')
                            ->description('Quality Control untuk Purchase Receipt Item')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Select::make('from_model_id')
                                    ->label('From Purchase Receipt Item')
                                    ->options(function ($context, $get) {
                                        $query = PurchaseReceiptItem::with(['purchaseReceipt.purchaseOrder.supplier', 'product']);

                                        if ($context === 'create') {
                                            // Hanya tampilkan item yang belum memiliki QC saat create
                                            $query->whereDoesntHave('qualityControl');
                                        }
                                        // Saat edit, tampilkan semua item (termasuk yang sudah memiliki QC)

                                        return $query->get()
                                            ->filter(function ($item) {
                                                // Filter out items with missing relationships to prevent errors
                                                return $item->purchaseReceipt &&
                                                       $item->purchaseReceipt->purchaseOrder &&
                                                       $item->purchaseReceipt->purchaseOrder->supplier &&
                                                       $item->product;
                                            })
                                            ->mapWithKeys(function ($item) {
                                                $receipt = $item->purchaseReceipt;
                                                $po = $receipt->purchaseOrder;
                                                $supplier = $po->supplier;
                                                $product = $item->product;

                                                $receiptNumber = $receipt->receipt_number ?? 'N/A';
                                                $poNumber = $po->po_number ?? 'N/A';
                                                $supplierName = $supplier->name ?? 'N/A';
                                                $productName = $product->name ?? 'N/A';
                                                $quantity = $item->qty_accepted ?? 0;

                                                $label = "PR: {$receiptNumber} - PO: {$poNumber} - {$supplierName} - {$productName} (Qty: {$quantity})";
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

                                        $purchaseReceiptItemId = $get('from_model_id');
                                        if ($purchaseReceiptItemId) {
                                            $item = PurchaseReceiptItem::with(['product.uom'])->find($purchaseReceiptItemId);
                                            if ($item) {
                                                // Populate product information fields
                                                $set('product_name', $item->product->name ?? '');
                                                $set('sku', $item->product->sku ?? '');
                                                $set('uom', $item->product->uom->name ?? '');
                                                $set('quantity_received', $item->qty_received ?? 0);
                                                $set('product_id', $item->product_id ?? null);
                                                $set("warehouse_id", $item->warehouse_id);
                                                $set('rak_id', $item->rak_id);

                                                // Check if there are any completed QC for this item
                                                $hasCompletedQc = $item->qualityControl()->where('status', 1)->exists();

                                                if ($hasCompletedQc) {
                                                    // Use qty_accepted if there are completed QC
                                                    $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                } else {
                                                    // Use qty_accepted for first QC if available, otherwise qty_received
                                                    $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                }

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
                                        'required' => 'Purchase Receipt Item harus dipilih'
                                    ]),
                                \Filament\Forms\Components\Hidden::make('from_model_type')
                                    ->default('App\Models\PurchaseReceiptItem')
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
                                    ->label('Warehouse')
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
                                                $purchaseReceiptItemId = $get('from_model_id');
                                                if ($purchaseReceiptItemId) {
                                                    $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                                    if ($item) {
                                                        // Check if there are any completed QC for this item
                                                        $hasCompletedQc = $item->qualityControl()->where('status', 1)->exists();

                                                        if ($hasCompletedQc) {
                                                            // Use qty_accepted if there are completed QC
                                                            $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                        } else {
                                                            // Use qty_accepted for first QC if available, otherwise qty_received
                                                            $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                        }

                                                        if ((float) $value > $maxInspectable) {
                                                            $fail("Passed quantity ({$value}) cannot exceed accepted quantity ({$maxInspectable}) in purchase receipt.");
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

                                        $purchaseReceiptItemId = $get('from_model_id');
                                        if ($purchaseReceiptItemId) {
                                            $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                            if ($item) {
                                                // Check if there are any completed QC for this item
                                                $hasCompletedQc = $item->qualityControl()->where('status', 1)->exists();

                                                if ($hasCompletedQc) {
                                                    // Use qty_accepted if there are completed QC
                                                    $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                } else {
                                                    // Use qty_accepted for first QC if available, otherwise qty_received
                                                    $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                }

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

                                                $purchaseReceiptItemId = $get('from_model_id');
                                                if ($purchaseReceiptItemId) {
                                                    $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                                    if ($item) {
                                                        // Check if there are any completed QC for this item
                                                        $hasCompletedQc = $item->qualityControl()->where('status', 1)->exists();

                                                        if ($hasCompletedQc) {
                                                            // Use qty_accepted if there are completed QC
                                                            $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                        } else {
                                                            // Use qty_accepted for first QC if available, otherwise qty_received
                                                            $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                        }

                                                        if ($totalInspected > $maxInspectable) {
                                                            $fail("Total inspected quantity ({$totalInspected}) cannot exceed accepted quantity ({$maxInspectable}) in purchase receipt.");
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

                                        $purchaseReceiptItemId = $get('from_model_id');
                                        if ($purchaseReceiptItemId) {
                                            $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                            if ($item) {
                                                // Check if there are any completed QC for this item
                                                $hasCompletedQc = $item->qualityControl()->where('status', 1)->exists();

                                                if ($hasCompletedQc) {
                                                    // Use qty_accepted if there are completed QC
                                                    $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                } else {
                                                    // Use qty_accepted for first QC if available, otherwise qty_received
                                                    $maxInspectable = $item->qty_accepted > 0 ? $item->qty_accepted : $item->qty_received;
                                                }

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
                    ->label('QC Number'),
                TextColumn::make('receipt_number')
                    ->label('Purchase Receipt')
                    ->getStateUsing(function ($record) {
                        return $record->fromModel?->purchaseReceipt?->receipt_number ?? 'N/A';
                    }),
                TextColumn::make('po_number')
                    ->label('Purchase Order')
                    ->getStateUsing(function ($record) {
                        return $record->fromModel?->purchaseReceipt?->purchaseOrder?->po_number ?? 'N/A';
                    }),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->getStateUsing(function ($record) {
                        return $record->product?->name ?? 'N/A';
                    }),
                TextColumn::make('inspectedBy.name')
                    ->label('Inspected By')
                    ->getStateUsing(function ($record) {
                        return $record->inspectedBy?->name ?? 'N/A';
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
                    '<summary class="cursor-pointer font-semibold">Panduan Quality Control (Purchase)</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Daftar Quality Control yang dibuat dari Purchase Receipt Item.</li>' .
                            '<li><strong>Proses QC:</strong> Gunakan tombol <em>Process QC</em> untuk menyetujui QC. Setelah diproses, barang yang lulus akan dikirim ke stock, sedangkan yang ditolak akan dibuatkan return product.</li>' .
                            '<li><strong>Dampak:</strong> Setelah QC diproses, stok akan ditambahkan ke inventory untuk jumlah yang <em>passed</em>; rejected akan membuat Return Product.</li>' .
                            '<li><strong>Catatan:</strong> Tombol <em>Process QC</em> hanya tersedia untuk QC yang belum diproses.</li>' .
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
                    ->label('Warehouse')
                    ->options(Warehouse::pluck('name', 'id')),
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
                            // Sembunyikan action jika passed_quantity = 0
                            return !$record->status && $record->passed_quantity > 0;
                        })
                        ->requiresConfirmation(function ($record) {
                            return [
                                'title' => 'Konfirmasi Process QC',
                                'description' => "Passed: {$record->passed_quantity}, Rejected: {$record->rejected_quantity}. Apakah Anda yakin ingin memproses QC ini?",
                                'submitLabel' => 'Ya, Proses QC',
                            ];
                        })
                        ->action(function ($record) {
                            $qcService = new QualityControlService();
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
        return parent::getEloquentQuery()
            ->where('from_model_type', 'App\Models\PurchaseReceiptItem')
            ->with([
                'product.uom',
                'fromModel.purchaseReceipt.purchaseOrder.supplier',
                'inspectedBy',
                'warehouse',
                'rak'
            ]);
    }
}
