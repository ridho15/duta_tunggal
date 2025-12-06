<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoucherRequestResource\Pages;
use App\Filament\Resources\VoucherRequestResource\RelationManagers;
use App\Models\VoucherRequest;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Services\VoucherRequestService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class VoucherRequestResource extends Resource
{
    protected static ?string $model = VoucherRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationGroup = 'Finance - Akuntansi';
    
    protected static ?string $navigationLabel = 'Pengajuan Voucher';
    
    protected static ?string $modelLabel = 'Pengajuan Voucher';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Voucher')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('voucher_number')
                                    ->label('Nomor Pengajuan')
                                    ->required()
                                    ->dehydrated()
                                    ->default(fn() => app(VoucherRequestService::class)->generateVoucherNumber())
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->suffixAction(
                                        Action::make('generate')
                                            ->label('Generate')
                                            ->icon('heroicon-o-arrow-path')
                                            ->action(function (callable $set) {
                                                $set('voucher_number', app(VoucherRequestService::class)->generateVoucherNumber());
                                            })
                                            ->tooltip('Generate nomor pengajuan'),
                                    ),

                                Forms\Components\DatePicker::make('voucher_date')
                                    ->label('Tanggal Pengajuan')
                                    ->required()
                                    ->default(now())
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->helperText('Bisa backdate sesuai kebutuhan'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Nominal')
                                    ->required()
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        // Format saat blur
                                        if ($state) {
                                            $set('amount', number_format((float) $state, 2, '.', ''));
                                        }
                                    }),

                                Forms\Components\TextInput::make('related_party')
                                    ->label('Pihak Terkait')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Nama customer/supplier/lainnya')
                                    ->helperText('Nama pihak yang terkait dengan voucher ini'),

                                Select::make('cabang_id')
                                    ->label('Cabang')
                                    ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                                    ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                                    ->nullable()
                                    ->helperText('Opsional: pilih cabang terkait'),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'pending' => 'Menunggu Persetujuan',
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak',
                                        'cancelled' => 'Dibatalkan',
                                    ])
                                    ->required()
                                    ->default('draft')
                                    ->disabled(fn(?VoucherRequest $record) => $record && !$record->canBeEdited())
                                    ->helperText(fn(?VoucherRequest $record) => 
                                        $record && !$record->canBeEdited() 
                                            ? 'Status tidak dapat diubah setelah diajukan' 
                                            : 'Status pengajuan'
                                    ),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->placeholder('Jelaskan tujuan atau detail voucher ini'),
                    ]),

                Forms\Components\Section::make('Informasi Approval')
                    ->schema([
                        Forms\Components\Placeholder::make('approved_info')
                            ->label('Status Approval')
                            ->content(fn(?VoucherRequest $record): string => 
                                $record 
                                    ? ($record->approved_by 
                                        ? 'Diproses oleh: ' . $record->approver?->name . ' pada ' . $record->approved_at?->format('d/m/Y H:i')
                                        : 'Belum diproses')
                                    : 'Belum ada data approval'
                            ),

                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Catatan Approval/Penolakan')
                            ->rows(2)
                            ->maxLength(65535)
                            ->disabled()
                            ->visible(fn(?VoucherRequest $record) => $record && $record->approval_notes),
                    ])
                    ->visible(fn(?VoucherRequest $record) => $record && $record->approved_by)
                    ->collapsed(),

                Forms\Components\Section::make('Informasi Sistem')
                    ->schema([
                        Forms\Components\Placeholder::make('created_info')
                            ->label('Dibuat Oleh')
                            ->content(fn(?VoucherRequest $record): string => 
                                $record 
                                    ? ($record->creator?->name ?? 'N/A') . ' pada ' . $record->created_at?->format('d/m/Y H:i')
                                    : 'Data baru'
                            ),

                        Forms\Components\Placeholder::make('transaction_link')
                            ->label('Transaksi Kas/Bank')
                            ->content(fn(?VoucherRequest $record): string => 
                                $record && $record->cash_bank_transaction_id
                                    ? 'Linked to: ' . ($record->cashBankTransaction?->number ?? 'N/A')
                                    : 'Belum ada transaksi terkait'
                            ),

                                Forms\Components\Placeholder::make('requested_owner_info')
                                    ->label('Diminta ke Owner')
                                    ->content(fn(?VoucherRequest $record): string =>
                                        $record && $record->requested_to_owner_by
                                            ? 'Diminta oleh: ' . ($record->requestedBy?->name ?? 'N/A') . ' pada ' . ($record->requested_to_owner_at?->format('d/m/Y H:i') ?? '-')
                                            : 'Belum diminta ke Owner'
                                    ),
                    ])
                    ->visible(fn(?VoucherRequest $record) => $record !== null)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher_number')
                    ->label('Nomor Voucher')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('voucher_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('related_party')
                    ->label('Pihak Terkait')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('total_amount_used')
                    ->label('Sudah Digunakan')
                    ->money('IDR')
                    ->getStateUsing(fn ($record) => $record->getTotalAmountUsed())
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Digunakan'),
                    ]),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Sisa')
                    ->money('IDR')
                    ->getStateUsing(fn ($record) => $record->getRemainingAmount())
                    ->sortable()
                    ->toggleable()
                    ->color(fn ($record) => $record->getRemainingAmount() > 0 ? 'success' : 'gray')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Sisa'),
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                    ])
                    ->icons([
                        'heroicon-o-document' => 'draft',
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'approved',
                        'heroicon-o-x-circle' => 'rejected',
                        'heroicon-o-ban' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state): string => match($state) {
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('cash_bank_transactions_count')
                    ->label('Jml Transaksi')
                    ->getStateUsing(fn ($record) => $record->cashBankTransactions()->count())
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->limit(20)
                    ->formatStateUsing(fn($state, $record) => $record?->cabang?->kode ? ($record->cabang->kode . ' - ' . $record->cabang->nama) : '-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->searchable()
                    ->toggleable()
                    ->limit(20)
                    ->formatStateUsing(fn($state, $record) => $record?->creator?->name ?? '-'),

                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Disetujui Oleh')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20),

                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Tgl Approval')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('cash_bank_transaction_id')
                    ->label('Link Transaksi')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Dihapus')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('voucher_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('voucher_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('voucher_date', '<=', $date),
                            );
                    }),

                Tables\Filters\SelectFilter::make('cabang_id')
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
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('submit')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->form([
                        Forms\Components\Checkbox::make('request_to_owner')
                            ->label('Minta approval ke Owner')
                            ->helperText('Centang untuk mengirim notifikasi khusus ke user dengan role Owner'),
                    ])
                    ->visible(fn(VoucherRequest $record) => $record->canBeSubmitted() && Auth::user()?->can('submit', $record))
                    ->authorize(fn(VoucherRequest $record) => Auth::user()?->can('submit', $record))
                    ->action(function (VoucherRequest $record, array $data) {
                        try {
                            $notifyOwner = !empty($data['request_to_owner']);
                            app(VoucherRequestService::class)->submitForApproval($record, $notifyOwner);

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Voucher berhasil diajukan untuk persetujuan' . ($notifyOwner ? ' dan notifikasi dikirim ke Owner' : ''))
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Catatan Approval')
                            ->rows(3)
                            ->placeholder('Opsional: tambahkan catatan'),

                        Forms\Components\Checkbox::make('auto_create_transaction')
                            ->label('Buat Transaksi Kas/Bank otomatis')
                            ->helperText('Jika dicentang, sistem akan membuat transaksi kas/bank untuk pembayaran voucher')
                            ->reactive(),

                        Forms\Components\Select::make('transaction_type')
                            ->label('Tipe Transaksi')
                            ->options([
                                'cash_out' => 'Kas Keluar',
                                'cash_in' => 'Kas Masuk',
                                'bank_out' => 'Bank Keluar',
                                'bank_in' => 'Bank Masuk',
                            ])
                            ->default('cash_out')
                            ->visible(fn($get) => $get('auto_create_transaction'))
                            ->required(fn($get) => $get('auto_create_transaction')),

                        Forms\Components\Select::make('cash_bank_account_id')
                            ->label('Akun Kas/Bank')
                            ->options(fn () => Schema::hasTable('cash_bank_accounts') ? DB::table('cash_bank_accounts')->pluck('name', 'id') : collect())
                            ->searchable()
                            ->visible(fn($get) => $get('auto_create_transaction'))
                            ->disabled(!Schema::hasTable('cash_bank_accounts'))
                            ->helperText(fn() => Schema::hasTable('cash_bank_accounts') ? null : 'Tabel `cash_bank_accounts` tidak ditemukan. Konfigurasi akun kas/bank belum tersedia.')
                            ->required(fn($get) => $get('auto_create_transaction') && Schema::hasTable('cash_bank_accounts'))
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Jika tabel cash_bank_accounts ada, coba isi account_coa_id dari mapping coa_id
                                try {
                                    if (!$state) {
                                        return;
                                    }

                                    if (Schema::hasTable('cash_bank_accounts') && Schema::hasColumn('cash_bank_accounts', 'coa_id')) {
                                        $coaId = DB::table('cash_bank_accounts')->where('id', $state)->value('coa_id');
                                        if ($coaId) {
                                            $set('account_coa_id', $coaId);
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // swallow - fallback to manual selection
                                }
                            }),

                        Forms\Components\Select::make('account_coa_id')
                            ->label('Akun COA (Kas/Bank)')
                            ->options(fn () => ChartOfAccount::orderBy('code')->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn($get) => $get('auto_create_transaction'))
                            ->required(fn($get) => $get('auto_create_transaction')),

                        Forms\Components\Select::make('offset_coa_id')
                            ->label('Akun COA Lawan')
                            ->options(fn () => ChartOfAccount::orderBy('code')->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn($get) => $get('auto_create_transaction'))
                            ->required(fn($get) => $get('auto_create_transaction')),

                        Forms\Components\Checkbox::make('auto_post')
                            ->label('Posting otomatis ke jurnal')
                            ->helperText('Jika dicentang, entri jurnal akan dibuat otomatis melalui modul Kas & Bank')
                            ->visible(fn($get) => $get('auto_create_transaction')),
                    ])
                    ->visible(fn(VoucherRequest $record) => $record->canBeApproved() && Auth::user()?->can('approve', $record))
                    ->authorize(fn(VoucherRequest $record) => Auth::user()?->can('approve', $record))
                    ->action(function (VoucherRequest $record, array $data) {
                        try {
                            app(VoucherRequestService::class)->approve($record, $data);

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Voucher berhasil disetujui')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn(VoucherRequest $record) => $record->canBeRejected() && Auth::user()?->can('reject', $record))
                    ->authorize(fn(VoucherRequest $record) => Auth::user()?->can('reject', $record))
                    ->action(function (VoucherRequest $record, array $data) {
                        try {
                            app(VoucherRequestService::class)->reject($record, $data['reason']);
                            
                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Voucher berhasil ditolak')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\Action::make('view_cash_bank_transactions')
                    ->label('Lihat Transaksi Kas/Bank')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->url(fn (VoucherRequest $record) => route('filament.admin.resources.cash-bank-transactions.index', [
                        'tableFilters[voucher_request_id][value]' => $record->id
                    ]))
                    ->visible(fn (VoucherRequest $record) => $record->cashBankTransactions()->count() > 0),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn(VoucherRequest $record) => $record->canBeEdited()),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(VoucherRequest $record) => $record->canBeCancelled()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Voucher Request</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Voucher Request adalah pengajuan untuk pengeluaran atau penerimaan uang yang memerlukan approval sebelum diproses menjadi transaksi kas/bank.</li>' .
                            '<li><strong>Status Flow:</strong> Draft → Request Approve → Approved → Paid/Cleared. Atau bisa Request Cancel → Cancelled.</li>' .
                            '<li><strong>Validasi:</strong> Amount, COA, dan detail lainnya. Approval diperlukan sebelum dapat dibayarkan.</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah jika draft), <em>Delete</em> (hapus), <em>View Cash Bank Transactions</em> (lihat transaksi terkait).</li>' .
                            '<li><strong>Filters:</strong> Status, Date Range, Amount Range, COA, dll.</li>' .
                            '<li><strong>Permissions:</strong> <em>request voucher</em> untuk membuat request, <em>response voucher</em> untuk approve/reject.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan Cash Bank Transactions dan Journal Entries untuk pencatatan keuangan.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
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
            'index' => Pages\ListVoucherRequests::route('/'),
            'create' => Pages\CreateVoucherRequest::route('/create'),
            'edit' => Pages\EditVoucherRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->where('cabang_id', $user->cabang_id);
        }

        return $query;
    }
    
    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasTable('voucher_requests')) {
            return null;
        }

        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        if (! Schema::hasTable('voucher_requests')) {
            return null;
        }

        return static::getModel()::where('status', 'pending')->count() > 0
            ? 'warning'
            : null;
    }
}
