<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\StockMutationReportResource\Pages;
use App\Models\StockMovement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class StockMutationReportResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Finance - Laporan';

    protected static ?int $navigationSort = 27;

    protected static ?string $slug = 'reports/stock-mutation';

    protected static ?string $navigationLabel = 'Laporan Mutasi Barang';

    protected static ?string $modelLabel = 'Laporan Mutasi Barang';

    protected static ?string $pluralModelLabel = 'Laporan Mutasi Barang';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewStockMutationReport::route('/'),
        ];
    }
}