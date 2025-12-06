<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashBankTransferResource\Pages;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Services\CashBankService;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashBankTransferResource extends Resource
{
    protected static ?string $model = CashBankTransfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Finance - Pembayaran';
    protected static ?string $modelLabel = 'Transfer Kas & Bank';
    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Fieldset::make('Form')
                ->schema([
                    Grid::make(12)->schema([
                        TextInput::make('number')->label('Nomor')
                            ->helperText('Auto-generate bila dikosongkan')
                            ->dehydrated()
                            ->columnSpan(3)
                            ->default(fn() => app(CashBankService::class)->generateTransferNumber())
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('generateNumber')
                                    ->label('Generate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Set $set) {
                                        $set('number', app(CashBankService::class)->generateTransferNumber());
                                    })
                            ),
                        DatePicker::make('date')->label('Tanggal')->required()->columnSpan(3),
                        TextInput::make('amount')->numeric()->minValue(0.01)->required()->indonesianMoney()->columnSpan(3),
                        TextInput::make('other_costs')
                            ->label('Biaya Lainnya')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->indonesianMoney()
                            ->columnSpan(3)
                            ->live()
                            ->helperText('Biaya admin bank atau biaya transfer lainnya'),
                        TextInput::make('reference')->label('Referensi')->columnSpan(4),
                        Select::make('from_coa_id')->label('Dari Kas/Bank (COA)')
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->searchable()->required()->columnSpan(4),
                        Select::make('to_coa_id')->label('Ke Kas/Bank (COA)')
                            ->options(fn() => ChartOfAccount::where(function ($q) {
                                $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%');
                            })->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->searchable()->required()->columnSpan(4)
                            ->rule('different:from_coa_id'),
                        Select::make('other_costs_coa_id')
                            ->label('COA Biaya Lainnya')
                            ->options(fn() => ChartOfAccount::whereNotNull('parent_id')->orderBy('code')->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name]))
                            ->searchable()
                            ->columnSpan(3)
                            ->visible(fn(callable $get) => $get('other_costs') > 0)
                            ->required(fn(callable $get) => $get('other_costs') > 0)
                            ->helperText('Pilih COA untuk biaya lainnya'),
                        Textarea::make('description')->label('Keterangan')->columnSpan(6),
                        FileUpload::make('attachment_path')->label('Lampiran')->directory('cashbank')->columnSpan(6),
                    ])
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->searchable()->sortable(),
                TextColumn::make('date')->date('d/m/Y')->label('Tanggal')->sortable(),
                TextColumn::make('fromCoa.code')
                    ->label('Dari')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->fromCoa ? $record->fromCoa->code . ' - ' . $record->fromCoa->name : '-'
                    )
                    ->searchable(),
                TextColumn::make('toCoa.code')
                    ->label('Ke')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->toCoa ? $record->toCoa->code . ' - ' . $record->toCoa->name : '-'
                    )
                    ->searchable(),
                TextColumn::make('amount')->money('IDR')->label('Jumlah')->sortable(),
                TextColumn::make('other_costs')
                    ->money('IDR')
                    ->label('Biaya Lainnya')
                    ->sortable()
                    ->toggleable(),
                BadgeColumn::make('status')->label('Status')
                    ->colors(['secondary' => 'draft', 'success' => 'posted', 'info' => 'reconciled']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'posted' => 'Posted',
                        'reconciled' => 'Reconciled',
                    ]),
                SelectFilter::make('from_coa_id')
                    ->label('Dari COA')
                    ->relationship('fromCoa', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('to_coa_id')
                    ->label('Ke COA')
                    ->relationship('toCoa', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('view_reconciliation')->label('Lihat Rekonsiliasi Bank')->icon('heroicon-o-eye')
                        ->url(fn($record) => '/bank-reconciliations?tableFilters[coa_id][value]=' . ($record->fromCoa && str_starts_with($record->fromCoa->code, '111') ? $record->fromCoa->id : ($record->toCoa && str_starts_with($record->toCoa->code, '111') ? $record->toCoa->id : '')))
                        ->visible(fn($record) => $record->status === 'posted' && ($record->fromCoa && str_starts_with($record->fromCoa->code, '111') || $record->toCoa && str_starts_with($record->toCoa->code, '111'))),
                    Action::make('post')->label('Posting Jurnal')->action(function ($record) {
                        app(CashBankService::class)->postTransfer($record);
                    })->visible(fn($record) => $record->status === 'draft')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-check'),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn($record) => $record->status === 'draft')
                        ->requiresConfirmation(function ($action, $record) {
                            $action->modalHeading('Hapus Transfer Kas & Bank')
                                   ->modalDescription("⚠️ PERHATIAN: Transfer ini akan dihapus secara permanen.\n\n" .
                                                    "Detail Transfer:\n" .
                                                    "- Nomor: {$record->number}\n" .
                                                    "- Dari: " . ($record->fromCoa ? $record->fromCoa->code . ' - ' . $record->fromCoa->name : 'N/A') . "\n" .
                                                    "- Ke: " . ($record->toCoa ? $record->toCoa->code . ' - ' . $record->toCoa->name : 'N/A') . "\n" .
                                                    "- Jumlah: Rp " . number_format($record->amount, 0, ',', '.') . "\n\n" .
                                                    "Pastikan transfer ini belum diposting jurnal.")
                                   ->modalSubmitActionLabel('Ya, Hapus Transfer');
                        })
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Transfer Kas & Bank</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Transfer Kas & Bank adalah proses pemindahan dana antar rekening kas/bank dalam perusahaan untuk keperluan operasional atau investasi.</li>' .
                    '<li><strong>Jenis Transfer:</strong> <em>Bank to Bank</em> (antar rekening bank), <em>Cash to Bank</em> (dari kas ke bank), <em>Bank to Cash</em> (dari bank ke kas).</li>' .
                    '<li><strong>Komponen Utama:</strong> <em>Nomor Transfer</em> (otomatis generate), <em>Tanggal</em> (tanggal transfer), <em>Dari COA</em> (rekening sumber), <em>Ke COA</em> (rekening tujuan), <em>Jumlah</em> (nominal transfer).</li>' .
                    '<li><strong>Status Flow:</strong> <em>Draft</em> (belum diposting) → <em>Posted</em> (sudah diposting jurnal) → <em>Reconciled</em> (sudah direkonsiliasi bank).</li>' .
                    '<li><strong>Validasi:</strong> <em>COA Validation</em> - memastikan rekening sumber dan tujuan valid (kas/bank). <em>Balance Check</em> - verifikasi saldo rekening sumber mencukupi.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan <em>Chart of Account</em> (COA kas/bank), <em>Journal Entry</em> (otomatis buat jurnal), <em>Bank Reconciliation</em> (rekonsiliasi bank), dan <em>Cash Bank Transaction</em> (transaksi kas/bank).</li>' .
                    '<li><strong>Actions:</strong> <em>Post Transfer</em> (posting jurnal - hanya untuk draft), <em>View Reconciliation</em> (lihat rekonsiliasi bank), <em>Edit</em> (ubah transfer), <em>Delete</em> (hapus transfer - hanya untuk draft), <em>Switch to Transaction</em> (pindah ke menu transaksi).</li>' .
                    '<li><strong>Journal Management:</strong> Sistem otomatis mengelola journal entries - menghapus saat transfer dihapus, re-posting saat diubah, dan restore saat direstore.</li>' .
                    '<li><strong>Permissions:</strong> <em>view any cash bank transfer</em>, <em>create cash bank transfer</em>, <em>update cash bank transfer</em>, <em>delete cash bank transfer</em>, <em>restore cash bank transfer</em>, <em>force-delete cash bank transfer</em>.</li>' .
                    '<li><strong>Journal Impact:</strong> Otomatis membuat journal entry dengan debit rekening tujuan dan credit rekening sumber. Biaya transfer tambahan akan dicatat sebagai expense.</li>' .
                    '<li><strong>Reporting:</strong> Menyediakan data untuk cash flow statement, bank reconciliation, dan financial position tracking.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashBankTransfers::route('/'),
            'create' => Pages\CreateCashBankTransfer::route('/create'),
            'edit' => Pages\EditCashBankTransfer::route('/{record}/edit'),
        ];
    }
}
