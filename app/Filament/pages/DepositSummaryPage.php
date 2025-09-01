<?php

namespace App\Filament\pages;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Supplier;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepositSummaryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.deposit-summary-page';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Deposit Summary';

    protected static ?int $navigationSort = 24;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Deposit::query()
                    ->selectRaw('
                        from_model_type,
                        from_model_id,
                        SUM(amount) as total_deposit,
                        SUM(used_amount) as total_used,
                        SUM(remaining_amount) as total_remaining,
                        COUNT(*) as deposit_count,
                        MAX(created_at) as latest_deposit
                    ')
                    ->with(['fromModel'])
                    ->groupBy('from_model_type', 'from_model_id')
                    ->orderBy('from_model_type')
                    ->orderByDesc('total_remaining')
            )
            ->columns([
                Tables\Columns\TextColumn::make('fromModel')
                    ->label('Entity Name')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(['code', 'name'])
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('from_model_type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        return $state === 'App\Models\Customer' ? 'Customer' : 'Supplier';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'App\Models\Customer' => 'success',
                        'App\Models\Supplier' => 'info',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('deposit_count')
                    ->label('# Deposits')
                    ->alignCenter()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_deposit')
                    ->label('Total Deposit')
                    ->money('idr')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_used')
                    ->label('Total Used')
                    ->money('idr')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_remaining')
                    ->label('Available Balance')
                    ->money('idr')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('latest_deposit')
                    ->label('Latest Deposit')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->groups([
                Tables\Grouping\Group::make('from_model_type')
                    ->label('Entity Type')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return $record->from_model_type === 'App\Models\Customer' ? 
                            '👥 CUSTOMERS' : '🏢 SUPPLIERS';
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('from_model_type')
            ->filters([
                Tables\Filters\SelectFilter::make('from_model_type')
                    ->label('Entity Type')
                    ->options([
                        'App\Models\Customer' => 'Customer',
                        'App\Models\Supplier' => 'Supplier',
                    ]),
                    
                Tables\Filters\Filter::make('has_balance')
                    ->label('Has Available Balance')
                    ->query(fn (Builder $query): Builder => 
                        $query->havingRaw('SUM(remaining_amount) > 0')
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-m-eye')
                    ->url(function ($record) {
                        return route('filament.admin.resources.deposits.index', [
                            'tableFilters' => [
                                'from_model_id' => ['values' => [$record->from_model_id]]
                            ]
                        ]);
                    })
                    ->openUrlInNewTab(),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public function getTitle(): string
    {
        return 'Deposit Summary by Customer & Supplier';
    }
}
