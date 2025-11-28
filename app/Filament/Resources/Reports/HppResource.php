<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\HppResource\Pages;
use App\Models\JournalEntry;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class HppResource extends Resource
{
    protected static ?string $model = JournalEntry::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 23;
    protected static ?string $slug = 'reports/hpp';
    protected static ?string $navigationLabel = 'Laporan HPP';

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
            'index' => Pages\ViewHpp::route('/'),
        ];
    }
}
