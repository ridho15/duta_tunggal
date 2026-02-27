<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Filament\Resources\PurchaseReturnResource\Pages\ViewPurchaseReturn;
use App\Models\Product;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    // Group updated to the standardized Purchase Order group
    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Return')
                    ->schema([
                        TextInput::make('nota_retur')
                            ->required()
                            ->label('Note Return')
                            ->reactive()
                            ->suffixAction(Action::make('generateNotaRetur')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Nota Retur')
                                ->action(function ($set, $get, $state) {
                                    $purchaseReturnService = app(PurchaseReturnService::class);
                                    $set('nota_retur', $purchaseReturnService->generateNotaRetur());
                                }))
                            ->maxLength(50)
                            ->validationMessages([
                                'required' => 'Nota retur wajib diisi',
                                'max' => 'Nota retur maksimal 50 karakter'
                            ]),
                        Select::make('purchase_receipt_id')
                            ->required()
                            ->label('Purchase Receipt')
                            ->preload()
                            ->reactive()
                            ->searchable()
                            ->relationship('purchaseReceipt', 'receipt_number', function (Builder $query) {
                                $query->whereHas('purchaseOrder', function (Builder $query) {
                                    $query->where('status', 'closed');
                                });
                            })
                            ->afterStateUpdated(function ($set, $state) {
                                if ($state) {
                                    $purchaseReceipt = \App\Models\PurchaseReceipt::find($state);
                                    if ($purchaseReceipt && !in_array('all', Auth::user()?->manage_type ?? [])) {
                                        $set('cabang_id', $purchaseReceipt->cabang_id);
                                    }
                                }
                            })
                            ->validationMessages([
                                'required' => 'Purchase receipt wajib dipilih'
                            ]),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    return \App\Models\Cabang::where('id', $user?->cabang_id)
                                        ->get()
                                        ->mapWithKeys(function ($cabang) {
                                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                        });
                                }
                                
                                return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                            })
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih'
                            ]),
                        DateTimePicker::make('return_date')
                            ->label('Return Date')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal retur wajib diisi'
                            ]),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_approval' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft')
                            ->disabled(fn () => !in_array('all', Auth::user()?->manage_type ?? []))
                            ->dehydrated(),
                        Textarea::make('notes')
                            ->label('Keterangan')
                            ->nullable(),
                        Repeater::make('purchaseReturnItem')
                            ->relationship()
                            ->label('Return Item')
                            ->columnSpanFull()
                            ->columns(2)
                            ->reactive()
                            ->schema([
                                Select::make('purchase_receipt_item_id')
                                    ->label('Purchase Receipt Item')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->required()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $purchaseReceiptItem = PurchaseReceiptItem::find($state);
                                        $set('product_id', $purchaseReceiptItem->product_id);
                                        $set('unit_price', $purchaseReceiptItem->purchaseOrderItem->unit_price);
                                    })
                                    ->relationship('purchaseReceiptItem', 'id', function (Builder $query, $get) {
                                        $query->where('purchase_receipt_id', $get('../../purchase_receipt_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (PurchaseReceiptItem $purchaseReceiptItem) {
                                        return "({$purchaseReceiptItem->product->sku}) {$purchaseReceiptItem->product->name}";
                                    })
                                    ->validationMessages([
                                        'required' => 'Purchase receipt item wajib dipilih'
                                    ]),
                                Select::make('product_id')
                                    ->label('Product')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn () => !in_array('all', Auth::user()?->manage_type ?? []))
                                    ->reactive()
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku} {$product->name})";
                                    })
                                    ->validationMessages([
                                        'required' => 'Product wajib dipilih'
                                    ]),
                                TextInput::make('qty_returned')
                                    ->label('Quantity Return')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->minValue(0.01)
                                    ->validationMessages([
                                        'required' => 'Quantity retur wajib diisi',
                                        'numeric' => 'Quantity retur harus berupa angka',
                                        'min' => 'Quantity retur minimal 0.01'
                                    ]),
                                TextInput::make('unit_price')
                                    ->label('Unit Price (Rp.)')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->default(0)
                                    ->required()
                                    ->minValue(0)
                                    ->validationMessages([
                                        'required' => 'Unit price wajib diisi',
                                        'numeric' => 'Unit price harus berupa angka',
                                        'min' => 'Unit price minimal 0'
                                    ]),
                                Textarea::make('reason')
                                    ->label('Reason')
                                    ->nullable(),
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nota_retur')
                    ->label('Nota Return')
                    ->searchable(),
                TextColumn::make('purchaseReceipt.receipt_number')
                    ->label('Receipt / QC')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state) {
                            return $state;
                        }
                        return $record->qualityControl?->qc_number
                            ? '(QC) ' . $record->qualityControl->qc_number
                            : '-';
                    })
                    ->sortable(),
                TextColumn::make('failed_qc_action')
                    ->label('Tindakan QC')
                    ->formatStateUsing(fn ($state) => \App\Models\PurchaseReturn::qcActionOptions()[$state] ?? '-')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'reduce_stock'       => 'danger',
                        'wait_next_delivery' => 'warning',
                        'merge_next_order'   => 'info',
                        default              => 'gray',
                    })
                    ->toggleable(),
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
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_approval' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('return_date')
                    ->dateTime()
                    ->sortable(),
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
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Retur Pembelian & Sinkronisasi Otomatis</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Retur Pembelian digunakan untuk mengembalikan barang ke supplier atau membatalkan penerimaan yang tidak sesuai.</li>' .
                            '<li><strong>Mekanisme:</strong> Biasanya dibuat dari Purchase Receipt; pastikan pilih item yang benar agar stok dan jurnal akuntansi diproses sesuai alur.</li>' .
                            '<li><strong>QC & Stok:</strong> Jika barang sudah masuk inventory setelah QC atau receipt selesai, retur akan mengurangi stok dan membuat jurnal terkait. Jika belum masuk stok (mis. masih proses QC), perilaku retur mengikuti status QC dan policy retur.</li>' .
                            '<li><strong>🔄 Sinkronisasi Otomatis:</strong> Purchase Return otomatis terupdate ketika qty_rejected di Purchase Receipt Item berubah. Jika qty_rejected dihapus, Purchase Return juga ikut terhapus.</li>' .
                            '<li><strong>Approval Workflow:</strong> Beberapa retur memerlukan approval; periksa hak akses dan prosedur sebelum submit.</li>' .
                            '<li><strong>Superadmin Access:</strong> Superadmin dapat mengubah status dan product field secara bebas, user biasa memiliki pembatasan.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->placeholder('Semua Status'),
                SelectFilter::make('cabang')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->kode}) {$record->nama}";
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? [])),
                Filter::make('return_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('return_date_from')
                            ->label('Tanggal Retur Dari'),
                        \Filament\Forms\Components\DatePicker::make('return_date_until')
                            ->label('Tanggal Retur Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['return_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('return_date', '>=', $date),
                            )
                            ->when(
                                $data['return_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('return_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        
                        if ($data['return_date_from'] ?? null) {
                            $indicators[] = 'Tanggal retur dari ' . \Carbon\Carbon::parse($data['return_date_from'])->format('d/m/Y');
                        }
                        
                        if ($data['return_date_until'] ?? null) {
                            $indicators[] = 'Tanggal retur sampai ' . \Carbon\Carbon::parse($data['return_date_until'])->format('d/m/Y');
                        }
                        
                        return $indicators;
                    }),
                SelectFilter::make('created_by')
                    ->label('Dibuat Oleh')
                    ->relationship('createdBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success')
                        ->visible(fn ($record) => in_array($record->status, ['draft', 'rejected'])),
                    \Filament\Tables\Actions\Action::make('submit_for_approval')
                        ->label('Submit for Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'draft')
                        ->action(function ($record) {
                            $service = app(PurchaseReturnService::class);
                            $service->submitForApproval($record);
                            \Filament\Notifications\Notification::make()
                                ->title('Purchase Return submitted for approval')
                                ->success()
                                ->send();
                        }),
                    \Filament\Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === 'pending_approval')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('approval_notes')
                                ->label('Approval Notes')
                                ->nullable(),
                        ])
                        ->action(function ($record, array $data) {
                            $service = app(PurchaseReturnService::class);
                            $service->approve($record, $data);
                            \Filament\Notifications\Notification::make()
                                ->title('Purchase Return approved')
                                ->success()
                                ->send();
                        }),
                    \Filament\Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->status === 'pending_approval')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('rejection_notes')
                                ->label('Rejection Notes')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $service = app(PurchaseReturnService::class);
                            $service->reject($record, $data);
                            \Filament\Notifications\Notification::make()
                                ->title('Purchase Return rejected')
                                ->danger()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->visible(fn ($record) => in_array($record->status, ['draft', 'rejected'])),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Informasi Retur')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('nota_retur')->label('Nota Retur'),
                        TextEntry::make('return_date')->dateTime()->label('Tanggal Retur'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'draft'            => 'gray',
                                'pending_approval' => 'warning',
                                'approved'         => 'success',
                                'rejected'         => 'danger',
                                default            => 'gray',
                            })
                            ->label('Status'),
                        TextEntry::make('cabang')
                            ->formatStateUsing(fn ($state) => "({$state->kode}) {$state->nama}")
                            ->label('Cabang'),
                        TextEntry::make('createdBy.name')->label('Dibuat Oleh'),
                        TextEntry::make('notes')->label('Keterangan')->columnSpanFull(),
                    ]),

                InfolistSection::make('Sumber Retur')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('purchaseReceipt.receipt_number')
                            ->label('Purchase Receipt')
                            ->placeholder('(Retur dari QC – belum ada Receipt)')
                            ->visible(fn ($record) => !$record->isQcReturn()),
                        TextEntry::make('qualityControl.qc_number')
                            ->label('QC Number')
                            ->visible(fn ($record) => $record->isQcReturn()),
                        TextEntry::make('qualityControl.fromModel.purchaseOrder.po_number')
                            ->label('PO Number')
                            ->visible(fn ($record) => $record->isQcReturn()),
                        TextEntry::make('qualityControl.fromModel.purchaseOrder.supplier.name')
                            ->label('Supplier')
                            ->visible(fn ($record) => $record->isQcReturn()),
                    ]),

                InfolistSection::make('Tindakan Penanganan QC')
                    ->columns(2)
                    ->visible(fn ($record) => $record->isQcReturn())
                    ->schema([
                        TextEntry::make('failed_qc_action')
                            ->label('Tindakan yang Dipilih')
                            ->formatStateUsing(fn ($state) => \App\Models\PurchaseReturn::qcActionOptions()[$state] ?? '-')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'reduce_stock'       => 'danger',
                                'wait_next_delivery' => 'warning',
                                'merge_next_order'   => 'info',
                                default              => 'gray',
                            }),
                        TextEntry::make('replacementPurchaseOrder.po_number')
                            ->label('Target PO (Gabung Pesanan)')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->failed_qc_action === 'merge_next_order'),
                        TextEntry::make('supplier_response')
                            ->label('Respon Supplier')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->failed_qc_action === 'wait_next_delivery'),
                        TextEntry::make('tracking_notes')
                            ->label('Tracking Notes')
                            ->columnSpanFull(),
                    ]),

                InfolistSection::make('Item Retur')
                    ->schema([
                        RepeatableEntry::make('purchaseReturnItem')
                            ->label('')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product.name')->label('Produk'),
                                TextEntry::make('product.sku')->label('SKU'),
                                TextEntry::make('qty_returned')->label('Qty Dikembalikan'),
                                TextEntry::make('unit_price')->money('IDR')->label('Harga Satuan'),
                                TextEntry::make('reason')->label('Alasan')->columnSpanFull(),
                            ]),
                    ]),

                InfolistSection::make('Approval')
                    ->columns(2)
                    ->visible(fn ($record) => in_array($record->status, ['approved', 'rejected']))
                    ->schema([
                        TextEntry::make('approvedBy.name')->label('Disetujui Oleh')->placeholder('-'),
                        TextEntry::make('approved_at')->dateTime()->label('Tanggal Persetujuan')->placeholder('-'),
                        TextEntry::make('approval_notes')->label('Catatan Approval')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('rejectedBy.name')->label('Ditolak Oleh')->placeholder('-'),
                        TextEntry::make('rejected_at')->dateTime()->label('Tanggal Penolakan')->placeholder('-'),
                        TextEntry::make('rejection_notes')->label('Alasan Penolakan')->placeholder('-')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            // Include both receipt-based returns (cabang via receipt) and
            // QC-based returns (cabang stored directly on the return record)
            $query->where(function (Builder $q) use ($user) {
                $q->whereHas('purchaseReceipt', function (Builder $inner) use ($user) {
                    $inner->where('cabang_id', $user->cabang_id);
                })->orWhere(function (Builder $inner) use ($user) {
                    $inner->whereNotNull('quality_control_id')
                          ->where('cabang_id', $user->cabang_id);
                });
            });
        }

        return $query;
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
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'view' => ViewPurchaseReturn::route('/{record}'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
