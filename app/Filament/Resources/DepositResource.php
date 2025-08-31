<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepositResource\Pages;
use App\Filament\Resources\DepositResource\Pages\ViewDeposit;
use App\Filament\Resources\DepositResource\Pages\ViewDepositLog;
use App\Filament\Resources\DepositResource\RelationManagers;
use App\Filament\Resources\DepositResource\RelationManagers\DepositLogRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
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
                Section::make('Deposit Information')
                    ->description('Create or manage deposit for Customer/Supplier')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Radio::make('from_model_type')
                                    ->required()
                                    ->reactive()
                                    ->inlineLabel()
                                    ->options([
                                        'App\Models\Supplier' => 'Supplier',
                                        'App\Models\Customer' => 'Customer'
                                    ])
                                    ->label('Entity Type')
                                    ->helperText('Select whether this deposit is from a Customer or Supplier'),
                                    
                                Select::make('from_model_id')
                                    ->required()
                                    ->searchable()
                                    ->options(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\Supplier') {
                                            return Supplier::get()->pluck('code', 'id');
                                        } elseif ($get('from_model_type') == 'App\Models\Customer') {
                                            return Customer::get()->pluck('code', 'id');
                                        }
                                        return [];
                                    })
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "({$record->code}) {$record->name}";
                                    })
                                    ->label(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\Supplier') {
                                            return 'Select Supplier';
                                        } elseif ($get('from_model_type') == 'App\Models\Customer') {
                                            return 'Select Customer';
                                        }
                                        return "Select Entity";
                                    })
                                    ->validationMessages([
                                        'required' => 'Please select an entity'
                                    ])
                                    ->helperText('Choose the specific customer or supplier for this deposit'),
                            ]),
                            
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Deposit Amount')
                                    ->prefix('Rp')
                                    ->required()
                                    ->default(0)
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $usedAmount = $get('used_amount') ?? 0;
                                        $set('remaining_amount', $state - $usedAmount);
                                    })
                                    ->helperText('Total deposit amount'),
                                    
                                TextInput::make('used_amount')
                                    ->required()
                                    ->label('Used Amount')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $totalAmount = $get('amount') ?? 0;
                                        $set('remaining_amount', $totalAmount - $state);
                                    })
                                    ->helperText('Amount already used'),
                                    
                                TextInput::make('remaining_amount')
                                    ->required()
                                    ->label('Remaining Amount')
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Calculated automatically'),
                            ]),
                            
                        Select::make('coa_id')
                            ->label('Chart Of Account')
                            ->required()
                            ->preload()
                            ->searchable(['code', 'name'])
                            ->relationship('coa', 'code')
                            ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                            })
                            ->helperText('Select the appropriate chart of account for this deposit'),
                            
                        Textarea::make('note')
                            ->label('Notes')
                            ->string()
                            ->placeholder('Enter any additional notes about this deposit...')
                            ->rows(3),
                            
                        Forms\Components\Toggle::make('status')
                            ->label('Active Status')
                            ->default(true)
                            ->helperText('Toggle to activate/deactivate this deposit')
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->label('Type')
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
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('idr')
                            ->label('Total Used')
                    ]),
                    
                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('idr')
                    ->sortable()
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
                        if (strlen($state) > 30) {
                            return $state;
                        }
                        return null;
                    }),
                    
                IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                TextColumn::make('createdBy.name')
                    ->searchable()
                    ->label('Created By')
                    ->toggleable(),
                    
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
                    
                TextColumn::make('deleted_at')
                    ->label('Deleted At')
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
                    ->label('Entity Type')
                    ->options([
                        'App\Models\Customer' => 'Customer',
                        'App\Models\Supplier' => 'Supplier',
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                    ])
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('from_model_id')
                    ->label('Specific Entity')
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
