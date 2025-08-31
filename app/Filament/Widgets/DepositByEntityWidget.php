<?php

namespace App\Filament\Widgets;

use App\Models\Deposit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DepositByEntityWidget extends BaseWidget
{
    protected static ?string $heading = 'Deposits by Customer/Supplier';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Deposit::with(['fromModel', 'coa'])
                    ->where('status', 'active')
                    ->orderBy('from_model_type')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('fromModel')
                    ->label('Entity')
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
                    
                Tables\Columns\TextColumn::make('amount')
                    ->label('Total Deposit')
                    ->money('idr')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('used_amount')
                    ->label('Used')
                    ->money('idr')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Available')
                    ->money('idr')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
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
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
