<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillOfMaterialResource\Pages;
use App\Filament\Resources\BillOfMaterialResource\Pages\ViewBillOfMaterial;
use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\BillOfMaterialService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;

class BillOfMaterialResource extends Resource
{
    protected static ?string $model = BillOfMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-pointing-in';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode BOM')
                            ->validationMessages([
                                'required' => 'Kode BOM tidak boleh kosong',
                                'unique' => 'Kode BOM sudah digunakan !'
                            ])
                            ->reactive()
                            ->suffixAction(Action::make('generateCode')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode Supplier')
                                ->action(function ($set, $get, $state) {
                                    $billOfMaterialService = app(BillOfMaterialService::class);
                                    $set('code', $billOfMaterialService->generateCode());
                                }))
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nama_bom')
                            ->label('Nama BOM')
                            ->validationMessages([
                                'required' => 'Nama tidak boleh kosongs'
                            ])
                            ->required()
                            ->maxLength(255),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->required()
                            ->validationMessages([
                                'required' => 'Cabang belum dipilih',
                                'exists' => 'Cabang tidak ditemukan !'
                            ])->reactive()
                            ->relationship('cabang', 'nama')
                            ->searchable(['nama', 'kode'])
                            ->preload()
                            ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                return "({$cabang->kode}) {$cabang->nama}";
                            }),
                        Select::make('product_id')
                            ->required()
                            ->label('Product')
                            ->reactive()
                            ->preload()
                            ->searchable(['sku', 'name'])
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $product = Product::find($state);
                                if ($product) {
                                    $set('uom_id', $product->uom_id);
                                    $listConversions = [];
                                    foreach ($product->unitConversions as $index => $conversion) {
                                        $listConversions[$index] = [
                                            'uom_id' => $conversion->uom_id,
                                            'nilai_konversi' => $conversion->nilai_konversi
                                        ];
                                    }

                                    $set('satuan_konversi', $listConversions);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Produk belum di pilih',
                                'exists' => 'Produk tidak ditemukan !'
                            ])
                            ->relationship('product', 'name', function (Builder $query, $get) {
                                $query->where('is_manufacture', true)
                                    ->where('cabang_id', $get('cabang_id'));
                            }),
                        Select::make('uom_id')
                            ->label('Unif Of Measure (Satuan)')
                            ->preload()
                            ->reactive()
                            ->searchable(['name'])
                            ->relationship('uom', 'name')
                            ->validationMessages([
                                'required' => 'Unit of measure belum dipilih',
                                'exists' => 'Unit of measure tidak ditemukan !'
                            ])
                            ->required(),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0.00),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->nullable(),
                        Toggle::make('is_active')
                            ->required(),
                        Repeater::make('satuan_konversi')
                            ->columnSpanFull()
                            ->columns(2)
                            ->reactive()
                            ->disabled()
                            ->label("Satuan Konversi")
                            ->schema([
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->preload()
                                    ->disabled()
                                    ->reactive()
                                    ->searchable()
                                    ->options(function () {
                                        return UnitOfMeasure::get()->pluck('name', 'id');
                                    }),
                                TextInput::make('nilai_konversi')
                                    ->label('Nilai Konversi')
                                    ->reactive()
                                    ->disabled()
                                    ->numeric(),
                            ]),
                        Repeater::make('items')
                            ->relationship()
                            ->columnSpanFull()
                            ->addAction(function (Action $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle');
                            })
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data) {
                                $listConversions = [];
                                $product = Product::find($data['product_id']);
                                foreach ($product->unitConversions as $index => $conversion) {
                                    $listConversions[$index] = [
                                        'uom_id' => $conversion->uom_id,
                                        'nilai_konversi' => $conversion->nilai_konversi
                                    ];
                                }
                                $data['satuan_konversi'] = $listConversions;
                                return $data;
                            })
                            ->columns(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Material')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'Material belum dipilih',
                                        'exists' => 'Material tidak tersedia !'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('uom_id', $product->uom_id);
                                            $listConversions = [];
                                            foreach ($product->unitConversions as $index => $conversion) {
                                                $listConversions[$index] = [
                                                    'uom_id' => $conversion->uom_id,
                                                    'nilai_konversi' => $conversion->nilai_konversi
                                                ];
                                            }

                                            $set('satuan_konversi', $listConversions);
                                        }
                                    })
                                    ->relationship('product', 'name')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    })->required(),
                                Select::make('uom_id')
                                    ->label('Unif Of Measure (Satuan)')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('uom', 'name')
                                    ->required()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Satuan belum dipilih',
                                        'exists' => 'Satuan tidak ditemukan'
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity tidak boleh kosong'
                                    ])
                                    ->default(0),
                                Textarea::make('note')
                                    ->label('Catatan')
                                    ->nullable(),
                                Repeater::make('satuan_konversi')
                                    ->label('Satuan Konversi')
                                    ->disabled()
                                    ->reactive()
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        Select::make('uom_id')
                                            ->label('Satuan')
                                            ->preload()
                                            ->reactive()
                                            ->searchable()
                                            ->relationship('uom', 'name')
                                            ->required(),
                                        TextInput::make('nilai_konversi')
                                            ->label('Nilai Konversi')
                                            ->numeric()
                                            ->reactive()
                                            ->required(),
                                    ])
                            ])

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode BOM')
                    ->searchable(),
                TextColumn::make('nama_bom')
                    ->label('Nama BOM')
                    ->searchable(),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('cabang', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    }),
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function (Builder $query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom.name')
                    ->label('Unit of measure (Satuan)')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('items.product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->label("Material")
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            'index' => Pages\ListBillOfMaterials::route('/'),
            'create' => Pages\CreateBillOfMaterial::route('/create'),
            'view' => ViewBillOfMaterial::route('/{record}'),
            'edit' => Pages\EditBillOfMaterial::route('/{record}/edit'),
        ];
    }
}
