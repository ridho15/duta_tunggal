<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashBankTransactionResource\Pages;
use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\VoucherRequest;
use App\Services\CashBankService;
use App\Services\VoucherRequestService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class CashBankTransactionResource extends Resource
{
    protected static ?string $model = CashBankTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Finance - Pembayaran';
    protected static ?string $modelLabel = 'Transaksi Kas & Bank';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Fieldset::make('Form')
                ->schema([
                    Grid::make(12)->schema([
                        TextInput::make('number')
                            ->label('No Bukti')
                            ->helperText('Otomatis terisi, bisa diubah manual jika diperlukan')
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2)
                            ->default(fn() => app(CashBankService::class)->generateNumber('CB'))
                            ->validationMessages([
                                'unique' => 'Nomor bukti sudah digunakan'
                            ]),
                        Select::make('numbering_format')
                            ->label('Format Penomoran')
                            ->options([
                                'default' => 'CB-YYYYMMDD-0001 (Default)',
                                'simple' => 'CB0001 (Sederhana)',
                                'monthly' => 'CB-YYYYMM-0001 (Bulanan)',
                                'yearly' => 'CB-YYYY-0001 (Tahunan)',
                            ])
                            ->default('default')
                            ->columnSpan(2)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $prefix = 'CB';
                                    $service = app(CashBankService::class);
                                    $number = $service->generateNumber($prefix, $state);
                                    $set('number', $number);
                                }
                            }),
                        DatePicker::make('date')->label('Tanggal')->required()->default(now())->columnSpan(3)
                            ->validationMessages([
                                'required' => 'Tanggal wajib diisi'
                            ]),
                        Select::make('type')->label('Tipe')->options([
                            'cash_in' => 'Kas Masuk',
                            'cash_out' => 'Kas Keluar',
                            'bank_in' => 'Bank Masuk',
                            'bank_out' => 'Bank Keluar',
                        ])->required()->columnSpan(3)
                            ->validationMessages([
                                'required' => 'Tipe transaksi wajib dipilih'
                            ]),
                        TextInput::make('amount')
                            ->label('Jumlah Total')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->indonesianMoney()
                            ->columnSpan(2)
                            ->readonly(fn(callable $get) => count($get('transactionDetails') ?? []) > 0)
                            ->helperText(fn(callable $get) => count($get('transactionDetails') ?? []) > 0 ? 'Jumlah otomatis dari total rincian' : 'Masukkan jumlah transaksi')
                            ->validationMessages([
                                'required' => 'Jumlah wajib diisi',
                                'numeric' => 'Jumlah harus berupa angka',
                                'min' => 'Jumlah minimal 0.01'
                            ]),
                        Select::make('account_coa_id')->label('Kas/Bank (COA)')->searchable()
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->where('code', 'like', '1111%')
                                    ->orWhere('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->required()->columnSpan(6)
                            ->validationMessages([
                                'required' => 'Kas/Bank (COA) wajib dipilih'
                            ]),
                        Select::make('offset_coa_id')->label('Rincian Pembayaran (COA)')->searchable()
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->whereNot('code', 'like', '1111%')
                                    ->whereNot('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->required()->columnSpan(6)
                            ->rule('different:account_coa_id')
                            ->validationMessages([
                                'required' => 'Rincian Pembayaran (COA) wajib dipilih',
                                'different' => 'Rincian Pembayaran (COA) tidak boleh sama dengan Kas/Bank (COA)'
                            ]),
                        TextInput::make('counterparty')->label('Pihak Terkait')->columnSpan(4),
                        Textarea::make('description')->label('Keterangan')->columnSpan(8),
                        FileUpload::make('attachment_path')->label('Lampiran')->directory('cashbank')->columnSpan(12),
                    ]),
                ]),
            Section::make('Voucher (Opsional)')
                ->schema([
                    Grid::make(12)->schema([
                        Select::make('voucher_request_id')
                            ->label('Pilih Voucher')
                            ->options(function () {
                                $voucherService = app(VoucherRequestService::class);
                                return $voucherService->getAvailableVouchers()->mapWithKeys(function ($voucher) {
                                    $remaining = $voucher->getRemainingAmount();
                                    return [$voucher->id => $voucher->voucher_number . ' - ' . $voucher->related_party . ' (' . formatCurrency($remaining) . ' tersisa)'];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->columnSpan(6)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $voucher = VoucherRequest::find($state);
                                    if ($voucher) {
                                        $set('voucher_number', $voucher->voucher_number);
                                        $set('amount', $voucher->getRemainingAmount());
                                        $set('description', 'Pembayaran Voucher: ' . $voucher->voucher_number . ' - ' . $voucher->related_party);
                                        $set('counterparty', $voucher->related_party);
                                    }
                                }
                            }),
                        TextInput::make('voucher_number')
                            ->label('Nomor Voucher Manual')
                            ->helperText('Masukkan nomor voucher jika tidak memilih dari daftar')
                            ->columnSpan(6)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state && !$get('voucher_request_id')) {
                                    $voucher = VoucherRequest::where('voucher_number', $state)->approved()->first();
                                    if ($voucher) {
                                        $set('voucher_request_id', $voucher->id);
                                        $set('amount', $voucher->getRemainingAmount());
                                        $set('description', 'Pembayaran Voucher: ' . $voucher->voucher_number . ' - ' . $voucher->related_party);
                                        $set('counterparty', $voucher->related_party);
                                    }
                                }
                            }),
                        Select::make('voucher_usage_type')
                            ->label('Tipe Penggunaan Voucher')
                            ->options([
                                'single_use' => 'Single Use (gunakan sekali)',
                                'multi_use' => 'Multi Use (bisa digunakan berkali-kali)',
                            ])
                            ->default('single_use')
                            ->required()
                            ->visible(fn(callable $get) => $get('voucher_request_id') || $get('voucher_number'))
                            ->columnSpan(6)
                            ->validationMessages([
                                'required' => 'Tipe penggunaan voucher wajib dipilih'
                            ]),
                        TextInput::make('voucher_amount_used')
                            ->label('Jumlah Voucher yang Digunakan')
                            ->numeric()
                            ->minValue(0.01)
                            ->visible(fn(callable $get) => $get('voucher_request_id') || $get('voucher_number'))
                            ->columnSpan(6)
                            ->default(fn(callable $get) => $get('amount'))
                            ->rules([
                                function (callable $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $voucherId = $get('voucher_request_id');
                                        $voucherNumber = $get('voucher_number');
                                        $usageType = $get('voucher_usage_type');

                                        if (!$voucherId && !$voucherNumber) {
                                            return; // Skip validation if no voucher selected
                                        }

                                        $voucher = $voucherId ? VoucherRequest::find($voucherId) : VoucherRequest::where('voucher_number', $voucherNumber)->approved()->first();

                                        if (!$voucher) {
                                            $fail('Voucher tidak ditemukan atau belum disetujui.');
                                            return;
                                        }

                                        $voucherService = app(VoucherRequestService::class);
                                        try {
                                            $voucherService->validateVoucherUsage($voucher, $value, $usageType);
                                        } catch (\Exception $e) {
                                            $fail($e->getMessage());
                                        }
                                    };
                                },
                            ])
                            ->validationMessages([
                                'numeric' => 'Jumlah voucher harus berupa angka',
                                'min' => 'Jumlah voucher minimal 0.01'
                            ]),
                    ]),
                ])
                ->visible(fn(callable $get) => true) // Always show but fields inside are conditionally visible
                ->collapsible(),
            Section::make('Rincian Transaksi')
                ->description('Opsional: Tambahkan rincian breakdown untuk transaksi ini. Total rincian akan otomatis mengupdate jumlah transaksi utama.')
                ->schema([
                    Placeholder::make('breakdown_info')
                        ->label('')
                        ->content('Tambahkan rincian transaksi untuk memecah jumlah ke dalam beberapa akun COA. Total semua rincian akan dijadikan jumlah transaksi utama.'),
                    Repeater::make('transactionDetails')
                        ->relationship('transactionDetails')
                        ->schema([
                            Select::make('chart_of_account_id')
                                ->label('Akun COA')
                                ->options(function () {
                                    return ChartOfAccount::where('is_active', true)
                                        ->orderBy('code')
                                        ->get()
                                        ->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]);
                                })
                                ->required()
                                ->searchable()
                                ->preload()
                                ->columnSpan(4)
                                ->validationMessages([
                                    'required' => 'Akun COA wajib dipilih'
                                ]),
                            TextInput::make('description')
                                ->label('Deskripsi')
                                ->placeholder('Contoh: Buku tulis, Pensil, dll.')
                                ->required()
                                ->columnSpan(3)
                                ->validationMessages([
                                    'required' => 'Deskripsi wajib diisi'
                                ]),
                            TextInput::make('amount')
                                ->label('Jumlah')
                                ->numeric()
                                ->minValue(-999999999)
                                ->maxValue(999999999)
                                ->required()
                                ->indonesianMoney()
                                ->helperText('Gunakan nilai negatif (-) untuk pengurang')
                                ->columnSpan(2)
                                ->validationMessages([
                                    'required' => 'Jumlah wajib diisi',
                                    'numeric' => 'Jumlah harus berupa angka',
                                    'min' => 'Jumlah minimal -999.999.999',
                                    'max' => 'Jumlah maksimal 999.999.999'
                                ]),
                            TextInput::make('ntpn')
                                ->label('NTPN')
                                ->helperText('Nomor Transaksi Penerimaan Negara untuk PPH 22 Import')
                                ->columnSpan(3)
                                ->suffixAction(
                                    FormAction::make('generateNTPN')
                                        ->label('Generate')
                                        ->icon('heroicon-o-cog-6-tooth')
                                        ->tooltip('Generate NTPN untuk PPH 22 Import')
                                        ->action(function (callable $set) {
                                            // Generate NTPN format: NTPN + YYYYMMDD + random 6 digits
                                            $date = now()->format('Ymd');
                                            $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                                            $ntpn = 'NTPN' . $date . $random;
                                            $set('ntpn', $ntpn);
                                        })
                                ),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->addActionLabel('Tambah Rincian')
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['description'] ?? ChartOfAccount::find($state['chart_of_account_id'] ?? null)?->name ?? 'Rincian Baru')
                        ->afterStateUpdated(function ($state, callable $set) {
                            $total = collect($state ?? [])->sum('amount');
                            if ($total > 0) {
                                $set('../../amount', abs($total)); // Use absolute value for main amount
                            }
                        })
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                $total = collect($value ?? [])->sum('amount');
                                if ($total == 0) {
                                    $fail('Total rincian tidak boleh 0');
                                }
                            },
                        ]),
                ])
                ->visible(fn(callable $get) => true) // Always visible, but optional to use
                ->collapsed() // Start collapsed since it's optional
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->searchable()->sortable(),
                TextColumn::make('date')->date('d/m/Y')->label('Tanggal')->sortable(),
                TextColumn::make('type')->badge()->label('Tipe')->searchable(),
                TextColumn::make('voucherRequest.number')
                    ->label('No. Voucher')
                    ->placeholder('-')
                    ->formatStateUsing(fn($state, $record) => $record->voucherRequest ? $record->voucherRequest->number : '-')
                    ->searchable(),
                TextColumn::make('accountCoa.code')->label('Akun Kas/Bank')->formatStateUsing(fn($state, $record) => $record->accountCoa->code . ' - ' . $record->accountCoa->name)->searchable(),
                TextColumn::make('offsetCoa.code')->label('Lawan Akun')->formatStateUsing(fn($state, $record) => $record->offsetCoa->code . ' - ' . $record->offsetCoa->name)->searchable(),
                TextColumn::make('amount')->money('IDR')->label('Jumlah')->sortable(),
                TextColumn::make('transactionDetails')
                    ->label('Rincian Transaksi')
                    ->formatStateUsing(function ($record) {
                        if ($record->transactionDetails->isEmpty()) {
                            return '-';
                        }

                        return $record->transactionDetails->map(function ($detail) {
                            $info = $detail->description . ' (' . $detail->chartOfAccount->code . '): ' . formatCurrency($detail->amount);
                            if ($detail->ntpn) {
                                $info .= ' [NTPN: ' . $detail->ntpn . ']';
                            }
                            return $info;
                        })->join('; ');
                    })
                    ->wrap()
                    ->limit(50),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe Transaksi')
                    ->options([
                        'debit' => 'Debit',
                        'credit' => 'Credit',
                    ]),
                SelectFilter::make('account_coa_id')
                    ->label('Akun Kas/Bank')
                    ->relationship('accountCoa', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('offset_coa_id')
                    ->label('Lawan Akun')
                    ->relationship('offsetCoa', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    EditAction::make(),
                    TableAction::make('post_to_journal')
                        ->label('Posting ke Jurnal')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Posting ke Jurnal')
                        ->modalDescription('Apakah Anda yakin ingin memposting transaksi ini ke jurnal?')
                        ->modalSubmitActionLabel('Ya, Posting')
                        ->action(function (CashBankTransaction $record) {
                            try {
                                app(\App\Services\CashBankService::class)->postTransaction($record);
                                Notification::make()
                                    ->title('Berhasil diposting ke jurnal')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal posting ke jurnal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    TableAction::make('view_voucher_request')
                        ->label('Lihat Voucher Request')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->url(fn(CashBankTransaction $record) => route('filament.admin.resources.voucher-requests.view', $record->voucher_request_id))
                        ->visible(fn(CashBankTransaction $record) => !is_null($record->voucher_request_id)),
                    TableAction::make('view_reconciliation')->label('Lihat Rekonsiliasi Bank')->icon('heroicon-o-eye')
                        ->url(fn($record) => '/bank-reconciliations?tableFilters[coa_id][value]=' . ($record->accountCoa && str_starts_with($record->accountCoa->code, '111') ? $record->accountCoa->id : ''))
                        ->visible(fn($record) => $record->accountCoa && str_starts_with($record->accountCoa->code, '111')),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Transaksi Kas & Bank</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Transaksi Kas & Bank adalah record semua transaksi yang terjadi pada rekening kas dan bank perusahaan, baik penerimaan maupun pengeluaran dana.</li>' .
                    '<li><strong>Tipe Transaksi:</strong> <em>Debit</em> (penerimaan dana ke rekening kas/bank) atau <em>Credit</em> (pengeluaran dana dari rekening kas/bank).</li>' .
                    '<li><strong>Komponen Utama:</strong> <em>Number</em> (nomor bukti transaksi), <em>Date</em> (tanggal transaksi), <em>Account COA</em> (rekening kas/bank), <em>Offset COA</em> (lawan rekening), <em>Amount</em> (nominal transaksi).</li>' .
                    '<li><strong>Transaction Details:</strong> Rincian akun anak yang terlibat dalam transaksi, termasuk NTPN untuk transaksi perpajakan. Mendukung multiple account details dalam satu transaksi.</li>' .
                    '<li><strong>Voucher Integration:</strong> Terintegrasi dengan Voucher Request - transaksi dapat dibuat dari voucher yang telah disetujui. Menampilkan nomor voucher sebagai referensi.</li>' .
                    '<li><strong>Validasi:</strong> <em>COA Validation</em> - memastikan rekening kas/bank valid. <em>Balance Check</em> - verifikasi saldo rekening untuk transaksi credit. <em>Amount Validation</em> - memastikan amount positif.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan <em>Voucher Request</em> (sumber transaksi), <em>Chart of Account</em> (rekening), <em>Journal Entry</em> (otomatis buat jurnal), <em>Bank Reconciliation</em> (rekonsiliasi bank), dan <em>Cash Flow</em> (laporan arus kas).</li>' .
                    '<li><strong>Actions:</strong> <em>View/Edit</em> (lihat/ubah transaksi), <em>Delete</em> (hapus transaksi), <em>View Reconciliation</em> (lihat rekonsiliasi bank untuk rekening bank), <em>View Voucher</em> (lihat voucher request terkait).</li>' .
                    '<li><strong>Permissions:</strong> <em>view any cash bank transaction</em>, <em>create cash bank transaction</em>, <em>update cash bank transaction</em>, <em>delete cash bank transaction</em>, <em>restore cash bank transaction</em>, <em>force-delete cash bank transaction</em>.</li>' .
                    '<li><strong>Journal Impact:</strong> Otomatis membuat journal entry dengan debit/credit sesuai tipe transaksi. Transaction details akan membuat multiple journal lines.</li>' .
                    '<li><strong>Reporting:</strong> Menyediakan data untuk cash flow statement, bank reconciliation, petty cash management, dan financial position tracking.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Transaction Details')
                    ->schema([
                        TextEntry::make('number')->label('Transaction Number'),
                        TextEntry::make('date')->date()->label('Transaction Date'),
                        TextEntry::make('type')->label('Type')->badge(),
                        TextEntry::make('amount')->money('IDR')->label('Amount'),
                        TextEntry::make('accountCoa.code')->label('Account COA'),
                        TextEntry::make('accountCoa.name')->label('Account Name'),
                        TextEntry::make('offsetCoa.code')->label('Offset COA'),
                        TextEntry::make('offsetCoa.name')->label('Offset Account'),
                        TextEntry::make('counterparty')->label('Counterparty'),
                        TextEntry::make('description'),
                        TextEntry::make('voucher_number')->label('Voucher Number'),
                        TextEntry::make('cabang.nama')->label('Branch'),
                    ])->columns(2),
                InfolistSection::make('Transaction Breakdown')
                    ->schema([
                        RepeatableEntry::make('transactionDetails')
                            ->label('')
                            ->schema([
                                TextEntry::make('chartOfAccount.code')->label('COA'),
                                TextEntry::make('chartOfAccount.name')->label('Account Name'),
                                TextEntry::make('amount')->money('IDR')->label('Amount'),
                                TextEntry::make('description')->label('Description'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function ($record) {
                        return $record->transactionDetails()->exists();
                    }),
                InfolistSection::make('Journal Entries')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                            ->label('View All Journal Entries')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(function ($record) {
                                // Redirect to JournalEntryResource with filter for this transaction
                                $sourceType = urlencode(\App\Models\CashBankTransaction::class);
                                $sourceId = $record->id;

                                return "/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}";
                            })
                            ->openUrlInNewTab()
                            ->visible(function ($record) {
                                return $record->journalEntries()->exists();
                            }),
                    ])
                    ->schema([
                        TextEntry::make('journal_entries_summary')
                            ->label('Summary')
                            ->state(function ($record) {
                                $entries = $record->journalEntries;
                                $totalDebit = $entries->sum('debit');
                                $totalCredit = $entries->sum('credit');
                                $count = $entries->count();
                                return "Total Entries: {$count} | Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . " | Total Credit: Rp " . number_format($totalCredit, 0, ',', '.');
                            })
                            ->columnSpanFull(),
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
                                TextEntry::make('reference')->label('Reference'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function ($record) {
                        return $record->journalEntries()->exists();
                    }),
            ]);
    }

    protected function afterCreate(): void
    {
        // Automatically post journal entries after creating a transaction
        $service = app(CashBankService::class);
        $service->postTransaction($this->record);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashBankTransactions::route('/'),
            'create' => Pages\CreateCashBankTransaction::route('/create'),
            'view' => Pages\ViewCashBankTransaction::route('/{record}'),
            'edit' => Pages\EditCashBankTransaction::route('/{record}/edit'),
        ];
    }
}
