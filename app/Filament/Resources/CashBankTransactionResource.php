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
use Filament\Notifications\Notification;

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
                            ->default(fn() => app(CashBankService::class)->generateNumber('CB')),
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
                        DatePicker::make('date')->label('Tanggal')->required()->default(now())->columnSpan(3),
                        Select::make('type')->label('Tipe')->options([
                            'cash_in' => 'Kas Masuk',
                            'cash_out' => 'Kas Keluar',
                            'bank_in' => 'Bank Masuk',
                            'bank_out' => 'Bank Keluar',
                        ])->required()->columnSpan(3),
                        TextInput::make('amount')->numeric()->minValue(0.01)->required()->indonesianMoney()->columnSpan(2),
                        Select::make('account_coa_id')->label('Kas/Bank (COA)')->searchable()
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->where('code', 'like', '1111%')
                                    ->orWhere('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->required()->columnSpan(6),
                        Select::make('offset_coa_id')->label('Rincian Pembayaran (COA)')->searchable()
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->whereNot('code', 'like', '1111%')
                                    ->whereNot('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->required()->columnSpan(6)
                            ->rule('different:account_coa_id'),
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
                            ->visible(fn (callable $get) => $get('voucher_request_id') || $get('voucher_number'))
                            ->columnSpan(6),
                        TextInput::make('voucher_amount_used')
                            ->label('Jumlah Voucher yang Digunakan')
                            ->numeric()
                            ->minValue(0.01)
                            ->visible(fn (callable $get) => $get('voucher_request_id') || $get('voucher_number'))
                            ->columnSpan(6)
                            ->default(fn (callable $get) => $get('amount'))
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
                            ]),
                    ]),
                ])
                ->visible(fn (callable $get) => true) // Always show but fields inside are conditionally visible
                ->collapsible(),
            Section::make('Rincian Pembayaran ke Akun Anak')
                ->schema([
                    Placeholder::make('breakdown_info')
                        ->label('')
                        ->content('Distribusikan jumlah pembayaran ke akun anak dari rekening bank yang dipilih. Total distribusi harus sama dengan jumlah pembayaran.'),
                    Repeater::make('transactionDetails')
                        ->relationship('transactionDetails')
                        ->schema([
                            Select::make('chart_of_account_id')
                                ->label('Akun Anak')
                                ->options(function (callable $get) {
                                    $accountCoaId = $get('../../account_coa_id');
                                    if (!$accountCoaId) return [];

                                    $parentAccount = ChartOfAccount::find($accountCoaId);
                                    if (!$parentAccount || !str_starts_with($parentAccount->code, '1112')) return [];

                                    return ChartOfAccount::where('parent_id', $accountCoaId)
                                        ->orderBy('code')
                                        ->get()
                                        ->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]);
                                })
                                ->required()
                                ->searchable()
                                ->columnSpan(6),
                            TextInput::make('amount')
                                ->label('Jumlah')
                                ->numeric()
                                ->minValue(-999999999)
                                ->maxValue(999999999)
                                ->required()
                                ->indonesianMoney()
                                ->helperText('Gunakan nilai negatif (-) untuk pajak/pengurang yang mengurangi nilai transaksi')
                                ->columnSpan(2),
                            TextInput::make('ntpn')
                                ->label('NTPN')
                                ->helperText('Nomor Transaksi Penerimaan Negara untuk PPH 22 Import')
                                ->columnSpan(2)
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
                            TextInput::make('description')
                                ->label('Keterangan')
                                ->columnSpan(2),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->addActionLabel('Tambah Rincian')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => ChartOfAccount::find($state['chart_of_account_id'] ?? null)?->name ?? 'Rincian Baru')
                        ->afterStateUpdated(function ($state, callable $set) {
                            $total = collect($state ?? [])->sum('amount');
                            $mainAmount = $set('../../amount', $total);
                        }),
                ])
                ->visible(fn (callable $get) => $get('account_coa_id') && ChartOfAccount::find($get('account_coa_id')) && str_starts_with(ChartOfAccount::find($get('account_coa_id'))->code, '1112'))
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.'),
                TextColumn::make('date')->date('d/m/Y')->label('Tanggal'),
                TextColumn::make('type')->badge()->label('Tipe'),
                TextColumn::make('voucherRequest.number')
                    ->label('No. Voucher')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state, $record) => $record->voucherRequest ? $record->voucherRequest->number : '-'),
                TextColumn::make('accountCoa.code')->label('Akun Kas/Bank')->formatStateUsing(fn ($state, $record) => $record->accountCoa->code . ' - ' . $record->accountCoa->name),
                TextColumn::make('offsetCoa.code')->label('Lawan Akun')->formatStateUsing(fn ($state, $record) => $record->offsetCoa->code . ' - ' . $record->offsetCoa->name),
                TextColumn::make('amount')->money('IDR')->label('Jumlah'),
                TextColumn::make('transactionDetails')
                    ->label('Rincian Akun Anak')
                    ->formatStateUsing(function ($record) {
                        if ($record->transactionDetails->isEmpty()) {
                            return '-';
                        }

                        return $record->transactionDetails->map(function ($detail) {
                            $info = $detail->chartOfAccount->code . ' - ' . $detail->chartOfAccount->name . ': ' . formatCurrency($detail->amount);
                            if ($detail->ntpn) {
                                $info .= ' (NTPN: ' . $detail->ntpn . ')';
                            }
                            return $info;
                        })->join('; ');
                    })
                    ->wrap()
                    ->limit(50),
            ])
            ->filters([])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('switch_to_transfer')
                    ->label('Transfer Kas & Bank')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('primary')
                    ->url(fn() => \App\Filament\Resources\CashBankTransferResource::getUrl('index'))
                    ->tooltip('Beralih ke menu Transfer Kas & Bank'),
            ])
            ->actions([
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
                    ->url(fn (CashBankTransaction $record) => route('filament.admin.resources.voucher-requests.view', $record->voucher_request_id))
                    ->visible(fn (CashBankTransaction $record) => !is_null($record->voucher_request_id)),
                TableAction::make('view_reconciliation')->label('Lihat Rekonsiliasi Bank')->icon('heroicon-o-eye')
                    ->url(fn ($record) => '/bank-reconciliations?tableFilters[coa_id][value]=' . ($record->accountCoa && str_starts_with($record->accountCoa->code, '111') ? $record->accountCoa->id : ''))
                    ->visible(fn ($record) => $record->accountCoa && str_starts_with($record->accountCoa->code, '111')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashBankTransactions::route('/'),
            'create' => Pages\CreateCashBankTransaction::route('/create'),
            'edit' => Pages\EditCashBankTransaction::route('/{record}/edit'),
        ];
    }
}
