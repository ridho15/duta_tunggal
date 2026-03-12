<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerReturnResource\Pages;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class CustomerReturnResource extends Resource
{
    protected static ?string $model = CustomerReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Customer Return';

    protected static ?string $navigationLabel = 'Customer Return';

    protected static ?string $modelLabel = 'Customer Return';

    protected static ?string $pluralModelLabel = 'Customer Returns';

    protected static ?int $navigationSort = 10;

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('view any customer return') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('create customer return') ?? false;
    }

    // ------------------------------------------------------------------
    // Form
    // ------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Section 1: Return Information ─────────────────────────
            Forms\Components\Section::make('Informasi Return')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('return_number')
                            ->label('No. Return')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated')
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(CustomerReturn::STATUS_LABELS)
                            ->required()
                            ->default(CustomerReturn::STATUS_PENDING)
                            ->disabled(fn (string $operation) => $operation === 'create')
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('return_date')
                            ->label('Tanggal Return')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->options(fn () => Customer::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('invoice_id', null))
                            ->columnSpan(1),

                        Forms\Components\Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(fn () => Cabang::orderBy('nama')->pluck('nama', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn () => Auth::user()?->cabang_id)
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->columnSpan(1),
                    ]),

                    Forms\Components\Select::make('warehouse_id')
                        ->label('Gudang Penerima')
                        ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->hint('Gudang tempat barang retur diterima dan disimpan')
                        ->helperText('Pilih gudang agar stok barang dapat dikembalikan saat proses selesai'),


                    Forms\Components\Select::make('invoice_id')
                        ->label('Invoice Penjualan')
                        ->options(function (Forms\Get $get) {
                            $customerId = $get('customer_id');
                            if (! $customerId) {
                                return [];
                            }
                            return Invoice::query()
                                ->where('from_model_type', 'App\\Models\\SaleOrder')
                                ->where('customer_name', function ($q) use ($customerId) {
                                    $q->select('name')
                                        ->from('customers')
                                        ->where('id', $customerId)
                                        ->limit(1);
                                })
                                ->orderByDesc('invoice_date')
                                ->get()
                                ->mapWithKeys(fn ($inv) => [
                                    $inv->id => $inv->invoice_number . ' — ' . $inv->invoice_date?->format('d/m/Y'),
                                ]);
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('items', []))
                        ->hint('Pilih dulu pelanggan'),

                    Forms\Components\Textarea::make('reason')
                        ->label('Alasan Return')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan Tambahan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            // ── Section 2: Returned Items ─────────────────────────────
            Forms\Components\Section::make('Produk yang Dikembalikan')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->relationship('customerReturnItems')
                        ->schema([
                            Forms\Components\Select::make('invoice_item_id')
                                ->label('Item Invoice')
                                ->options(function (Forms\Get $get) {
                                    $invoiceId = $get('../../invoice_id');
                                    if (! $invoiceId) {
                                        return [];
                                    }
                                    return InvoiceItem::with('product')
                                        ->where('invoice_id', $invoiceId)
                                        ->whereNull('deleted_at')
                                        ->get()
                                        ->mapWithKeys(fn ($item) => [
                                            $item->id => ($item->product?->name ?? '-') . ' (Qty: ' . $item->quantity . ')',
                                        ]);
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state) {
                                        $item = InvoiceItem::find($state);
                                        if ($item) {
                                            $set('product_id', $item->product_id);
                                        }
                                    }
                                })
                                ->columnSpan(4),

                            Forms\Components\Hidden::make('product_id'),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty Return')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(0.01)
                                ->step(0.01)
                                ->columnSpan(2),

                            Forms\Components\Textarea::make('problem_description')
                                ->label('Deskripsi Masalah')
                                ->required()
                                ->rows(2)
                                ->columnSpan(6),

                            // QC fields – editable after QC inspection
                            Forms\Components\Select::make('qc_result')
                                ->label('Hasil QC')
                                ->options([
                                    'pass' => 'Lolos',
                                    'fail' => 'Gagal',
                                ])
                                ->columnSpan(2),

                            Forms\Components\Textarea::make('qc_notes')
                                ->label('Catatan QC')
                                ->rows(2)
                                ->columnSpan(4),

                            Forms\Components\Select::make('decision')
                                ->label('Keputusan')
                                ->options(CustomerReturnItem::DECISION_LABELS)
                                ->columnSpan(2),
                        ])
                        ->columns(6)
                        ->reorderable(false)
                        ->addActionLabel('+ Tambah Produk'),
                ]),
        ]);
    }

    // ------------------------------------------------------------------
    // Table
    // ------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('return_number')
                    ->label('No. Return')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('No. Invoice')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('return_date')
                    ->label('Tanggal Return')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => CustomerReturn::STATUS_LABELS[$state] ?? $state)
                    ->colors([
                        'warning' => CustomerReturn::STATUS_PENDING,
                        'info'    => CustomerReturn::STATUS_RECEIVED,
                        'primary' => CustomerReturn::STATUS_QC_INSPECTION,
                        'success' => fn ($state) => in_array($state, [
                            CustomerReturn::STATUS_APPROVED,
                            CustomerReturn::STATUS_COMPLETED,
                        ]),
                        'danger'  => CustomerReturn::STATUS_REJECTED,
                    ]),

                Tables\Columns\TextColumn::make('customerReturnItems_count')
                    ->label('Jml Item')
                    ->counts('customerReturnItems')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(CustomerReturn::STATUS_LABELS),

                Tables\Filters\Filter::make('return_date')
                    ->label('Tanggal Return')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('return_date', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->whereDate('return_date', '<=', $d));
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Receive action
                Tables\Actions\Action::make('mark_received')
                    ->label('Tandai Diterima')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan')
                    ->modalDescription('Tandai bahwa barang return sudah diterima oleh DT?')
                    ->visible(fn (CustomerReturn $record) => $record->status === CustomerReturn::STATUS_PENDING)
                    ->action(function (CustomerReturn $record) {
                        $record->update([
                            'status'      => CustomerReturn::STATUS_RECEIVED,
                            'received_by' => Auth::id(),
                            'received_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Barang Return Diterima')
                            ->success()
                            ->send();
                    }),

                // Start QC action
                Tables\Actions\Action::make('start_qc')
                    ->label('Mulai QC')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Mulai Inspeksi QC')
                    ->modalDescription('Tandai bahwa inspeksi QC sedang berlangsung?')
                    ->visible(fn (CustomerReturn $record) => $record->status === CustomerReturn::STATUS_RECEIVED)
                    ->action(function (CustomerReturn $record) {
                        $record->update([
                            'status'          => CustomerReturn::STATUS_QC_INSPECTION,
                            'qc_inspected_by' => Auth::id(),
                            'qc_inspected_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Inspeksi QC Dimulai')
                            ->success()
                            ->send();
                    }),

                // Approve action
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Customer Return')
                    ->visible(fn (CustomerReturn $record) => $record->status === CustomerReturn::STATUS_QC_INSPECTION)
                    ->action(function (CustomerReturn $record) {
                        $record->update([
                            'status'      => CustomerReturn::STATUS_APPROVED,
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Customer Return Disetujui')
                            ->success()
                            ->send();
                    }),

                // Reject action
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Customer Return')
                    ->visible(fn (CustomerReturn $record) => $record->status === CustomerReturn::STATUS_QC_INSPECTION)
                    ->action(function (CustomerReturn $record) {
                        $record->update([
                            'status'      => CustomerReturn::STATUS_REJECTED,
                            'rejected_by' => Auth::id(),
                            'rejected_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Customer Return Ditolak')
                            ->danger()
                            ->send();
                    }),

                // Complete action — triggers stock restoration (mirrors QC reject flow)
                Tables\Actions\Action::make('complete')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-flag')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Selesaikan Customer Return')
                    ->modalDescription('Proses ini akan mengembalikan stok barang (keputusan Perbaikan/Penggantian) ke gudang penerima dan membuat jurnal akuntansi. Tidak dapat dibatalkan.')
                    ->visible(fn (CustomerReturn $record) => $record->status === CustomerReturn::STATUS_APPROVED
                        && ! $record->stock_restored_at)
                    ->action(function (CustomerReturn $record) {
                        try {
                            app(CustomerReturnService::class)->processCompletion($record);
                            \Filament\Notifications\Notification::make()
                                ->title('Customer Return Selesai')
                                ->body('Stok barang berhasil dikembalikan ke gudang.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Gagal Menyelesaikan Return')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ------------------------------------------------------------------
    // Infolist (View page)
    // ------------------------------------------------------------------

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informasi Return')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('return_number')
                            ->label('No. Return')
                            ->weight('bold')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => CustomerReturn::STATUS_LABELS[$state] ?? $state)
                            ->color(fn ($state) => CustomerReturn::STATUS_COLORS[$state] ?? 'secondary'),

                        Infolists\Components\TextEntry::make('return_date')
                            ->label('Tanggal Return')
                            ->date('d/m/Y'),
                    ]),

                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Pelanggan'),

                        Infolists\Components\TextEntry::make('invoice.invoice_number')
                            ->label('No. Invoice'),
                    ]),

                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('warehouse.name')
                            ->label('Gudang Penerima')
                            ->placeholder('Belum ditentukan'),

                        Infolists\Components\TextEntry::make('stock_restored_at')
                            ->label('Stok Dikembalikan Pada')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),

                    Infolists\Components\TextEntry::make('reason')
                        ->label('Alasan Return')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('notes')
                        ->label('Catatan')
                        ->columnSpanFull()
                        ->placeholder('-'),
                ]),

            Infolists\Components\Section::make('Produk yang Dikembalikan')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('customerReturnItems')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product.name')
                                ->label('Produk'),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('Qty'),
                            Infolists\Components\TextEntry::make('problem_description')
                                ->label('Masalah'),
                            Infolists\Components\TextEntry::make('qc_result')
                                ->label('Hasil QC')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state === 'pass' ? 'Lolos' : ($state === 'fail' ? 'Gagal' : '-'))
                                ->color(fn ($state) => $state === 'pass' ? 'success' : ($state === 'fail' ? 'danger' : 'secondary')),
                            Infolists\Components\TextEntry::make('qc_notes')
                                ->label('Catatan QC')
                                ->placeholder('-'),
                            Infolists\Components\TextEntry::make('decision')
                                ->label('Keputusan')
                                ->badge()
                                ->formatStateUsing(fn ($state) => CustomerReturnItem::DECISION_LABELS[$state] ?? '-')
                                ->color(fn ($state) => match ($state) {
                                    'repair'  => 'warning',
                                    'replace' => 'info',
                                    'reject'  => 'danger',
                                    default   => 'secondary',
                                }),
                        ])
                        ->columns(6),
                ]),

            Infolists\Components\Section::make('Log Proses')
                ->icon('heroicon-o-clock')
                ->collapsed()
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('receivedBy.name')
                            ->label('Diterima Oleh')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('received_at')
                            ->label('Tanggal Terima')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('qcInspectedBy.name')
                            ->label('Diinspeksi Oleh')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('qc_inspected_at')
                            ->label('Tanggal QC')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('approvedBy.name')
                            ->label('Disetujui Oleh')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->label('Tanggal Setuju')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),
                ]),
        ]);
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['invoice', 'customer', 'cabang', 'customerReturnItems.product']);
    }

    // ------------------------------------------------------------------
    // Pages
    // ------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomerReturns::route('/'),
            'create' => Pages\CreateCustomerReturn::route('/create'),
            'view'   => Pages\ViewCustomerReturn::route('/{record}'),
            'edit'   => Pages\EditCustomerReturn::route('/{record}/edit'),
        ];
    }
}
