<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountReceivableResource\Pages;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

class AccountReceivableResource extends Resource
{
    protected static ?string $model = AccountReceivable::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 19;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Account Receivable')
                    ->schema([
                        Select::make('invoice_id')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($get, $set, $state) {
                                $invoice = Invoice::find($state);
                                if ($invoice) {
                                    $set('customer_id', $invoice->fromModel->customer_id);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Invoice belum dipilih'
                            ])
                            ->label('Invoice')
                            ->relationship('invoice', 'invoice_number', function (Builder $query, $get) {
                                $query->where('from_model_type', 'App\Models\SaleOrder');
                            }),
                        Select::make('customer_id')
                            ->label('Customer')
                            ->preload()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Customer belum dipilih'
                            ])
                            ->searchable(['name', 'code'])
                            ->required()
                            ->getOptionLabelFromRecordUsing(function (Customer $customer) {
                                return "({$customer->code}) {$customer->name}";
                            })
                            ->relationship('customer', 'name'),
                        TextInput::make('total')
                            ->required()
                            ->prefix('Rp')
                            ->numeric(),
                        TextInput::make('paid')
                            ->required()
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0.00),
                        TextInput::make('remaining')
                            ->required()
                            ->prefix('Rp')
                            ->numeric(),
                        Checkbox::make('status')
                            ->label('Lunas / Belum Lunas')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),
                    
                TextColumn::make('customer')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->label('Customer')
                    ->searchable(['code', 'name'])
                    ->sortable(),
                    
                TextColumn::make('invoice.invoice_date')
                    ->label('Invoice Date')
                    ->date('M j, Y')
                    ->sortable(),
                    
                TextColumn::make('invoice.due_date')
                    ->label('Due Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(function ($record) {
                        if ($record->invoice->due_date < now() && $record->status === 'Belum Lunas') {
                            return 'danger';
                        }
                        return 'gray';
                    }),
                    
                TextColumn::make('total')
                    ->label('Total Amount')
                    ->sortable()
                    ->money('idr')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total AR')
                    ]),
                    
                TextColumn::make('paid')
                    ->label('Paid Amount')
                    ->sortable()
                    ->money('idr')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total Paid')
                    ]),
                    
                TextColumn::make('remaining')
                    ->label('Outstanding')
                    ->sortable()
                    ->money('idr')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total Outstanding')
                    ]),
                    
                TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->getStateUsing(function ($record) {
                        if ($record->status === 'Belum Lunas' && $record->invoice->due_date < now()) {
                            return now()->diffInDays($record->invoice->due_date);
                        }
                        return 0;
                    })
                    ->color(function ($state) {
                        if ($state > 30) return 'danger';
                        if ($state > 0) return 'warning';
                        return 'success';
                    })
                    ->badge()
                    ->sortable(),
                    
                TextColumn::make('status')
                    ->label('Status')
                    ->color(function ($state) {
                        return match ($state) {
                            'Belum Lunas' => 'warning',
                            'Lunas' => 'success',
                            default => 'gray'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->badge()
                    ->sortable(),
                    
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort([
                ['invoice.due_date', 'desc'],
                ['remaining', 'desc']
            ])
            ->groups([
                Tables\Grouping\Group::make('customer.name')
                    ->label('Customer')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return "👤 ({$record->customer->code}) {$record->customer->name}";
                    })
                    ->collapsible(),
                    
                Tables\Grouping\Group::make('status')
                    ->label('Payment Status')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return $record->status === 'Lunas' ? '✅ PAID' : '⏳ OUTSTANDING';
                    })
                    ->collapsible(),
                    
                Tables\Grouping\Group::make('overdue_group')
                    ->label('Overdue Status')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        if ($record->status === 'Lunas') return '✅ PAID';
                        
                        $daysOverdue = $record->invoice->due_date < now() 
                            ? now()->diffInDays($record->invoice->due_date) 
                            : 0;
                            
                        if ($daysOverdue > 60) return '🚨 OVERDUE 60+ Days';
                        if ($daysOverdue > 30) return '⚠️ OVERDUE 30+ Days';
                        if ($daysOverdue > 0) return '⏰ OVERDUE';
                        return '💚 CURRENT';
                    })
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->code}) {$record->name}";
                    }),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Payment Status')
                    ->options([
                        'Belum Lunas' => 'Outstanding',
                        'Lunas' => 'Paid',
                    ])
                    ->multiple(),
                    
                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_from')
                                    ->label('Amount From')
                                    ->numeric()
                                    ->prefix('Rp'),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('Amount To')
                                    ->numeric()
                                    ->prefix('Rp'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['amount_from'], fn (Builder $query, $amount): Builder => 
                                $query->where('total', '>=', $amount))
                            ->when($data['amount_to'], fn (Builder $query, $amount): Builder => 
                                $query->where('total', '<=', $amount));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['amount_from'] ?? null) {
                            $indicators['amount_from'] = 'Amount from: Rp ' . number_format($data['amount_from']);
                        }
                        if ($data['amount_to'] ?? null) {
                            $indicators['amount_to'] = 'Amount to: Rp ' . number_format($data['amount_to']);
                        }
                        return $indicators;
                    }),
                    
                Tables\Filters\Filter::make('outstanding_only')
                    ->label('Outstanding Only')
                    ->query(fn (Builder $query): Builder => $query->where('remaining', '>', 0))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Invoices')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('invoice', function (Builder $query) {
                            $query->where('due_date', '<', now());
                        })->where('status', 'Belum Lunas');
                    })
                    ->toggle(),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('created_from')
                                    ->label('Created From'),
                                Forms\Components\DatePicker::make('created_until')
                                    ->label('Created Until'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $query, $date): Builder => 
                                $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $query, $date): Builder => 
                                $query->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Created from: ' . \Carbon\Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Created until: ' . \Carbon\Carbon::parse($data['created_until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                    
                Tables\Filters\Filter::make('due_date_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('due_from')
                                    ->label('Due From'),
                                Forms\Components\DatePicker::make('due_until')
                                    ->label('Due Until'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereHas('invoice', function (Builder $query) use ($data) {
                            $query->when($data['due_from'], fn (Builder $query, $date): Builder => 
                                    $query->whereDate('due_date', '>=', $date))
                                ->when($data['due_until'], fn (Builder $query, $date): Builder => 
                                    $query->whereDate('due_date', '<=', $date));
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['due_from'] ?? null) {
                            $indicators['due_from'] = 'Due from: ' . \Carbon\Carbon::parse($data['due_from'])->toFormattedDateString();
                        }
                        if ($data['due_until'] ?? null) {
                            $indicators['due_until'] = 'Due until: ' . \Carbon\Carbon::parse($data['due_until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                    
                Tables\Filters\SelectFilter::make('overdue_days')
                    ->label('Overdue Period')
                    ->options([
                        '1-30' => '1-30 Days',
                        '31-60' => '31-60 Days',
                        '60+' => '60+ Days',
                    ])
                    ->query(function (Builder $query, $data) {
                        if (!$data['value']) return $query;
                        
                        return $query->whereHas('invoice', function (Builder $query) use ($data) {
                            $now = now();
                            switch ($data['value']) {
                                case '1-30':
                                    $query->whereBetween('due_date', [$now->copy()->subDays(30), $now->copy()->subDay()]);
                                    break;
                                case '31-60':
                                    $query->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)]);
                                    break;
                                case '60+':
                                    $query->where('due_date', '<', $now->copy()->subDays(60));
                                    break;
                            }
                        })->where('status', 'Belum Lunas');
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                ])->button()
                    ->label('Action')
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('created_at', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountReceivables::route('/'),
            'create' => Pages\CreateAccountReceivable::route('/create'),
            'edit' => Pages\EditAccountReceivable::route('/{record}/edit'),
        ];
    }
}
