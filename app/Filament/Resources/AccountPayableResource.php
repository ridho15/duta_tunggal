<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\AccountPayableResource\Pages;
use App\Helpers\MoneyHelper;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Support\AccountPayableQuery;
use App\Support\OverdueStatusPresenter;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\AccountPayableResource\RelationManagers;

class AccountPayableResource extends Resource
{
    protected static ?string $model = AccountPayable::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Keuangan Pembelian';

    protected static ?string $navigationLabel = 'Utang Usaha';

    protected static ?string $modelLabel = 'Utang Usaha';

    protected static ?string $pluralModelLabel = 'Utang Usaha';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

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
                                return "({$supplier->code}) {$supplier->perusahaan}";
                            })
                            ->relationship('supplier', 'perusahaan'),
                        TextInput::make('total')
                            ->required()
                            ->indonesianMoney()
                            ->validationMessages([
                                'required' => 'Total tidak boleh kosong',
                                'numeric' => 'Total harus berupa angka'
                            ])
                            ->readonly()
                            ->reactive()
                            ->dehydrateStateUsing(function ($state) {
                                return (float) \App\Helpers\MoneyHelper::safeParse($state);
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $total = (float) \App\Helpers\MoneyHelper::safeParse($state);
                                $paid = (float) \App\Helpers\MoneyHelper::safeParse($get('paid'));
                                $set('remaining', $total - $paid);
                            })
                            ->helperText('Total akan terisi otomatis berdasarkan invoice yang dipilih'),
                        TextInput::make('paid')
                            ->required()
                            ->indonesianMoney()
                            ->validationMessages([
                                'required' => 'Jumlah pembayaran tidak boleh kosong',
                                'numeric' => 'Jumlah pembayaran harus berupa angka'
                            ])
                            ->default(0.00)
                            ->reactive()
                            ->dehydrateStateUsing(fn ($state) => MoneyHelper::safeParse($state))
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $total = (float) \App\Helpers\MoneyHelper::safeParse($get('total'));
                                $paid = (float) \App\Helpers\MoneyHelper::safeParse($state);
                                $set('remaining', $total - $paid);
                            }),
                        TextInput::make('remaining')
                            ->required()
                            ->indonesianMoney()
                            ->validationMessages([
                                'required' => 'Sisa pembayaran tidak boleh kosong',
                                'numeric' => 'Sisa pembayaran harus berupa angka'
                            ])
                            ->reactive()
                            ->dehydrateStateUsing(fn ($state) => MoneyHelper::safeParse($state))
                            ->helperText('Sisa pembayaran akan terisi otomatis berdasarkan total invoice'),
                        Hidden::make('status')
                            ->default(PaymentStatus::UNPAID->value)
                            ->dehydrated(fn (string $context): bool => $context === 'create')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return AccountPayableQuery::withOverdueGrouping(
                    AccountPayableQuery::base()->with(['invoice', 'invoice.fromModel'])
                );
            })
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->overdue_group === 'DELETED INVOICE') {
                            return $state . ' 🗑️ (DELETED)';
                        }
                        return $state;
                    })
                    ->color(function ($record) {
                        return $record->overdue_group === 'DELETED INVOICE' ? 'danger' : 'gray';
                    }),
                    
                TextColumn::make('supplier')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->perusahaan}";
                    })
                    ->label('Supplier')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('supplier', function (Builder $query) use ($search) {
                            $query->where('code', 'like', "%{$search}%")
                                  ->orWhere('perusahaan', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                    
                TextColumn::make('invoice.invoice_date')
                    ->label('Invoice Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->overdue_group === 'DELETED INVOICE') {
                            return $state . ' 🗑️';
                        }
                        return $state;
                    }),
                    
                TextColumn::make('invoice.due_date')
                    ->label('Due Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn ($record) => self::overdueStatusPresenter()->dueDateColor($record))
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->overdue_group === 'DELETED INVOICE') {
                            return $state . ' 🗑️ (DELETED)';
                        }
                        return $state;
                    }),
                    
                TextColumn::make('total')
                    ->label('Total Amount')
                    ->sortable()
                    ->searchable()
                    ->rupiah()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->rupiah()
                            ->label('Total Outstanding')
                    ]),
                    
                TextColumn::make('paid')
                    ->label('Paid Amount')
                    ->sortable()
                    ->rupiah()
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->rupiah()
                            ->label('Total Paid')
                    ]),
                    
                TextColumn::make('remaining')
                    ->label('Outstanding')
                    ->sortable()
                    ->rupiah()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->rupiah()
                            ->label('Total Outstanding')
                    ]),
                    
                TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->getStateUsing(fn ($record) => self::overdueStatusPresenter()->daysOverdue($record))
                    ->color(fn ($state) => self::overdueStatusPresenter()->daysOverdueColor($state))
                    ->badge()
                    ->sortable(),
                    
                TextColumn::make('status')
                    ->label('Status')
                    ->color(function ($state) {
                        return match ($state) {
                            PaymentStatus::UNPAID->value => 'warning',
                            PaymentStatus::PAID->value => 'success',
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
                    
                TextColumn::make('invoice.fromModel.po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->default('-')
                    ->toggleable(),
                    
                TextColumn::make('invoice.fromModel.createdBy.name')
                    ->label('PO Created By')
                    ->getStateUsing(function ($record) {
                        if ($record->overdue_group === 'DELETED INVOICE') {
                            return 'DELETED INVOICE';
                        }
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
                Tables\Grouping\Group::make('supplier.perusahaan')
                    ->label('Supplier')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return "🏢 ({$record->supplier->code}) {$record->supplier->perusahaan}";
                    })
                    ->collapsible(),
                    
                Tables\Grouping\Group::make('status')
                    ->label('Payment Status')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function ($record) {
                        return $record->status === PaymentStatus::PAID->value ? '✅ PAID' : '⏳ OUTSTANDING';
                    })
                    ->collapsible(),
                    
                Tables\Grouping\Group::make('overdue_group')
                    ->label('Overdue Status')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn ($record) => self::overdueStatusPresenter()->overdueGroupLabel($record))
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'perusahaan')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "({$record->code}) {$record->name}";
                    }),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('Payment Status')
                    ->options([
                        PaymentStatus::UNPAID->value => 'Outstanding',
                        PaymentStatus::PAID->value => 'Paid',
                    ])
                    ->multiple(),
                    
                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_from')
                                    ->label('Amount From')
                                    ->indonesianMoney()
                                    ->dehydrateStateUsing(fn ($state) => MoneyHelper::safeParse($state)),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('Amount To')
                                    ->indonesianMoney()
                                    ->dehydrateStateUsing(fn ($state) => MoneyHelper::safeParse($state)),
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
                            $indicators['amount_from'] = 'Amount from: ' . MoneyHelper::rupiah($data['amount_from']);
                        }
                        if ($data['amount_to'] ?? null) {
                            $indicators['amount_to'] = 'Amount to: ' . MoneyHelper::rupiah($data['amount_to']);
                        }
                        return $indicators;
                    }),
                    
                Tables\Filters\Filter::make('outstanding_only')
                    ->label('Outstanding Only')
                    ->query(fn (Builder $query): Builder => $query->where('remaining', '>', 0))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Invoices')
                    ->query(fn (Builder $query): Builder => AccountPayableQuery::applyOverdueFilter($query))
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
                        if (!$data['value']) {
                            return $query;
                        }

                        return AccountPayableQuery::applyOverdueDaysFilter($query, $data['value']);
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
            ->bulkActions([])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Account Payable</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Account Payable adalah catatan hutang perusahaan kepada supplier berdasarkan invoice pembelian yang belum dibayar.</li>' .
                            '<li><strong>Status:</strong> <em>Belum Lunas</em> (outstanding), <em>Lunas</em> (paid). Hanya menampilkan yang belum lunas secara default.</li>' .
                            '<li><strong>Validasi:</strong> Total, Paid, dan Remaining dihitung otomatis. Status pembayaran diperbarui berdasarkan pembayaran.</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah pembayaran), <em>Delete</em> (hapus record).</li>' .
                            '<li><strong>Grouping:</strong> Berdasarkan Supplier, Status Pembayaran, dan Status Overdue (Current, Overdue, dll.).</li>' .
                            '<li><strong>Filters:</strong> Supplier, Status, Amount Range, Outstanding Only, Overdue, Date Range, dll.</li>' .
                            '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan AP dari cabang tersebut jika tidak memiliki akses all.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentHistoryRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('account_payables.status', '!=', PaymentStatus::PAID->value);

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('invoice', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
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

    public static function overdueStatusPresenter(): OverdueStatusPresenter
    {
        return app(OverdueStatusPresenter::class);
    }
}
