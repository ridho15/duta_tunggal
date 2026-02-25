<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptResource\Pages;
use App\Filament\Resources\PurchaseReceiptResource\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceiptResource\RelationManagers\PurchaseReceiptItemRelationManager;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PurchaseReceipt;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    // Group label updated to indicate Purchase Order group
    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Receipt')
                    ->schema([
                        TextInput::make('receipt_number')
                            ->label('Receipt Number')
                            ->string()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Receipt number tidak boleh kosong',
                                'unique' => 'Receipt number sudah digunakan'
                            ])
                            ->suffixAction(Action::make('generateReceiptNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Receipt Number')
                                ->action(function ($set, $get, $state) {
                                    $purchaseReceipService = app(PurchaseReceiptService::class);
                                    $set('receipt_number', $purchaseReceipService->generateReceiptNumber());
                                }))
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($record) {
                                return Rule::unique('purchase_receipts', 'receipt_number')
                                    ->whereNull('deleted_at')
                                    ->ignore($record?->id ?? null);
                            })
                            ->required(),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required()
                            ->searchable()
                            ->helperText('Pilih cabang untuk purchase receipt ini'),
                        DateTimePicker::make('receipt_date')
                            ->validationMessages([
                                'required' => 'Tanggal penerimaan belum dipilih',
                                'date' => 'Format tanggal tidak valid'
                            ])
                            ->required(),
                        Select::make('received_by')
                            ->label('Received By')
                            ->preload()
                            ->searchable()
                            ->validationMessages([
                                'required' => 'Penerima belum dipilih',
                                'exists' => 'Penerima tidak tersedia'
                            ])
                            ->relationship('receivedBy', 'name')
                            ->required(),
                        Select::make('currency_id')
                            ->label('Mata Uang')
                            ->preload()
                            ->reactive()
                            ->validationMessages([
                                'exists' => "Mata uang tidak tersedia",
                                'required' => 'Mata uang belum dipilih'
                            ])
                            ->searchable(['name'])
                            ->relationship('currency', 'name')
                            ->getOptionLabelFromRecordUsing(function (Currency $currency) {
                                return "{$currency->name} ({$currency->symbol})";
                            })
                            ->required(),
                        Repeater::make('purchaseReceiptBiaya')
                            ->columnSpanFull()
                            ->relationship()
                            ->addActionAlignment(Alignment::Right)
                            ->addAction(function (Action $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle')
                                    ->label('Tambah Biaya');
                            })
                            ->label('Biaya Lain')
                            ->columns(3)
                            ->schema([
                                TextInput::make('nama_biaya')
                                    ->label('Nama Biaya')
                                    ->string()
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => 'Nama biaya belum diisi',
                                        'string' => 'Nama biaya tidak valid !',
                                        'max' => 'Nama biaya terlalu panjang'
                                    ]),
                                Select::make('currency_id')
                                    ->label('Mata Uang')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('currency', 'name')
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (Currency $currency) {
                                        return "{$currency->name} ({$currency->symbol})";
                                    })
                                    ->validationMessages([
                                        'required' => 'Mata uang belum dipilih',
                                        'exists' => 'Mata uang tidak tersedia'
                                    ]),
                                Select::make('coa_id')
                                    ->label('COA Biaya')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('coa', 'name')
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(function (ChartOfAccount $coa) {
                                        return "({$coa->code}) {$coa->name}";
                                    })
                                    ->options(function () {
                                        return ChartOfAccount::where('type', 'Expense')->orderBy('code')->get()->mapWithKeys(function ($coa) {
                                            return [$coa->id => "({$coa->code}) {$coa->name}"];
                                        });
                                    })
                                    ->validationMessages([
                                        'required' => 'COA biaya belum dipilih',
                                        'exists' => 'COA biaya tidak tersedia'
                                    ]),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->reactive()
                                    ->indonesianMoney()
                                    ->prefix(function ($get) {
                                        $currency = Currency::find($get('currency_id'));
                                        if ($currency) {
                                            return $currency->symbol;
                                        }
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Total tidak boleh kosong',
                                        'numeric' => 'Total biaya tidak valid !',
                                    ])
                                    ->default(0),
                                Radio::make('untuk_pembelian')
                                    ->label('Untuk Pembelian')
                                    ->options([
                                        0 => 'Non Pajak',
                                        1 => 'Pajak'
                                    ])
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tipe Pajak belum dipilih'
                                    ]),
                                Checkbox::make('masuk_invoice')
                                    ->label('Masuk Invoice')
                                    ->default(false),
                            ]),
                        Textarea::make('notes')
                            ->nullable(),
                        Repeater::make('purchaseReceiptPhoto')
                            ->relationship()
                            ->defaultItems(0)
                            ->schema([
                                FileUpload::make('photo_url')
                                    ->image()
                                    ->maxSize(2048)
                                    ->validationMessages([
                                        'required' => 'Photo wajib diupload',
                                        'image' => 'File harus berupa gambar',
                                        'max' => 'Ukuran file maksimal 2MB',
                                        'acceptedFileTypes' => 'Format file harus JPG, PNG, atau format gambar lainnya'
                                    ])
                                    ->required()
                            ]),
                        Repeater::make('purchaseReceiptItem')
                            ->relationship()
                            ->nullable()
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Purchase Receipt Item')
                            ->columns(3)
                            ->reactive()
                            ->defaultItems(0)
                            ->schema([
                                Select::make('product_id')
                                    ->preload()
                                    ->required()
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'Produk belum dipilih',
                                        'exists' => 'Produk tidak tersedia'
                                    ])
                                    ->relationship('product', 'name')
                                    ->reactive()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "{$record->sku} - {$record->name}";
                                    }),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(function () {
                                        $user = Auth::user();
                                        $manageType = $user?->manage_type ?? [];
                                        $query = Warehouse::where('status', 1);
                                        
                                        if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                            $query->where('cabang_id', $user?->cabang_id);
                                        }
                                        
                                        return $query->get()->mapWithKeys(function ($warehouse) {
                                            return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                        });
                                    })
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        $user = Auth::user();
                                        $manageType = $user?->manage_type ?? [];
                                        $query = Warehouse::where('status', 1)
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
                                    ->validationMessages([
                                        'required' => 'Gudang belum dipilih',
                                        'exists' => 'Gudang tidak tersedia'
                                    ]),
                                Select::make('rak_id')
                                    ->label('Rak (Optional)')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->relationship('rak', 'name', function ($get, Builder $query) {
                                        $query->where('warehouse_id', $get('warehouse_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                        return "({$rak->code}) {$rak->name}";
                                    })
                                    ->nullable(),
                                TextInput::make('qty_received')
                                    ->label('Quantity Received')
                                    ->numeric()
                                    ->helperText(function ($get) {
                                        $poItemId = $get('purchase_order_item_id');
                                        if ($poItemId) {
                                            $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                            if ($poItem) {
                                                $total = $poItem->quantity;
                                                $received = $poItem->total_received;
                                                return "Quantity yang datang dari supplier (Total PO: {$total}, Sudah diterima sebelumnya: {$received})";
                                            }
                                        }
                                        return "Quantity yang datang dari supplier";
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity diterima tidak boleh kosong',
                                        'numeric' => 'Quantity diterima tidak valid !',
                                        'min' => 'Quantity diterima minimal 0'
                                    ])
                                    ->rules(['min:0'])
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        // Untuk partial receipt, jangan otomatis hitung qty_rejected
                                        // Biarkan user mengisi qty_accepted dan qty_rejected secara manual
                                        $qtyReceived = (float) ($state ?? 0);
                                        $qtyAccepted = (float) ($get('qty_accepted') ?? 0);

                                        // Validation: qty_accepted tidak boleh melebihi qty_received
                                        if ($qtyAccepted > $qtyReceived) {
                                            $set('qty_accepted', $qtyReceived);
                                            $qtyAccepted = $qtyReceived;

                                            \Filament\Notifications\Notification::make()
                                                ->title('Peringatan')
                                                ->body("Quantity Accepted tidak boleh melebihi Quantity Received ({$qtyReceived})")
                                                ->warning()
                                                ->send();
                                        }

                                        // Validation: qty_accepted tidak boleh melebihi quantity PO
                                        $poItemId = $get('purchase_order_item_id');
                                        if ($poItemId) {
                                            $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                            if ($poItem && $qtyAccepted > $poItem->quantity) {
                                                $set('qty_accepted', $poItem->quantity);
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Peringatan')
                                                    ->body("Quantity Accepted tidak boleh melebihi Quantity PO ({$poItem->quantity})")
                                                    ->warning()
                                                    ->send();
                                            }
                                        }
                                    })
                                    ->default(0),
                                TextInput::make('qty_accepted')
                                    ->label('Quantity Accepted')
                                    ->numeric()
                                    ->helperText(function ($get) {
                                        $poItemId = $get('purchase_order_item_id');
                                        if ($poItemId) {
                                            $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                            if ($poItem) {
                                                $remaining = $poItem->remaining_quantity;
                                                $total = $poItem->quantity;
                                                return "Quantity yang diterima/disetujui (Maksimal: {$total}, Sisa PO: {$remaining})";
                                            }
                                        }
                                        return "Quantity yang diterima/disetujui";
                                    })
                                    ->validationMessages([
                                        'required' => 'Quantity diambil tidak boleh kosong',
                                        'numeric' => 'Quantity diambil tidak valid !',
                                        'min' => 'Quantity diambil minimal 0',
                                        'max' => 'Quantity diterima melebihi quantity PO'
                                    ])
                                    ->rules(function ($get) {
                                        $poItemId = $get('purchase_order_item_id');
                                        if ($poItemId) {
                                            $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                            if ($poItem) {
                                                return ['max:' . $poItem->quantity, 'min:0'];
                                            }
                                        }
                                        return ['min:0'];
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get, $component) {
                                        $qtyReceived = (float) ($get('qty_received') ?? 0);
                                        $qtyAccepted = (float) ($state ?? 0);

                                        // Validation: qty_accepted cannot exceed qty_received
                                        if ($qtyAccepted > $qtyReceived) {
                                            $component->state($qtyReceived);
                                            $qtyAccepted = $qtyReceived;

                                            \Filament\Notifications\Notification::make()
                                                ->title('Peringatan')
                                                ->body("Quantity Accepted tidak boleh melebihi Quantity Received ({$qtyReceived})")
                                                ->warning()
                                                ->send();
                                        }

                                        // Validation: qty_accepted tidak boleh melebihi quantity PO
                                        $poItemId = $get('purchase_order_item_id');
                                        if ($poItemId) {
                                            $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                            if ($poItem && $qtyAccepted > $poItem->quantity) {
                                                $component->state($poItem->quantity);
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Peringatan')
                                                    ->body("Quantity Accepted tidak boleh melebihi Quantity PO ({$poItem->quantity})")
                                                    ->warning()
                                                    ->send();
                                            }
                                        }

                                        // Untuk partial receipt, jangan otomatis hitung qty_rejected
                                        // Biarkan user mengatur qty_accepted dan qty_rejected secara manual
                                    })
                                    ->required(),
                                TextInput::make('qty_rejected')
                                    ->label('Quantity Rejected')
                                    ->numeric()
                                    ->helperText("Quantity yang ditolak (harus diisi manual, tidak otomatis dihitung)")
                                    ->validationMessages([
                                        'numeric' => 'Quantity ditolak tidak valid !',
                                        'min' => 'Quantity ditolak minimal 0'
                                    ])
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $qtyReceived = (float) ($get('qty_received') ?? 0);
                                        $qtyAccepted = (float) ($get('qty_accepted') ?? 0);
                                        $qtyRejected = (float) ($state ?? 0);

                                        // Validasi: total qty_accepted + qty_rejected tidak boleh melebihi qty_received
                                        if ($qtyAccepted + $qtyRejected > $qtyReceived) {
                                            $maxRejected = $qtyReceived - $qtyAccepted;
                                            $set('qty_rejected', max(0, $maxRejected));

                                            \Filament\Notifications\Notification::make()
                                                ->title('Peringatan')
                                                ->body("Total Accepted + Rejected tidak boleh melebihi Quantity Received ({$qtyReceived})")
                                                ->warning()
                                                ->send();
                                        }
                                    }),
                                Textarea::make('reason_rejected')
                                    ->label('Reason Rejected')
                                    ->string()
                                    ->nullable(),
                                Repeater::make('purchaseReceiptItemPhoto')
                                    ->relationship()
                                    ->addActionLabel('Tambah Photo')
                                    ->schema([
                                        FileUpload::make('photo_url')
                                            ->label('Photo')
                                            ->image()
                                            ->acceptedFileTypes(['image/*'])
                                            ->maxSize(1024)
                                            ->validationMessages([
                                                'required' => 'Photo wajib diupload',
                                                'image' => 'File harus berupa gambar',
                                                'max' => 'Ukuran file maksimal 1MB',
                                                'acceptedFileTypes' => 'Format file harus JPG, PNG, atau format gambar lainnya'
                                            ])
                                            ->required(),
                                    ]),
                            ]),

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt Number')
                    ->searchable(),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('receivedBy.name')
                    ->label('Received By')
                    ->searchable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable(),
                TextColumn::make('currency.name')
                    ->label('Currency'),
                TextColumn::make('total_biaya')
                    ->label('Total Biaya Lain')
                    ->money('IDR')
                    ->getStateUsing(function ($record) {
                        return $record->purchaseReceiptBiaya->sum('total');
                    })
                    ->sortable(),
                TextColumn::make('qc_status')
                    ->label('QC Status')
                    ->getStateUsing(function ($record) {
                        $totalItems = $record->purchaseReceiptItem()->count();
                        $sentItems = $record->purchaseReceiptItem()->where('status', 'completed')->count();

                        if ($totalItems === 0) {
                            return 'Tidak ada item';
                        }

                        return "{$sentItems}/{$totalItems} item dikirim ke QC";
                    })
                    ->badge()
                    ->color(function ($state) {
                        if (str_contains($state, 'Tidak ada item')) {
                            return 'gray';
                        }

                        $parts = explode('/', $state);
                        if (count($parts) === 2) {
                            $sent = (int) $parts[0];
                            $total = (int) $parts[1];

                            if ($sent === $total && $total > 0) {
                                return 'success'; // All items sent to QC
                            } elseif ($sent > 0) {
                                return 'warning'; // Some items sent to QC
                            }
                        }

                        return 'danger'; // No items sent to QC
                    })
                    ->tooltip(function ($state) {
                        if (str_contains($state, 'Tidak ada item')) {
                            return 'Purchase receipt ini belum memiliki item';
                        }

                        $parts = explode('/', $state);
                        if (count($parts) === 2) {
                            $sent = (int) $parts[0];
                            $total = (int) $parts[1];

                            if ($sent === $total && $total > 0) {
                                return 'Semua item telah dikirim ke Quality Control';
                            } elseif ($sent > 0) {
                                return 'Beberapa item telah dikirim ke Quality Control';
                            } else {
                                return 'Belum ada item yang dikirim ke Quality Control';
                            }
                        }

                        return '';
                    }),
                SelectColumn::make('status')
                    ->options(function () {
                        return [
                            'draft' => 'Draft',
                            'partial' => 'Partial',
                            'completed' => 'Completed'
                        ];
                    })
                    ->tooltip(function ($state) {
                        switch ($state) {
                            case 'draft':
                                return 'Status Draft: Belum ada item yang dikirim ke Quality Control. Purchase receipt masih dalam proses penerimaan barang.';
                            case 'partial':
                                return 'Status Partial: Beberapa item telah dikirim ke Quality Control. Purchase receipt dalam proses quality control parsial.';
                            case 'completed':
                                return 'Status Completed: Semua item telah dikirim ke Quality Control. Purchase receipt telah selesai dan siap untuk proses selanjutnya seperti pembuatan invoice dan pembayaran.';
                            default:
                                return '';
                        }
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
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Purchase Receipt</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Alur Baru (QC First)</strong>: Purchase Receipt dibuat <strong>otomatis</strong> oleh sistem setelah Quality Control Purchase disetujui (status Passed). Jangan buat receipt manual.</li>' .
                            '<li><strong>Alur</strong>: Purchase Order → Quality Control Purchase → Purchase Receipt (otomatis).</li>' .
                            '<li><strong>QC Status</strong>: Menampilkan status Quality Control terkait receipt ini.</li>' .
                            '<li><strong>Stok</strong>: Stok ditambahkan ke inventory otomatis saat QC disetujui dan receipt dibuat.</li>' .
                            '<li><strong>🔄 Sinkronisasi Retur:</strong> Jika qty_rejected diubah atau dihapus, Purchase Return akan otomatis terupdate atau terhapus untuk menjaga konsistensi data.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'partial' => 'Partial',
                        'completed' => 'Completed',
                    ]),
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(Cabang::pluck('nama', 'id'))
                    ->searchable(),
                Filter::make('receipt_date')
                    ->form([
                        DatePicker::make('receipt_date_from'),
                        DatePicker::make('receipt_date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['receipt_date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('receipt_date', '>=', $date),
                            )
                            ->when(
                                $data['receipt_date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('receipt_date', '<=', $date),
                            );
                    }),
                SelectFilter::make('currency_id')
                    ->label('Currency')
                    ->relationship('currency', 'name')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->name} ({$record->symbol})";
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
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
            PurchaseReceiptItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReceipts::route('/'),
            'view' => ViewPurchaseReceipt::route('/{record}'),
            'edit' => Pages\EditPurchaseReceipt::route('/{record}/edit'),
        ];
    }
}
