<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Resources\WarehouseResource\Pages\ViewWarehouse;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Gudang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Gudang')
                    ->schema([
                        TextInput::make('kode')
                            ->label('Kode')
                            ->maxLength(20)
                            ->reactive()
                            ->unique(ignoreRecord: true)
                            ->suffixAction(Action::make('generateKodeGudang')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode Gudang')
                                ->action(function ($set, $get, $state) {
                                    $warehouseService = app(WarehouseService::class);
                                    $set('kode', $warehouseService->generateKodeGudang());
                                }))
                            ->validationMessages([
                                'required' => 'Kode Gudang wajib diisi',
                                'unique' => 'Kode gudang sudah digunakan'
                            ])
                            ->required(),
                        TextInput::make('name')
                            ->label('Nama')
                            ->maxLength(100)
                            ->required(),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->preload()
                            ->validationMessages([
                                'required' => "Cabang wajib dipilih"
                            ])
                            ->searchable(['kode', 'nama'])
                            ->relationship('cabang', 'nama')
                            ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                return "({$cabang->kode}) {$cabang->nama}";
                            })
                            ->required(),
                        Radio::make('tipe')
                            ->label('Tipe')
                            ->inlineLabel()
                            ->options([
                                'Kecil' => 'Kecil',
                                'Besar' => 'Besar',
                            ])
                            ->default('Kecil')
                            ->required(),
                        Textarea::make('location')
                            ->label('Alamat'),
                        TextInput::make('telepon')
                            ->label('Telepon')
                            ->maxLength(20),
                        Checkbox::make('status')
                            ->label('Status (Aktif / Tidak Aktif)')
                            ->default(false),
                        ColorPicker::make('warna_background')
                            ->label('Warna Background'),
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
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge()
                    ->colors([
                        'info' => 'Kecil',
                        'success' => 'Besar',
                    ]),
                TextColumn::make('location')
                    ->label('Alamat')
                    ->limit(30),
                TextColumn::make('telepon')
                    ->label('Telepon'),
                IconColumn::make('status')
                    ->label('Status')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
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
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'view' => ViewWarehouse::route('/{record}'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
