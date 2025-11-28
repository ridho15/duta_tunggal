<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepositAdjustmentResource\Pages;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Supplier;
use Filament\Forms;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class DepositAdjustmentResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Finance';

    // Hide from Filament sidebar to avoid duplicate menu entry with `Deposit`.
    // The page remains accessible at `/admin/deposit-adjustments` and
    // we add a link on the main Deposit page for discovery.
    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Deposit Adjustment';

    protected static ?string $pluralModelLabel = 'Deposit Adjustments';

    protected static ?int $navigationSort = 14;

    // Do not register this resource in the Filament sidebar navigation.
    // The resource remains accessible at `/admin/deposit-adjustments`
    // and is reachable from the Deposit resource header action.
    protected static bool $shouldRegisterNavigation = false;

    // Only accessible by Finance role
    public static function canAccess(): bool
    {
        return Auth::check() && (
            Auth::user()->hasRole('Super Admin') || 
            Auth::user()->hasRole('Finance') ||
            Auth::user()->hasRole('Administrator')
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Deposit Adjustment for Finance')
                    ->description('Khusus untuk penyesuaian deposit oleh tim Finance')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Radio::make('from_model_type')
                                    ->label('Entity Type')
                                    ->required()
                                    ->reactive()
                                    ->options([
                                        'App\Models\Supplier' => 'Supplier',
                                        'App\Models\Customer' => 'Customer'
                                    ])
                                    ->inline(),
                                    
                                Select::make('from_model_id')
                                    ->label('Select Entity')
                                    ->required()
                                    ->searchable()
                                    ->options(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\Supplier') {
                                            return Supplier::get()->pluck('name', 'id');
                                        } elseif ($get('from_model_type') == 'App\Models\Customer') {
                                            return Customer::get()->pluck('name', 'id');
                                        }
                                        return [];
                                    })
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "({$record->code}) {$record->name}";
                                    })
                                    ->reactive(),
                            ]),
                            
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Deposit Amount')
                                    ->indonesianMoney()
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                    
                                TextInput::make('used_amount')
                                    ->label('Used Amount')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->default(0),
                                    
                                TextInput::make('remaining_amount')
                                    ->label('Remaining Amount')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->default(0),
                            ]),
                            
                        Select::make('coa_id')
                            ->label('Chart Of Account')
                            ->required()
                            ->searchable(['code', 'name'])
                            ->relationship('coa', 'code')
                            ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                            }),
                            
                        Textarea::make('note')
                            ->label('Adjustment Note')
                            ->required()
                            ->placeholder('Enter reason for adjustment...'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fromModel')
                    ->label('Entity')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(),
                    
                TextColumn::make('from_model_type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        return $state == 'App\Models\Supplier' ? 'Supplier' : 'Customer';
                    }),
                    
                TextColumn::make('amount')
                    ->label('Total Deposit')
                    ->money('IDR')
                    ->sortable(),
                    
                TextColumn::make('used_amount')
                    ->label('Used Amount')
                    ->money('IDR')
                    ->sortable(),
                    
                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('IDR')
                    ->sortable(),
                    
                TextColumn::make('coa.code')
                    ->label('COA')
                    ->formatStateUsing(function ($record) {
                        return "({$record->coa->code}) {$record->coa->name}";
                    }),
                    
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'closed' => 'danger',
                    }),
                    
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable(),
                    
                TextColumn::make('created_at')
                    ->label('Date Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    
                    Action::make('adjust_deposit')
                        ->label('Adjust Amount')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('warning')
                        ->modal()
                        ->form([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('adjustment_amount')
                                        ->label('Adjustment Amount')
                                        ->indonesianMoney()
                                        ->numeric()
                                        ->required()
                                        ->helperText('Positive to add, negative to subtract'),
                                        
                                    Textarea::make('adjustment_note')
                                        ->label('Adjustment Reason')
                                        ->required()
                                        ->placeholder('Enter reason for this adjustment...'),
                                ])
                        ])
                        ->action(function (array $data, Deposit $record) {
                            $adjustment = $data['adjustment_amount'];
                            $oldAmount = $record->amount;
                            $oldRemaining = $record->remaining_amount;
                            
                            // Update amounts
                            $record->amount += $adjustment;
                            $record->remaining_amount += $adjustment;
                            $record->save();
                            
                            // Log the adjustment
                            $record->depositLogRef()->create([
                                'deposit_id' => $record->id,
                                'type' => $adjustment > 0 ? 'add' : 'adjustment',
                                'amount' => $adjustment,
                                'note' => "Finance Adjustment: {$data['adjustment_note']} (Old: Rp " . number_format($oldAmount) . ", New: Rp " . number_format($record->amount) . ")",
                                'created_by' => Auth::id()
                            ]);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Deposit Adjusted')
                                ->success()
                                ->body("Deposit adjusted by Rp " . number_format(abs($adjustment)) . ($adjustment > 0 ? ' (added)' : ' (deducted)'))
                                ->send();
                        }),
                        
                    Action::make('close_deposit')
                        ->label('Close Deposit')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Deposit $record) {
                            $record->update(['status' => 'closed']);
                            
                            $record->depositLogRef()->create([
                                'deposit_id' => $record->id,
                                'type' => 'close',
                                'amount' => 0,
                                'note' => 'Deposit closed by Finance',
                                'created_by' => Auth::id()
                            ]);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Deposit Closed')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Deposit $record) => $record->status === 'active'),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->filters([
                Tables\Filters\SelectFilter::make('from_model_type')
                    ->label('Entity Type')
                    ->options([
                        'App\Models\Customer' => 'Customer',
                        'App\Models\Supplier' => 'Supplier',
                    ]),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepositAdjustments::route('/'),
            'create' => Pages\CreateDepositAdjustment::route('/create'),
            'view' => Pages\ViewDepositAdjustment::route('/{record}'),
            'edit' => Pages\EditDepositAdjustment::route('/{record}/edit'),
        ];
    }
}
