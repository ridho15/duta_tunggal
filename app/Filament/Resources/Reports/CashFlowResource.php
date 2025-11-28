<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\CashFlowResource\Pages;
use App\Models\CashBankTransaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CashFlowResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 24;
    protected static ?string $slug = 'reports/cash-flow';
    protected static ?string $model = CashBankTransaction::class;
    protected static ?string $navigationLabel = 'Laporan Arus Kas';

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
            'index' => Pages\ViewCashFlow::route('/'),
        ];
    }
}
