<?php

namespace App\Filament\Resources\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProfitAndLossResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = null; // intentionally hidden; merged into JournalEntry navigation
    // Ensure this report resource does not register its own navigation item
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 22;
    protected static ?string $model = JournalEntry::class;

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
            'index' => ProfitAndLossResource\Pages\ViewProfitAndLoss::route('/'),
        ];
    }
}
