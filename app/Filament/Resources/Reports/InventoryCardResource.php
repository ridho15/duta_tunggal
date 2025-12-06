<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\InventoryCardResource\Pages;
use App\Models\StockMovement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryCardResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 21;

    protected static ?string $slug = 'reports/inventory-card';

    protected static ?string $navigationLabel = 'Kartu Persediaan';

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
        return parent::getEloquentQuery()->whereHas('warehouse', function (Builder $query) {
            // Cabang filtering akan diterapkan melalui global scope di Warehouse model
            // yang sudah memiliki CabangScope
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewInventoryCard::route('/'),
        ];
    }
}
