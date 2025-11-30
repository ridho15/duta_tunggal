<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepositResource\Pages;
use App\Filament\Resources\DepositResource\Pages\ViewDeposit;
use App\Filament\Resources\DepositResource\RelationManagers\DepositLogRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Schema;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Supplier;
use App\Models\JournalEntry;
use App\Services\DepositNumberGenerator;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class DepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-pound';

    protected static ?string $navigationGroup = 'Finance - Pembayaran';

    protected static ?int $navigationSort = 6;

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
                                    })
                                    ->validationMessages([
                                        'required' => 'Tipe entitas (Supplier/Customer) harus dipilih.'
                                    ]),

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
                                    ->preload()
                                    ->validationMessages([
                                        'required' => 'Supplier/Customer harus dipilih.'
                                    ]),
                            ]),
                    ]),

                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('deposit_number')
                            ->label('Nomor Deposit')
                            ->maxLength(255)
                            ->unique(table: 'deposits', column: 'deposit_number', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Nomor deposit sudah digunakan. Silakan generate nomor baru.',
                            ])
                            ->default(function () {
                                return app(DepositNumberGenerator::class)->generate();
                            })
                            ->suffixAction(
                                FormAction::make('generate_deposit_number')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Set $set) {
                                        $set('deposit_number', app(DepositNumberGenerator::class)->generate());
                                    })
                            ),

                        TextInput::make('amount')
                            ->label('Total')
                            ->indonesianMoney()
                            ->required()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $usedAmount = $get('used_amount') ?? 0;
                                $set('remaining_amount', $state - $usedAmount);
                            })
                            ->validationMessages([
                                'required' => 'Jumlah deposit harus diisi.',
                                'numeric' => 'Jumlah deposit harus berupa angka.',
                                'min' => 'Jumlah deposit minimal :min.'
                            ]),
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
                            ->searchable()
                            ->placeholder('Pilih salah satu opsi')
                            ->getSearchResultsUsing(function (string $search, $get): array {
                                $query = ChartOfAccount::query();
                                // If the chart_of_accounts table has an account_type column,
                                // prefer filtering by account_type based on the selected entity.
                                if (Schema::hasColumn('chart_of_accounts', 'account_type')) {
                                    if ($get('from_model_type') === 'App\\Models\\Customer') {
                                        $query->where('account_type', 'Liability');
                                    } elseif ($get('from_model_type') === 'App\\Models\\Supplier') {
                                        $query->where('account_type', 'Asset');
                                    }
                                }
                                return $query->where(function ($q) use ($search) {
                                    $q->where('code', 'LIKE', "%{$search}%")
                                        ->orWhere('name', 'LIKE', "%{$search}%");
                                })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    })
                                    ->toArray();
                            })
                            ->options(function ($get) {
                                $query = ChartOfAccount::query();
                                // If the chart_of_accounts table has an account_type column,
                                // prefer filtering by account_type based on the selected entity.
                                if (Schema::hasColumn('chart_of_accounts', 'account_type')) {
                                    if ($get('from_model_type') === 'App\\Models\\Customer') {
                                        $query->where('account_type', 'Liability');
                                    } elseif ($get('from_model_type') === 'App\\Models\\Supplier') {
                                        $query->where('account_type', 'Asset');
                                    }
                                }
                                return $query->get()->mapWithKeys(function ($coa) {
                                    return [$coa->id => "({$coa->code}) {$coa->name}"];
                                });
                            })
                            ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                            })
                            ->validationMessages([
                                'required' => 'Chart of Account untuk deposit harus dipilih.'
                            ]),

                        Select::make('payment_coa_id')
                            ->label('Payment COA (Kas/Bank)')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->placeholder('Pilih COA Kas/Bank')
                            ->getSearchResultsUsing(function (string $search): array {
                                return ChartOfAccount::where('type', 'asset')
                                    ->where('code', 'LIKE', '11%')
                                    ->where(function ($q) use ($search) {
                                        $q->where('code', 'LIKE', "%{$search}%")
                                            ->orWhere('name', 'LIKE', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    })
                                    ->toArray();
                            })
                            ->options(function () {
                                return ChartOfAccount::where('type', 'asset')
                                    ->where('code', 'LIKE', '11%')
                                    ->get()
                                    ->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    });
                            })
                            ->getOptionLabelFromRecordUsing(function (ChartOfAccount $chartOfAccount) {
                                return "({$chartOfAccount->code}) {$chartOfAccount->name}";
                            })
                            ->validationMessages([
                                'required' => 'Chart of Account untuk pembayaran (Kas/Bank) harus dipilih.'
                            ]),
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
            ->modifyQueryUsing(fn ($query) => $query->with(['coa', 'fromModel', 'paymentCoa']))
            ->columns([
                TextColumn::make('deposit_number')
                    ->label('Nomor Deposit')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('fromModel')
                    ->label('Nama')
                    ->formatStateUsing(function ($state) {
                        return $state ? "({$state->code}) {$state->name}" : '-';
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
                    ->color(fn(string $state): string => match ($state) {
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
                    ->money('IDR')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total All Deposits')
                    ]),

                TextColumn::make('used_amount')
                    ->label('Used Amount')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Used')
                    ]),

                TextColumn::make('remaining_amount')
                    ->label('Hutang Titipan Konsumen')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Hutang Titipan Konsumen')
                    ]),

                TextColumn::make('coa')
                    ->label('Chart Of Account')
                    ->formatStateUsing(function ($state) {
                        return $state ? "({$state->code}) {$state->name}" : '-';
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

                TextColumn::make('paymentCoa')
                    ->label('Coa Payment')
                    ->formatStateUsing(function ($state) {
                        return $state ? "({$state->code}) {$state->name}" : '-';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('paymentCoa', function ($query) use ($search) {
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
            // ->headerActions([
            //     Action::make('deposit_adjustments')
            //         ->label('Deposit Adjustments')
            //         ->icon('heroicon-o-adjustments-horizontal')
            //         ->url(fn () => url('/admin/deposit-adjustments'))
            // ])
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
                    ->query(fn(Builder $query): Builder => $query->where('remaining_amount', '>', 0)),

                Tables\Filters\Filter::make('empty_balance')
                    ->label('Empty Balance')
                    ->query(fn(Builder $query): Builder => $query->where('remaining_amount', '<=', 0)),

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
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
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
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    // EditAction::make() // Disabled - deposits should not be editable after creation for accounting integrity
                    //     ->color('success'),
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
                                            ->indonesianMoney()
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->rules([
                                                'required',
                                                'numeric',
                                                'min:1'
                                            ])
                                            ->validationMessages([
                                                'required' => 'Total penambahan saldo tidak boleh kosong',
                                                'numeric' => 'Total penambahan saldo harus berupa angka',
                                                'min' => 'Total penambahan saldo minimal :min'
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

                            // Create journal entries for deposit balance addition
                            static::createDepositAdditionJournalEntries($record, $data['amount'], $data['note']);

                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Saldo berhasil di tambahkan");
                        }),
                    Action::make('kurangiSaldo')
                        ->label('Kurangi Saldo')
                        ->icon('heroicon-o-minus-circle')
                        ->color('danger')
                        ->modal()
                        ->modalSubmitActionLabel("Kurangi Saldo")
                        ->form(function ($record) {
                            return [
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->label('Total')
                                            ->indonesianMoney()
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->rules([
                                                'required',
                                                'numeric',
                                                'min:1'
                                            ])
                                            ->validationMessages([
                                                'required' => 'Total pengurangan saldo tidak boleh kosong',
                                                'numeric' => 'Total pengurangan saldo harus berupa angka',
                                                'min' => 'Total pengurangan saldo minimal :min'
                                            ]),
                                        Textarea::make('note')
                                            ->label('Catatan')
                                            ->string()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Catatan wajib diisi untuk alasan pengurangan'
                                            ]),
                                    ])
                            ];
                        })
                        ->action(function (array $data, $record) {
                            // Validate that reduction amount doesn't exceed remaining balance
                            if ($data['amount'] > $record->remaining_amount) {
                                HelperController::sendNotification(
                                    isSuccess: false,
                                    title: 'Error',
                                    message: 'Jumlah pengurangan tidak boleh melebihi sisa saldo deposit (Rp ' . number_format($record->remaining_amount, 0, ',', '.') . ')'
                                );
                                return;
                            }

                            $record->amount -= $data['amount'];
                            $record->remaining_amount -= $data['amount'];
                            $record->save();

                            $record->depositLogRef()->create([
                                'deposit_id' => $record->id,
                                'type' => 'return',
                                'amount' => $data['amount'],
                                'note' => $data['note'],
                                'created_by' => Auth::user()->id
                            ]);

                            // Create journal entries for deposit balance reduction
                            static::createDepositReductionJournalEntries($record, $data['amount'], $data['note']);

                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Saldo berhasil di kurangi");
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

    public static function createDepositAdditionJournalEntries($record, $amount, $note = null): void
    {
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($record);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($record);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($record);

        $description = 'Penambahan saldo deposit - ' . $record->fromModel->name;
        if ($note) {
            $description .= ' (' . $note . ')';
        }

        if ($record->from_model_type === 'App\Models\Supplier') {
            // For supplier deposit addition:
            // Dr: Uang Muka Pembelian (1150.01/1150.02)
            // Cr: Kas/Bank (need to get from payment_coa_id or find default)

            // DEBIT: Uang Muka Pembelian
            $record->journalEntry()->create([
                'coa_id' => $record->coa_id, // Uang Muka Pembelian
                'date' => now(),
                'reference' => 'DEP-ADD-' . $record->id . '-' . now()->format('YmdHis'),
                'description' => $description,
                'debit' => $amount,
                'journal_type' => 'deposit',
                'source_type' => \App\Models\Deposit::class,
                'source_id' => $record->id,
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
            ]);

            // Get bank/cash COA from the deposit's payment_coa_id or find default
            $bankCoaId = $record->payment_coa_id ?? null;
            if (!$bankCoaId) {
                // Try to find default bank/cash COA
                $bankCoaId = ChartOfAccount::where('code', 'LIKE', '111%')->first()?->id;
            }

            if ($bankCoaId) {
                // CREDIT: Kas/Bank
                JournalEntry::create([
                    'coa_id' => $bankCoaId,
                    'date' => now(),
                    'reference' => 'DEP-ADD-' . $record->id . '-' . now()->format('YmdHis'),
                    'description' => 'Pembayaran penambahan deposit ke supplier - ' . $record->fromModel->name,
                    'credit' => $amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }
        } elseif ($record->from_model_type === 'App\Models\Customer') {
            // For customer deposit addition:
            // Dr: Kas/Bank (need to get from payment_coa_id or find default)
            // Cr: Hutang Titipan Konsumen (2160.04)

            // Get bank/cash COA from the deposit's payment_coa_id or find default
            $bankCoaId = $record->payment_coa_id ?? null;
            if (!$bankCoaId) {
                // Try to find default bank/cash COA
                $bankCoaId = ChartOfAccount::where('code', 'LIKE', '111%')->first()?->id;
            }

            if ($bankCoaId) {
                // DEBIT: Kas/Bank
                $record->journalEntry()->create([
                    'coa_id' => $bankCoaId,
                    'date' => now(),
                    'reference' => 'DEP-ADD-' . $record->id . '-' . now()->format('YmdHis'),
                    'description' => $description,
                    'debit' => $amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }

            // CREDIT: Hutang Titipan Konsumen
            $liabilityCoaId = ChartOfAccount::where('code', '2160.04')->first()?->id;
            if ($liabilityCoaId) {
                JournalEntry::create([
                    'coa_id' => $liabilityCoaId,
                    'date' => now(),
                    'reference' => 'DEP-ADD-' . $record->id . '-' . now()->format('YmdHis'),
                    'description' => $description,
                    'credit' => $amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }
        }
    }

    public static function createDepositReductionJournalEntries($record, $amount, $note = null): void
    {
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($record);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($record);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($record);

        $description = 'Pengurangan saldo deposit - ' . $record->fromModel->name;
        if ($note) {
            $description .= ' (' . $note . ')';
        }

        if ($record->from_model_type === 'App\Models\Supplier') {
            // For supplier deposit reduction (reverse of addition):
            // Dr: Kas/Bank (coa_id from deposit payment_coa_id)
            // Cr: Uang Muka Pembelian (1150.01/1150.02)

            // DEBIT: Kas/Bank
            $bankCoaId = $record->payment_coa_id ?? null;
            if (!$bankCoaId) {
                // Try to find default bank/cash COA
                $bankCoaId = ChartOfAccount::where('code', 'LIKE', '111%')->first()?->id;
            }

            if ($bankCoaId) {
                $record->journalEntry()->create([
                    'coa_id' => $bankCoaId,
                    'date' => now(),
                    'reference' => 'DEP-REDUCE-' . $record->id . '-' . now()->format('YmdHis'),
                    'description' => $description,
                    'debit' => $amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }

            // CREDIT: Uang Muka Pembelian
            JournalEntry::create([
                'coa_id' => $record->coa_id, // Uang Muka Pembelian
                'date' => now(),
                'reference' => 'DEP-REDUCE-' . $record->id . '-' . now()->format('YmdHis'),
                'description' => 'Pengembalian pengurangan deposit ke supplier - ' . $record->fromModel->name,
                'credit' => $amount,
                'journal_type' => 'deposit',
                'source_type' => \App\Models\Deposit::class,
                'source_id' => $record->id,
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
            ]);
        } elseif ($record->from_model_type === 'App\Models\Customer') {
            // For customer deposit reduction (reverse of addition):
            // Dr: Hutang Titipan Konsumen (2160.04)
            // Cr: Kas/Bank (coa_id from deposit payment_coa_id)

            // DEBIT: Hutang Titipan Konsumen
            $liabilityCoaId = ChartOfAccount::where('code', '2160.04')->first()?->id;
            if ($liabilityCoaId) {
                $record->journalEntry()->create([
                    'coa_id' => $liabilityCoaId,
                    'date' => now(),
                    'reference' => 'DEP-REDUCE-' . $record->id . '-' . now()->format('YmdHis'),
                    'description' => $description,
                    'debit' => $amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }

            // CREDIT: Kas/Bank
            $bankCoaId = $record->payment_coa_id ?? null;
            if (!$bankCoaId) {
                // Try to find default bank/cash COA
                $bankCoaId = ChartOfAccount::where('code', 'LIKE', '111%')->first()?->id;
            }

            if ($bankCoaId) {
                JournalEntry::create([
                    'coa_id' => $bankCoaId,
                    'date' => now(),
                    'reference' => 'DEP-REDUCE-' . $record->id . '-' . now()->format('YmdHis'),
                    'description' => 'Pengembalian pengurangan deposit dari customer - ' . $record->fromModel->name,
                    'credit' => $amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeposits::route('/'),
            'create' => Pages\CreateDeposit::route('/create'),
            'view' => Pages\ViewDeposit::route('/{record}'),
            'edit' => Pages\EditDeposit::route('/{record}/edit'),
        ];
    }
}
