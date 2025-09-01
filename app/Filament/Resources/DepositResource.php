<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepositResource\Pages;
use App\Filament\Resources\DepositResource\Pages\ViewDeposit;
use App\Filament\Resources\DepositResource\RelationManagers\DepositLogRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class DepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-pound';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('From Supplier / Customer')
                    ->description('Referensi untuk membuat Deposit, tidak boleh di abaikan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Radio::make('from_model_type')
                                    ->label('From')
                                    ->required()
                                    ->reactive()
                                    ->inline()
                                    ->options([
                                        'App\Models\Supplier' => 'Supplier',
                                        'App\Models\Customer' => 'Customer'
                                    ])
                                    ->afterStateUpdated(function ($set) {
                                        $set('from_model_id', null);
                                    }),
                                    
                                Select::make('from_model_id')
                                    ->label('From')
                                    ->required()
                                    ->searchable()
                                    ->placeholder('Pilih salah satu opsi')
                                    ->options(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\Supplier') {
                                            return Supplier::get()->mapWithKeys(function ($supplier) {
                                                return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                            });
                                        } elseif ($get('from_model_type') == 'App\Models\Customer') {
                                            return Customer::get()->mapWithKeys(function ($customer) {
                                                return [$customer->id => "({$customer->code}) {$customer->name}"];
                                            });
                                        }
                                        return [];
                                    })
                                    ->preload(),
                            ]),
                    ]),
                    
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('deposit_number')
                            ->label('Nomor Deposit')
                            ->maxLength(255)
                            ->default(function () {
                                return 'DEP-' . date('Y-m-d') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                            }),
                            
                        TextInput::make('amount')
                            ->label('Total')
                            ->prefix('Rp')
                            ->required()
                            ->default(0)
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $usedAmount = $get('used_amount') ?? 0;
                                $set('remaining_amount', $state - $usedAmount);
                            }),
                    ]),
                    
                Section::make()
                    ->columns(2)
                    ->schema([
                        Textarea::make('note')
                            ->label('Catatan')
                            ->placeholder('Masukkan catatan deposit...')
                            ->rows(3),
                            
                        Select::make('coa_id')
                            ->label('Chart Of Account')
                            ->required()
                            ->preload()
                            ->searchable(['code', 'name'])
                            ->placeholder('Pilih salah satu opsi')
                            ->relationship('coa', 'code')
                            ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                            }),
                    ]),
                    
                // Hidden fields for backward compatibility
                Hidden::make('used_amount')->default(0),
                Hidden::make('remaining_amount')
                    ->default(function ($get) {
                        return $get('amount') ?? 0;
                    }),
                Hidden::make('status')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deposit_number')
                    ->label('Nomor Deposit')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                    
                TextColumn::make('fromModel')
                    ->label('Nama')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('fromModel', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->sortable(),
                    
                TextColumn::make('from_model_type')
                    ->label('Role')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'App\Models\Supplier') {
                            return 'Supplier';
                        } elseif ($state == 'App\Models\Customer') {
                            return 'Customer';
                        }
                        return '-';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'App\Models\Customer' => 'success',
                        'App\Models\Supplier' => 'info',
                        default => 'gray',
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $searchLower = strtolower($search);
                        if (str_contains('customer', $searchLower)) {
                            $query->where('from_model_type', 'App\Models\Customer');
                        } elseif (str_contains('supplier', $searchLower)) {
                            $query->where('from_model_type', 'App\Models\Supplier');
                        }
                    })
                    ->sortable(),
                    
                TextColumn::make('amount')
                    ->label('Total Deposit')
                    ->money('idr')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total All Deposits')
                    ]),
                    
                TextColumn::make('used_amount')
                    ->label('Used Amount')
                    ->money('idr')
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total Used')
                    ]),
                    
                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('idr')
                    ->sortable()
                    ->toggleable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total Remaining')
                    ]),
                    
                TextColumn::make('coa')
                    ->label('Chart Of Account')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('coa', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if ($state && strlen("({$state->code}) {$state->name}") > 30) {
                            return "({$state->code}) {$state->name}";
                        }
                        return null;
                    })
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
                    
                TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if ($state && strlen($state) > 50) {
                            return $state;
                        }
                        return null;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(),
                    
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('from_model_type')
                    ->label('Entity Type')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return $record->from_model_type === 'App\Models\Customer' ? 
                            '👥 CUSTOMERS' : '🏢 SUPPLIERS';
                    })
                    ->collapsible(),
                    
                Tables\Grouping\Group::make('fromModel.name')
                    ->label('Entity Name')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return "({$record->fromModel->code}) {$record->fromModel->name}";
                    })
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_model_type')
                    ->label('Role')
                    ->options([
                        'App\Models\Customer' => 'Customer',
                        'App\Models\Supplier' => 'Supplier',
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('from_model_id')
                    ->label('Nama')
                    ->relationship('fromModel', 'name')
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->code}) {$record->name}";
                    })
                    ->multiple(),
                    
                Tables\Filters\Filter::make('has_remaining')
                    ->label('Has Remaining Balance')
                    ->query(fn (Builder $query): Builder => $query->where('remaining_amount', '>', 0)),
                    
                Tables\Filters\Filter::make('empty_balance')
                    ->label('Empty Balance')
                    ->query(fn (Builder $query): Builder => $query->where('remaining_amount', '<=', 0)),
                    
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
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('created_from')
                                    ->label('Created From'),
                                Forms\Components\DatePicker::make('created_until')
                                    ->label('Created Until'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('tambahSaldo')
                        ->label('Tambah Saldo')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->modal()
                        ->modalSubmitActionLabel("Tambah Saldo")
                        ->form(function () {
                            return [
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->label('Total')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Total tidak boleh kosong'
                                            ]),
                                        Textarea::make('note')
                                            ->label('Catatan')
                                            ->string()
                                            ->nullable(),
                                    ])
                            ];
                        })
                        ->action(function (array $data, $record) {
                            $record->amount += $data['amount'];
                            $record->remaining_amount += $data['amount'];
                            $record->save();
                            $record->depositLogRef()->create([
                                'deposit_id' => $record->id,
                                'type' => 'add',
                                'amount' => $data['amount'],
                                'note' => $data['note'],
                                'created_by' => Auth::user()->id
                            ]);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Saldo berhasil di tambahkan");
                        })
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DepositLogRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeposits::route('/'),
            'create' => Pages\CreateDeposit::route('/create'),
            'view' => ViewDeposit::route('/{record}'),
            'edit' => Pages\EditDeposit::route('/{record}/edit'),
        ];
    }
}
