<?php

namespace App\Filament\Actions;

use App\Models\ChartOfAccount;
use App\Models\Deposit;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

class AddDepositAction
{
    public static function make(string $entityType = 'customer'): Action
    {
        return Action::make('add_deposit')
            ->label('Add Deposit')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modal()
            ->modalHeading(function ($record) use ($entityType) {
                $type = $entityType === 'customer' ? 'Customer' : 'Supplier';
                return "Add Deposit for {$type}: ({$record->code}) {$record->name}";
            })
            ->form([
                Grid::make(2)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Deposit Amount')
                            ->prefix('Rp')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $usedAmount = $get('used_amount') ?? 0;
                                $set('remaining_amount', $state - $usedAmount);
                            }),
                            
                        TextInput::make('used_amount')
                            ->label('Already Used Amount')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $totalAmount = $get('amount') ?? 0;
                                $set('remaining_amount', $totalAmount - $state);
                            }),
                    ]),
                    
                TextInput::make('remaining_amount')
                    ->label('Remaining Amount')
                    ->prefix('Rp')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),
                    
                Select::make('coa_id')
                    ->label('Chart Of Account')
                    ->required()
                    ->searchable(['code', 'name'])
                    ->relationship('coa', 'code')
                    ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                        return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                    }),
                    
                Textarea::make('note')
                    ->label('Notes')
                    ->placeholder('Enter deposit notes...')
                    ->rows(3),
            ])
            ->action(function (array $data, $record) use ($entityType) {
                // Determine model type
                $modelType = $entityType === 'customer' ? 'App\Models\Customer' : 'App\Models\Supplier';
                
                // Create deposit
                $deposit = Deposit::create([
                    'from_model_type' => $modelType,
                    'from_model_id' => $record->id,
                    'amount' => $data['amount'],
                    'used_amount' => $data['used_amount'] ?? 0,
                    'remaining_amount' => $data['amount'] - ($data['used_amount'] ?? 0),
                    'coa_id' => $data['coa_id'],
                    'note' => $data['note'],
                    'status' => 'active',
                    'created_by' => Auth::id(),
                ]);

                // Create deposit log
                $deposit->depositLogRef()->create([
                    'deposit_id' => $deposit->id,
                    'type' => 'create',
                    'amount' => $deposit->amount,
                    'note' => 'Deposit created from ' . ($entityType === 'customer' ? 'Customer' : 'Supplier') . ' management: ' . ($data['note'] ?? 'No additional notes'),
                    'created_by' => Auth::id()
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Deposit Created')
                    ->success()
                    ->body("Deposit of Rp " . number_format($data['amount']) . " created successfully")
                    ->send();
            });
    }
}
