<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountPayableResource\Pages;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
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
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\AccountPayableResource\RelationManagers;

class AccountPayableResource extends Resource
{
    protected static ?string $model = AccountPayable::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance - Pembelian';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Account Payable')
                    ->schema([
                        Select::make('invoice_id')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->label('Invoice')
                            ->afterStateUpdated(function ($state, $set) {
                                $invoice = Invoice::find($state);
                                if ($invoice) {
                                    $set('supplier_id', $invoice->fromModel->supplier_id);
                                    $set('total', (float) $invoice->total);
                                    $set('remaining', (float) $invoice->total);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Invoice belum dipilih',
                            ])
                            ->relationship('invoice', 'invoice_number', function (Builder $query, $get) {
                                $query->where('from_model_type', 'App\Models\PurchaseOrder');
                            }),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Supplier belum dipilih'
                            ])
                            ->searchable(['name', 'code'])
                            ->required()
                            ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                                return "({$supplier->code}) {$supplier->name}";
                            })
                            ->relationship('supplier', 'name'),
                        TextInput::make('total')
                            ->required()
                            ->indonesianMoney()
                            ->numeric()
                            ->validationMessages([
                                'required' => 'Total tidak boleh kosong',
                                'numeric' => 'Total harus berupa angka'
                            ])
                            ->readonly()
                            ->reactive()
                            ->dehydrateStateUsing(function ($state) {
                                // Ensure total is properly processed for readonly field
                                if (is_string($state)) {
                                    // Remove formatting and convert to float
                                    $cleaned = preg_replace('/[^\d.,]/', '', $state);
                                    // Handle Indonesian format (dots as thousand separators, comma as decimal)
                                    $parts = explode(',', $cleaned);
                                    if (count($parts) > 1) {
                                        $integer = str_replace('.', '', $parts[0]);
                                        $decimal = $parts[1];
                                        return (float)($integer . '.' . $decimal);
                                    } else {
                                        $integer = str_replace('.', '', $parts[0]);
                                        return (float)$integer;
                                    }
                                }
                                return (float)$state;
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $total = is_numeric($state) ? (float) $state : 0;
                                $paid = is_numeric($get('paid')) ? (float) $get('paid') : 0;
                                $set('remaining', $total - $paid);
                            })
                            ->helperText('Total akan terisi otomatis berdasarkan invoice yang dipilih'),
                        TextInput::make('paid')
                            ->required()
                            ->indonesianMoney()
                            ->numeric()
                            ->validationMessages([
                                'required' => 'Jumlah pembayaran tidak boleh kosong',
                                'numeric' => 'Jumlah pembayaran harus berupa angka'
                            ])
                            ->default(0.00)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $total = is_numeric($get('total')) ? (float) $get('total') : 0;
                                $paid = is_numeric($state) ? (float) $state : 0;
                                $set('remaining', $total - $paid);
                            }),
                        TextInput::make('remaining')
                            ->required()
                            ->indonesianMoney()
                            ->numeric()
                            ->validationMessages([
                                'required' => 'Sisa pembayaran tidak boleh kosong',
                                'numeric' => 'Sisa pembayaran harus berupa angka'
                            ])
                            ->reactive()
                            ->helperText('Sisa pembayaran akan terisi otomatis berdasarkan total invoice'),
                        Radio::make('status')
                            ->label('Status Pembayaran')
                            ->options([
                                'Belum Lunas' => 'Belum Lunas',
                                'Lunas' => 'Lunas',
                            ])
                            ->default('Belum Lunas')
                            ->required()
                            ->validationMessages([
                                'required' => 'Status pembayaran harus dipilih'
                            ])
                            ->inline()
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Join invoices to allow computed grouping & sorting by overdue status
                $query->leftJoin('invoices', 'account_payables.invoice_id', '=', 'invoices.id')
                    ->select('account_payables.*')
                    ->addSelect(
                        DB::raw("CASE 
                            WHEN account_payables.status = 'Lunas' THEN 'PAID'
                            WHEN invoices.due_date < CURDATE() AND DATEDIFF(CURDATE(), invoices.due_date) > 60 THEN 'OVERDUE 60+ Days'
                            WHEN invoices.due_date < CURDATE() AND DATEDIFF(CURDATE(), invoices.due_date) > 30 THEN 'OVERDUE 30+ Days'
                            WHEN invoices.due_date < CURDATE() THEN 'OVERDUE'
                            ELSE 'CURRENT'
                        END AS overdue_group")
                    );
                // Eager load required relations still
                return $query->with(['invoice.fromModel']);
            })
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),
                    
                TextColumn::make('supplier')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->label('Supplier')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('supplier', function (Builder $query) use ($search) {
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
                            ->label('Total AP')
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
                    
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable()
                    ->default('System'),
                    
                TextColumn::make('invoice.fromModel.createdBy.name')
                    ->label('PO Created By')
                    ->getStateUsing(function ($record) {
                        return $record->invoice->fromModel?->createdBy?->name ?? 'System';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("
                            (SELECT name FROM users WHERE users.id = (
                                SELECT created_by FROM purchase_orders WHERE purchase_orders.id = invoices.from_model_id
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
                Tables\Grouping\Group::make('supplier.name')
                    ->label('Supplier')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return "🏢 ({$record->supplier->code}) {$record->supplier->name}";
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
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
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
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentHistoryRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('created_at', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountPayables::route('/'),
            'create' => Pages\CreateAccountPayable::route('/create'),
            'view' => Pages\ViewAccountPayable::route('/{record}'),
            'edit' => Pages\EditAccountPayable::route('/{record}/edit'),
        ];
    }
}
