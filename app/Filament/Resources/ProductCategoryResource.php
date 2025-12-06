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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $modelLabel = 'Kategori Produk';

    protected static ?string $pluralModelLabel = 'Kategori Produk';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 7;

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
                        'required' => "Kode Kategori tidak boleh kosong",
                        'max' => 'Kode kategori terlalu panjang',
                        'unique' => 'Kode Kategori sudah digunakan'
                    ])
                    ->required(),
                Select::make('cabang_id')
                    ->label('Cabang')
                    ->options(\App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                    }))
                    ->preload()
                    ->searchable()
                    ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                    ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                    ->validationMessages([
                        'required' => 'Cabang harus dipilih'
                    ])
                    ->required(),
                TextInput::make('kenaikan_harga')
                    ->label('Kenaikan Harga (%)')
                    ->numeric()
                    ->validationMessages([
                        'numeric' => 'Kenaikan harga harus berupa angka'
                    ])
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
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->sortable(),
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
