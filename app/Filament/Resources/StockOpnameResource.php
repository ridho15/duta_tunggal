<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Filament\Resources\StockOpnameResource\RelationManagers;
use App\Models\StockOpname;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 5;

    protected static ?string $label = 'Stock Opname';

    protected static ?string $pluralLabel = 'Stock Opnames';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Informasi Stock Opname')
                    ->schema([
                        TextInput::make('opname_number')
                            ->label('Nomor Opname')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Nomor opname harus diisi',
                                'unique' => 'Nomor opname sudah digunakan'
                            ]),

                        DatePicker::make('opname_date')
                            ->label('Tanggal Opname')
                            ->required()
                            ->default(now())
                            ->validationMessages([
                                'required' => 'Tanggal opname harus diisi'
                            ]),

                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(Warehouse::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->validationMessages([
                                'required' => 'Warehouse harus dipilih'
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'in_progress' => 'Sedang Berlangsung',
                                'completed' => 'Selesai',
                                'approved' => 'Disetujui',
                            ])
                            ->default('draft')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('opname_number'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
