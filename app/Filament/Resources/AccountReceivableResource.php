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

    protected static ?string $navigationGroup = 'Finance - Penjualan';

    protected static ?int $navigationSort = 4;

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
                            ->indonesianMoney()
                            ->numeric(),
                        TextInput::make('paid')
                            ->required()
                            ->indonesianMoney()
                            ->numeric()
                            ->default(0.00),
                        TextInput::make('remaining')
                            ->required()
                            ->indonesianMoney()
                            ->numeric(),
                        Checkbox::make('status')
                            ->label('Lunas / Belum Lunas')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['invoice.fromModel']);
            })
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search) {
                            $query->where('code', 'like', "%{$search}%")
                                  ->orWhere('name', 'like', "%{$search}%");
                        });
                    })
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
                    ->searchable()
                    ->money('IDR')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total AR')
                    ]),
                    
                TextColumn::make('paid')
                    ->label('Paid Amount')
                    ->sortable()
                    ->money('IDR')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Paid')
                    ]),
                    
                TextColumn::make('remaining')
                    ->label('Outstanding')
                    ->sortable()
                    ->money('IDR')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
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
                    
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable()
                    ->default('System'),
                    
                TextColumn::make('invoice.fromModel.createdBy.name')
                    ->label('SO Created By')
                    ->getStateUsing(function ($record) {
                        return $record->invoice->fromModel?->createdBy?->name ?? 'System';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("
                            (SELECT name FROM users WHERE users.id = (
                                SELECT created_by FROM sale_orders WHERE sale_orders.id = invoices.from_model_id
                            )) {$direction}
                        ");
                    }),
                    
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
            ->defaultSort('invoice.due_date', 'desc')
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
                                    ->indonesianMoney(),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('Amount To')
                                    ->numeric()
                                    ->indonesianMoney(),
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
                                    ->label('Start Date'),
                                Forms\Components\DatePicker::make('created_until')
                                    ->label('End Date'),
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
                    
                Tables\Filters\Filter::make('invoice_date_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('invoice_from')
                                    ->label('Invoice Date From'),
                                Forms\Components\DatePicker::make('invoice_until')
                                    ->label('Invoice Date Until'),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereHas('invoice', function (Builder $query) use ($data) {
                            $query->when($data['invoice_from'], fn (Builder $query, $date): Builder => 
                                    $query->whereDate('invoice_date', '>=', $date))
                                ->when($data['invoice_until'], fn (Builder $query, $date): Builder => 
                                    $query->whereDate('invoice_date', '<=', $date));
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['invoice_from'] ?? null) {
                            $indicators['invoice_from'] = 'Invoice from: ' . \Carbon\Carbon::parse($data['invoice_from'])->toFormattedDateString();
                        }
                        if ($data['invoice_until'] ?? null) {
                            $indicators['invoice_until'] = 'Invoice until: ' . \Carbon\Carbon::parse($data['invoice_until'])->toFormattedDateString();
                        }
                        return $indicators;
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
