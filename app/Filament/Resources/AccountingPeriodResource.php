<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingPeriodResource\Pages;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Services\AccountingPeriodService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class AccountingPeriodResource extends Resource
{
    protected static ?string $model = AccountingPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Akuntansi Keuangan';

    protected static ?string $navigationLabel = 'Tutup Buku & Periode';

    protected static ?string $modelLabel = 'Periode Akuntansi';

    protected static ?string $pluralModelLabel = 'Periode Akuntansi';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Periode Akuntansi')
                    ->description('Tentukan rentang tanggal pembukuan dan cabang.')
                    ->schema([
                        Forms\Components\Select::make('cabang_id')
                            ->label('Cabang')
                            ->relationship('cabang', 'nama')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->cabang_id ?? session('current_cabang_id'))
                            ->placeholder('Semua Cabang / Konsolidasi Pusat')
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'open' => 'Terbuka',
                                'closed' => 'Ditutup',
                            ])
                            ->default('open')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('period_start')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal mulai periode harus diisi.',
                            ])
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->afterOrEqual('period_start')
                            ->validationMessages([
                                'required' => 'Tanggal selesai periode harus diisi.',
                                'after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->placeholder('Pusat / Semua')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('period_start')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('period_end')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'open',
                        'danger' => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Terbuka',
                        'closed' => 'Ditutup (Locked)',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('closedBy.name')
                    ->label('Ditutup Oleh')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('closed_at')
                    ->label('Waktu Tutup')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('period_start', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Terbuka',
                        'closed' => 'Ditutup',
                    ]),
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama'),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('close_period')
                        ->label('Tutup Buku')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(fn (AccountingPeriod $record) => $record->status === 'open' && auth()->user()->can('close', $record))
                        ->requiresConfirmation()
                        ->modalHeading('Tutup Buku Periode Akuntansi')
                        ->modalDescription('Apakah Anda yakin ingin menutup buku periode ini? Seluruh transaksi dan jurnal dalam rentang tanggal ini akan dikunci dari penambahan, pengubahan, atau penghapusan transaksi mundur.')
                        ->form([
                            Forms\Components\Toggle::make('generate_closing_entries')
                                ->label('Buat Jurnal Penutup Otomatis (Closing Entries)')
                                ->helperText('Otomatis menutup saldo pendapatan & beban ke akun Laba Ditahan.')
                                ->default(true),
                        ])
                        ->action(function (AccountingPeriod $record, array $data) {
                            try {
                                $service = app(AccountingPeriodService::class);
                                $service->closePeriod(
                                    $record,
                                    auth()->id(),
                                    (bool) ($data['generate_closing_entries'] ?? true)
                                );

                                Notification::make()
                                    ->title('Periode Akuntansi Berhasil Ditutup')
                                    ->body("Periode {$record->period_start->format('d M Y')} s/d {$record->period_end->format('d M Y')} telah dikunci.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Gagal Menutup Periode')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Action::make('reopen_period')
                        ->label('Buka Kembali')
                        ->icon('heroicon-o-lock-open')
                        ->color('warning')
                        ->visible(fn (AccountingPeriod $record) => $record->status === 'closed' && auth()->user()->can('reopen', $record))
                        ->requiresConfirmation()
                        ->modalHeading('Buka Kembali Periode Akuntansi')
                        ->modalDescription('PERINGATAN: Membuka kembali periode ini akan memperbolehkan posting jurnal dan koreksi transaksi. Lakukan hanya untuk koreksi resmi yang telah disetujui.')
                        ->action(function (AccountingPeriod $record) {
                            try {
                                $service = app(AccountingPeriodService::class);
                                $service->reopenPeriod($record);

                                Notification::make()
                                    ->title('Periode Akuntansi Dibuka Kembali')
                                    ->body("Periode {$record->period_start->format('d M Y')} s/d {$record->period_end->format('d M Y')} kini berstatus terbuka.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Gagal Membuka Periode')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (AccountingPeriod $record) => $record->status === 'open'),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (AccountingPeriod $record) => $record->status === 'open'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingPeriods::route('/'),
            'create' => Pages\CreateAccountingPeriod::route('/create'),
            'edit' => Pages\EditAccountingPeriod::route('/{record}/edit'),
        ];
    }
}
