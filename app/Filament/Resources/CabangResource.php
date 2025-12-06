<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CabangResource\Pages;
use App\Filament\Resources\CabangResource\Pages\ViewCabang;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Services\CabangService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CabangResource extends Resource
{
    protected static ?string $model = Cabang::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Cabang';

    protected static ?string $pluralLabel = 'Cabang';

    protected static ?string $modelLabel = 'Cabang';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('form Cabang')
                    ->schema([
                        TextInput::make('kode')
                            ->label('Kode')
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Kode Cabang tidak boleh kosong',
                                'unique' => 'Kode cabang sudah digunakan'
                            ])
                            ->suffixAction(Action::make('generateKodeCabang')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode Cabang')
                                ->action(function ($set, $get, $state) {
                                    $cabangService = app(CabangService::class);
                                    $set('kode', $cabangService->generateKodeCabang());
                                }))
                            ->required(),
                        TextInput::make('nama')
                            ->label('Nama')
                            ->maxLength(100)
                            ->validationMessages([
                                'required' => 'Nama Cabang tidak boleh kosong'
                            ])
                            ->required(),
                        Textarea::make('alamat')
                            ->label('Alamat')
                            ->validationMessages([
                                'required' => 'Alamat tidak boleh kosong'
                            ])
                            ->required(),
                        TextInput::make('telepon')
                            ->tel()
                            ->label('Telepon')
                            ->validationMessages([
                                'required' => 'Nomor Telepon tidak boleh kosong',
                                'regex' => 'Nomor Telepon tidak valid !'
                            ])
                            ->helperText('Contoh : 07512345678')
                            ->rules(['regex:/^0[2-9][0-9]{7,10}$/'])
                            ->required()
                            ->maxLength(255),
                        TextInput::make('kenaikan_harga')
                            ->label('Kenaikan Harga (%)')
                            ->numeric()
                            ->default(0)
                            ->validationMessages([
                                'numeric' => 'Kenaikan harga harus berupa angka'
                            ]),
                        ColorPicker::make('warna_background')
                            ->label('Warna Background')
                            ->required()
                            ->validationMessages([
                                'required' => 'Warna belum dipilih'
                            ]),
                        Radio::make('tipe_penjualan')
                            ->label('Tipe Penjualan')
                            ->inlineLabel()
                            ->options([
                                'Semua' => 'Semua',
                                'Pajak' => 'Pajak',
                                'Non Pajak' => 'Non Pajak',
                            ])
                            ->validationMessages([
                                'required' => 'Tipe penjualan belum dipilih'
                            ])
                            ->default('Semua')
                            ->required(),
                        TextInput::make('kode_invoice_pajak')
                            ->label('Kode Invoice Pajak')
                            ->validationMessages([
                                'max' => 'Kode invoice pajak terlalu panjang'
                            ])
                            ->maxLength(50),
                        TextInput::make('kode_invoice_non_pajak')
                            ->label('Kode Invoice Non Pajak')
                            ->maxLength(50)
                            ->validationMessages([
                                'max' => 'Kode invoice non pajak terlalu panjang'
                            ]),
                        TextInput::make('kode_invoice_pajak_walkin')
                            ->label('Kode Invoice Pajak (Customer Walk-in)')
                            ->maxLength(50)
                            ->validationMessages([
                                'max' => 'Kode invoice pajak walk-in terlalu panjang'
                            ]),
                        TextInput::make('nama_kwitansi')
                            ->label('Nama di Kwitansi')
                            ->maxLength(100)
                            ->validationMessages([
                                'max' => 'Nama kwitansi terlalu panjang'
                            ]),
                        TextInput::make('label_invoice_pajak')
                            ->label('Label Invoice Pajak')
                            ->maxLength(100)
                            ->validationMessages([
                                'max' => 'Label invoice pajak terlalu panjang'
                            ]),
                        TextInput::make('label_invoice_non_pajak')
                            ->label('Label Invoice Non Pajak')
                            ->maxLength(100)
                            ->validationMessages([
                                'max' => 'Label invoice non pajak terlalu panjang'
                            ]),
                        FileUpload::make('logo_invoice_non_pajak')
                            ->label('Logo Invoice Non Pajak')
                            ->directory('logo-invoice-non-pajak')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif'])
                            ->maxSize(2048) // 2MB
                            ->validationMessages([
                                'acceptedFileTypes' => 'Logo harus berupa gambar (JPG, PNG, GIF)',
                                'maxSize' => 'Ukuran logo maksimal 2MB'
                            ]),
                        Toggle::make('lihat_stok_cabang_lain')
                            ->label('Bisa Lihat Stok Cabang Lain saat Penjualan'),
                        Checkbox::make('status')
                            ->label('Status (Aktif / Tidak Aktif)')
                            ->default(false),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('warna_background')
                    ->label('Background'),
                TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('alamat')
                    ->label('Alamat')
                    ->limit(30),
                TextColumn::make('telepon')
                    ->label('Telepon'),
                TextColumn::make('kenaikan_harga')
                    ->label('Kenaikan Harga (%)')
                    ->formatStateUsing(fn($state) => $state . '%'),
                TextColumn::make('tipe_penjualan')
                    ->label('Tipe Penjualan')
                    ->badge()
                    ->colors([
                        'success' => 'Semua',
                        'warning' => 'Pajak',
                        'danger' => 'Non Pajak',
                    ]),
                IconColumn::make('status')
                    ->label('Status')
                    ->boolean(),
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    })
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('warehouse', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        true => 'Aktif',
                        false => 'Tidak Aktif',
                    ])
                    ->label('Status'),
                SelectFilter::make('tipe_penjualan')
                    ->options([
                        'Semua' => 'Semua',
                        'Pajak' => 'Pajak',
                        'Non Pajak' => 'Non Pajak',
                    ])
                    ->label('Tipe Penjualan'),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCabangs::route('/'),
            'create' => Pages\CreateCabang::route('/create'),
            'view' => ViewCabang::route('/{record}'),
            'edit' => Pages\EditCabang::route('/{record}/edit'),
        ];
    }
}
