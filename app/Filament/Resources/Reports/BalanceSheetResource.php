<?php

namespace App\Filament\Resources\Reports;

use App\Models\JournalEntry;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class BalanceSheetResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Laporan Keuangan';
    protected static ?string $navigationLabel = 'Balance Sheet';
    protected static ?string $navigationParentItem = 'Laporan Keuangan';
    protected static ?int $navigationSort = 4;
    protected static ?string $model = JournalEntry::class;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => BalanceSheetResource\Pages\ViewBalanceSheet::route('/'),
        ];
    }
}
