<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OtherSaleResource\Pages;
use App\Filament\Resources\OtherSaleResource\RelationManagers;
use App\Models\OtherSale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                            ->default(fn () => 'OS-' . now()->format('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT)),

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
                            ->relationship('coa', 'name', function (Builder $query) {
                                $query->where('type', 'Revenue');
                            })
                            ->searchable()
                            ->preload()
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
                            ->relationship('cabang', 'nama')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detail Pembayaran')
                    ->schema([
                        Forms\Components\Select::make('cash_bank_account_id')
                            ->label('Akun Kas/Bank')
                            ->relationship('cashBankAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih jika pembayaran langsung ke kas/bank'),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
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
                    ]),
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
                    ->color(fn (string $state): string => match ($state) {
                        'building_rental' => 'success',
                        'other_income' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
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
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'posted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->searchable(),
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
                    ->relationship('cabang', 'nama'),
            ])
            ->actions([
                Tables\Actions\Action::make('post_journal')
                    ->label('Post Journal')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn (OtherSale $record): bool => $record->status === 'draft')
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
                    ->visible(fn (OtherSale $record): bool => $record->status === 'posted')
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'edit' => Pages\EditOtherSale::route('/{record}/edit'),
        ];
    }
}
