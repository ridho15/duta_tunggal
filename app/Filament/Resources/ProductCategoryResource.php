<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Validation\Rule;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $modelLabel = 'Kategori Produk';

    protected static ?string $pluralModelLabel = 'Kategori Produk';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 26;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama Kategori')
                    ->maxLength(100)
                    ->validationMessages([
                        'required' => 'Nama kategori tidak boleh kosong',
                        'max' => 'Nama kategori terlalu panjang'
                    ])
                    ->required(),
                TextInput::make('kode')
                    ->label('Kode Kategori')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($record) {
                        return Rule::unique('product_categories', 'kode')
                            ->where('deleted_at', null)
                            ->ignore($record?->id ?? null);
                    })
                    ->validationMessages([
                        'requried' => "Kode Kategori tidak boleh kosong",
                        'unique' => 'Kode Kategori sudah digunakan'
                    ])
                    ->required(),
                Select::make('cabang_id')
                    ->label('Cabang')
                    ->preload()
                    ->searchable()
                    ->relationship('cabang', 'nama')
                    ->required(),
                TextInput::make('kenaikan_harga')
                    ->label('Kenaikan Harga (%)')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode Kategori')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('kenaikan_harga')
                    ->label('Kenaikan Harga (%)')
                    ->suffix('%'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
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
            'index' => Pages\ListProductCategories::route('/'),
            // 'create' => Pages\CreateProductCategory::route('/create'),
            // 'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
