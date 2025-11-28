<?php

namespace App\Filament\Resources\DepositResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class DepositLogRelationManager extends RelationManager
{
    protected static string $relationship = 'depositLog';

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                    
                TextColumn::make('type')
                    ->label('Transaction Type')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'create' => 'primary',
                            'use' => 'success',
                            'add' => 'info',
                            'return' => 'warning',
                            'cancel' => 'danger',
                            'edit' => 'warning',
                            'adjustment' => 'gray'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'create' => 'CREATE',
                            'use' => 'USED',
                            'add' => 'ADDED',
                            'return' => 'RETURNED',
                            'cancel' => 'CANCELLED',
                            'edit' => 'EDITED',
                            'adjustment' => 'ADJUSTMENT',
                            default => Str::upper($state)
                        };
                    })
                    ->searchable(),
                    
                TextColumn::make('amount')
                    ->label('Amount')
                    ->sortable()
                    ->money('IDR')
                    ->color(function ($state, $record) {
                        return match ($record->type) {
                            'create', 'add', 'return' => 'success',
                            'use', 'cancel' => 'danger',
                            default => 'primary'
                        };
                    }),
                    
                TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state == 'App\Models\Customer') {
                            return "Customer Payment";
                        } elseif ($state == 'App\Models\Supplier') {
                            return 'Supplier Payment';
                        } elseif ($state == 'App\Models\Deposit') {
                            return "Deposit Operation";
                        } elseif ($state == 'App\Models\VendorPaymentDetail') {
                            return "Vendor Payment";
                        } elseif ($state == 'App\Models\CustomerReceiptItem') {
                            return "Customer Receipt";
                        }
                        return $state ? class_basename($state) : 'Manual';
                    })
                    ->badge()
                    ->color('gray'),
                    
                TextColumn::make('note')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if ($state && strlen($state) > 50) {
                            return $state;
                        }
                        return null;
                    }),
                    
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
                    ->default('System'),
                    
                TextColumn::make('remaining_balance')
                    ->label('Balance After')
                    ->money('IDR')
                    ->sortable()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'create' => 'Create',
                        'use' => 'Used',
                        'add' => 'Added',
                        'return' => 'Returned',
                        'cancel' => 'Cancelled',
                        'adjustment' => 'Adjustment',
                    ])
                    ->multiple(),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('from_date')
                                    ->label('From Date'),
                                Forms\Components\DatePicker::make('to_date')
                                    ->label('To Date'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                    
                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_amount')
                                    ->label('Min Amount')
                                    ->numeric()
                                    ->indonesianMoney(),
                                Forms\Components\TextInput::make('max_amount')
                                    ->label('Max Amount')
                                    ->numeric()
                                    ->indonesianMoney(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export History')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        // Export functionality can be added here
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No deposit history found')
            ->emptyStateDescription('Deposit history will appear here as transactions are made.');
    }
}
