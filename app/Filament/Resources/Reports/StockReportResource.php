<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\StockReportResource\Pages;
use App\Models\InventoryStock;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockReportResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Persediaan';

    protected static ?int $navigationSort = 28;

    protected static ?string $slug = 'reports/stock-report';

    protected static ?string $navigationLabel = 'Laporan Stok';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewStockReport::route('/'),
        ];
    }
}
