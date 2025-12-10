<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OtherSaleResource\Pages;
use App\Filament\Resources\OtherSaleResource\RelationManagers;
use App\Models\OtherSale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class OtherSaleResource extends Resource
{
    protected static ?string $model = OtherSale::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Penjualan Lainnya';

    protected static ?string $modelLabel = 'Penjualan Lainnya';

    protected static ?string $pluralModelLabel = 'Penjualan Lainnya';

    protected static ?string $navigationGroup = 'Finance - Penjualan';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Transaksi')
                    ->schema([
                        Forms\Components\TextInput::make('reference_number')
                            ->label('Nomor Referensi')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn() => 'OS-' . now()->format('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT)),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('type')
                            ->label('Jenis Penjualan')
                            ->options([
                                'building_rental' => 'Pendapatan Sewa Gedung',
                                'other_income' => 'Pendapatan Lainnya',
                            ])
                            ->required()
                            ->default('building_rental'),

                        Forms\Components\Select::make('coa_id')
                            ->label('Akun Pendapatan')
                            ->options(function () {
                                return \App\Models\ChartOfAccount::where('type', 'Revenue')
                                    ->get()
                                    ->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\ChartOfAccount::where('type', 'Revenue')
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                              ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    });
                            })
                            ->required()
                            ->default(function () {
                                // Default to "PENDAPATAN LAINNYA" for building rental
                                return \App\Models\ChartOfAccount::where('code', '7000.04')->value('id');
                            }),

                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->indonesianMoney(),

                        Forms\Components\Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(\App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->searchable()
                            ->preload()
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detail Pembayaran')
                    ->schema([
                        Forms\Components\Select::make('cash_bank_account_id')
                            ->label('Akun Kas/Bank')
                            ->options(function () {
                                return \App\Models\CashBankAccount::with('coa')
                                    ->get()
                                    ->mapWithKeys(function ($account) {
                                        $coaCode = $account->coa ? $account->coa->code : 'N/A';
                                        $coaName = $account->coa ? $account->coa->name : 'N/A';
                                        return [$account->id => "({$coaCode}) {$account->name}"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\CashBankAccount::with('coa')
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                              ->orWhereHas('coa', function ($coaQuery) use ($search) {
                                                  $coaQuery->where('code', 'like', "%{$search}%")
                                                           ->orWhere('name', 'like', "%{$search}%");
                                              });
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($account) {
                                        $coaCode = $account->coa ? $account->coa->code : 'N/A';
                                        $coaName = $account->coa ? $account->coa->name : 'N/A';
                                        return [$account->id => "({$coaCode}) {$account->name}"];
                                    });
                            })
                            ->placeholder('Pilih jika pembayaran langsung ke kas/bank'),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->options(function () {
                                return \App\Models\Customer::all()
                                    ->mapWithKeys(function ($customer) {
                                        return [$customer->id => "({$customer->code}) {$customer->name}"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Customer::where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                              ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($customer) {
                                        return [$customer->id => "({$customer->code}) {$customer->name}"];
                                    });
                            })
                            ->placeholder('Pilih jika ada customer tertentu'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->maxLength(500),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->maxLength(1000),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('No. Referensi')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'building_rental' => 'success',
                        'other_income' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'building_rental' => 'Sewa Gedung',
                        'other_income' => 'Pendapatan Lainnya',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('coa.name')
                    ->label('Akun Pendapatan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'warning',
                        'posted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('cabang')
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'building_rental' => 'Sewa Gedung',
                        'other_income' => 'Pendapatan Lainnya',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'posted' => 'Posted',
                        'cancelled' => 'Cancelled',
                    ]),

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
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\Action::make('post_journal')
                        ->label('Post Journal')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->visible(fn(OtherSale $record): bool => $record->status === 'draft')
                        ->action(function (OtherSale $record) {
                            $service = new \App\Services\OtherSaleService();
                            $service->postJournalEntries($record);

                            \Filament\Notifications\Notification::make()
                                ->title('Journal entries posted successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('reverse_journal')
                        ->label('Reverse Journal')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn(OtherSale $record): bool => $record->status === 'posted')
                        ->action(function (OtherSale $record) {
                            $service = new \App\Services\OtherSaleService();
                            $service->reverseJournalEntries($record);

                            \Filament\Notifications\Notification::make()
                                ->title('Journal entries reversed successfully')
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Other Sale</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Other Sale adalah penjualan lainnya yang tidak melalui proses Sale Order standar, seperti penjualan langsung atau penjualan non-inventory.</li>' .
                    '<li><strong>Status Flow:</strong> Dibuat langsung, dapat diedit atau dihapus. Termasuk opsi untuk reverse journal entries jika diperlukan.</li>' .
                    '<li><strong>Validasi:</strong> Subtotal, Tax, PPN dihitung otomatis berdasarkan item. Terintegrasi dengan accounting untuk journal entries.</li>' .
                    '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah penjualan), <em>Delete</em> (hapus), <em>Reverse Journal</em> (balikkan entri jurnal).</li>' .
                    '<li><strong>Filters:</strong> Customer, Date Range, Amount Range, dll.</li>' .
                    '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan penjualan dari cabang tersebut jika tidak memiliki akses all.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan accounting untuk journal entries dan mungkin menghasilkan Account Receivable.</li>' .
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
            'index' => Pages\ListOtherSales::route('/'),
            'create' => Pages\CreateOtherSale::route('/create'),
            'view' => Pages\ViewOtherSale::route('/{record}'),
            'edit' => Pages\EditOtherSale::route('/{record}/edit'),
        ];
    }
}
