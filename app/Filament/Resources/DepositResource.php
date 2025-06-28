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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Deposit')
                    ->schema([
                        Section::make('From Supplier / Customer')
                            ->columnSpanFull()
                            ->columns(2)
                            ->description('Referensi untuk membuat Deposit, tidak boleh di abaikan')
                            ->schema([
                                Radio::make('from_model_type')
                                    ->required()
                                    ->reactive()
                                    ->inlineLabel()
                                    ->options([
                                        'App\Models\Supplier' => 'Supplier',
                                        'App\Models\Customer' => 'Customer'
                                    ])->label('From'),
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
                                    })->preload()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "({$record->code}) {$record->name}";
                                    })
                                    ->label(function ($get) {
                                        if ($get('from_model_type') == 'App\Models\Supplier') {
                                            return 'From Supplier';
                                        } elseif ($get('from_model_type') == 'App\Models\Customer') {
                                            return 'From Customer';
                                        }
                                        return "From";
                                    })
                                    ->validationMessages([
                                        'required' => 'Supplier atau Customer belum dipilih'
                                    ])
                                    ->required(),
                            ]),
                        TextInput::make('amount')
                            ->label('Total')
                            ->prefix('Rp')
                            ->required()
                            ->default(0)
                            ->numeric(),
                        TextInput::make('used_amount')
                            ->required()
                            ->label('Total Digunakan')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0),
                        TextInput::make('remaining_amount')
                            ->required()
                            ->label('Total Sisa')
                            ->prefix('Rp')
                            ->default(0)
                            ->numeric(),
                        Select::make('coa_id')
                            ->label('Chart Of Account')
                            ->required()
                            ->preload()
                            ->searchable(['code', 'name'])
                            ->relationship('coa', 'code')
                            ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                            }),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->string(),
                        Checkbox::make('status')
                            ->label('Status (Aktif / Tidak Aktif)')
                            ->default(true),
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
                    }),
                TextColumn::make('from_model_type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        if ($state == 'App\Models\Supplier') {
                            return 'Supplier';
                        } elseif ($state == 'App\Models\Customer') {
                            return 'Customer';
                        }

                        return '-';
                    }),
                TextColumn::make('amount')
                    ->label('Total')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('used_amount')
                    ->label('Total Digunakan')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('remaining_amount')
                    ->label('Total Sisa')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('coa')
                    ->label('Chart Of Account')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('coa', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                IconColumn::make('status')
                    ->label('Status')
                    ->boolean(),
                TextColumn::make('createdBy.name')
                    ->searchable()
                    ->label('Created By'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
