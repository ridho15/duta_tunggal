<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\JournalEntry;
use App\Services\JournalEntryAggregationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    // Show Journal Entries in sidebar and expose the Profit & Loss page under it
    protected static ?string $navigationLabel = 'Journal Entry';
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $modelLabel = 'Journal Entry';

    protected static ?string $pluralModelLabel = 'Journal Entries';

    protected static ?string $navigationGroup = 'Finance - Akuntansi';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Journal Entry Details')
                    ->schema([
                        Forms\Components\Select::make('reference_prefix')
                            ->label('Reference Type')
                            ->searchable()
                            ->options([
                                'MANUAL' => 'Manual Journal Entry',
                                'ADJ' => 'Adjustment Entry',
                                'REV' => 'Reversal Entry',
                                'CORR' => 'Correction Entry',
                                'JV' => 'Journal Voucher',
                                'DEP' => 'Deposit Adjustment',
                                'BANK' => 'Bank Adjustment',
                                'CASH' => 'Cash Adjustment',
                            ])
                            ->default('MANUAL')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Generate next number for this prefix
                                $nextNumber = \App\Models\JournalEntry::where('reference', 'like', $state . '-%')
                                    ->selectRaw('CAST(SUBSTRING_INDEX(reference, "-", -1) AS UNSIGNED) as num')
                                    ->orderBy('num', 'desc')
                                    ->value('num') ?? 0;
                                $nextNumber += 1;
                                $set('reference_number', str_pad($nextNumber, 3, '0', STR_PAD_LEFT));
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->default('001')
                            ->maxLength(10)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $prefix = $get('reference_prefix');
                                if ($prefix && $state) {
                                    $set('reference', $prefix . '-' . $state);
                                }
                            })
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('generate_number')
                                    ->label('Generate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (callable $get, callable $set) {
                                        $prefix = $get('reference_prefix');
                                        if ($prefix) {
                                            // Generate next number for this prefix
                                            $nextNumber = \App\Models\JournalEntry::where('reference', 'like', $prefix . '-%')
                                                ->selectRaw('CAST(SUBSTRING_INDEX(reference, "-", -1) AS UNSIGNED) as num')
                                                ->orderBy('num', 'desc')
                                                ->value('num') ?? 0;
                                            $nextNumber += 1;
                                            $formattedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                                            $set('reference_number', $formattedNumber);
                                            $set('reference', $prefix . '-' . $formattedNumber);
                                        }
                                    })
                            )
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('reference')
                            ->label('Generated Reference')
                            ->maxLength(255)
                            ->readonly()
                            ->helperText('Reference akan di-generate otomatis berdasarkan tipe dan nomor yang dipilih. Klik tombol Generate untuk nomor berikutnya.')
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        Forms\Components\Select::make('journal_type')
                            ->label('Journal Type')
                            ->options([
                                'manual' => 'Manual Entry',
                                'sales' => 'Sales',
                                'purchase' => 'Purchase',
                                'payment' => 'Payment',
                                'receipt' => 'Receipt',
                                'transfer' => 'Transfer',
                                'adjustment' => 'Adjustment',
                                'depreciation' => 'Depreciation',
                                'manufacturing' => 'Manufacturing',
                                'inventory' => 'Inventory',
                            ])
                            ->default('manual')
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        // Source selection - optional for all journal entries
                        Forms\Components\Select::make('source_type')
                            ->label('Source Type')
                            ->options([
                                'App\\Models\\PurchaseOrder' => 'Purchase Order',
                                'App\\Models\\SaleOrder' => 'Sales Order',
                                'App\\Models\\ManufacturingOrder' => 'Manufacturing Order',
                                'App\\Models\\DeliveryOrder' => 'Delivery Order',
                                'App\\Models\\MaterialIssue' => 'Material Issue',
                                'App\\Models\\VendorPayment' => 'Vendor Payment',
                                'App\\Models\\CustomerReceipt' => 'Customer Receipt',
                                'App\\Models\\CashBankTransaction' => 'Cash/Bank Transaction',
                                'App\\Models\\CustomerReceiptItem' => 'Customer Receipt Item',
                                'App\\Models\\StockTransfer' => 'Stock Transfer',
                                'App\\Models\\Asset' => 'Asset',
                                'App\\Models\\Deposit' => 'Deposit',
                            ])
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('source_id', null); // Reset source_id when source_type changes
                            })
                            ->columnSpan(1),

                        // Source record selection
                        Forms\Components\Select::make('source_id')
                            ->label('Source Record')
                            ->options(function (callable $get) {
                                $sourceType = $get('source_type');
                                if (!$sourceType) return [];

                                try {
                                    $modelClass = $sourceType;
                                    if (!class_exists($modelClass)) return [];
                                    if (!is_subclass_of($modelClass, \Illuminate\Database\Eloquent\Model::class)) return [];

                                    // Get appropriate display field based on model
                                    $displayField = match($modelClass) {
                                        'App\\Models\\PurchaseOrder' => 'po_number',
                                        'App\\Models\\SaleOrder' => 'so_number',
                                        'App\\Models\\ManufacturingOrder' => 'mo_number',
                                        'App\\Models\\DeliveryOrder' => 'do_number',
                                        'App\\Models\\MaterialIssue' => 'issue_number',
                                        'App\\Models\\VendorPayment' => 'reference',
                                        'App\\Models\\CustomerReceipt' => 'id',
                                        'App\\Models\\CashBankTransaction' => 'reference',
                                        'App\\Models\\CustomerReceiptItem' => 'id',
                                        'App\\Models\\StockTransfer' => 'transfer_number',
                                        'App\\Models\\Asset' => 'asset_code',
                                        'App\\Models\\Deposit' => 'deposit_number',
                                        default => 'id'
                                    };

                                    $query = $modelClass::query();

                                    // Add specific conditions based on model type
                                    switch($modelClass) {
                                        case 'App\\Models\\PurchaseOrder':
                                            $query->where('status', 'approved');
                                            break;
                                        case 'App\\Models\\SaleOrder':
                                            $query->where('status', 'confirmed');
                                            break;
                                        case 'App\\Models\\ManufacturingOrder':
                                            $query->where('status', 'in_progress');
                                            break;
                                        case 'App\\Models\\DeliveryOrder':
                                            $query->where('status', 'approved');
                                            break;
                                        case 'App\\Models\\MaterialIssue':
                                            $query->where('status', 'completed');
                                            break;
                                        case 'App\\Models\\CustomerReceipt':
                                            $query->whereIn('status', ['Paid', 'Partial']);
                                            break;
                                        case 'App\\Models\\CustomerReceiptItem':
                                            $query->whereHas('customerReceipt', function($q) {
                                                $q->whereIn('status', ['Paid', 'Partial']);
                                            });
                                            break;
                                    }

                                    return $query->pluck($displayField, 'id')
                                                 ->mapWithKeys(function ($display, $id) use ($modelClass, $displayField) {
                                                     $model = $modelClass::find($id);
                                                     $prefix = match($modelClass) {
                                                         'App\\Models\\PurchaseOrder' => 'PO',
                                                         'App\\Models\\SaleOrder' => 'SO',
                                                         'App\\Models\\ManufacturingOrder' => 'MO',
                                                         'App\\Models\\DeliveryOrder' => 'DO',
                                                         'App\\Models\\MaterialIssue' => 'MI',
                                                         'App\\Models\\VendorPayment' => 'VP',
                                                         'App\\Models\\CustomerReceipt' => 'CR',
                                                         'App\\Models\\CashBankTransaction' => 'CBT',
                                                         'App\\Models\\CustomerReceiptItem' => 'CRI',
                                                         'App\\Models\\StockTransfer' => 'ST',
                                                         'App\\Models\\Asset' => 'ASSET',
                                                         'App\\Models\\Deposit' => 'DEP',
                                                         default => 'UNK'
                                                     };
                                                     return [$id => $prefix . '-' . $id . ': ' . $display];
                                                 });
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => !empty($get('source_type')))
                            ->columnSpan(1),

                        Forms\Components\Select::make('cabang_id')
                            ->label('Branch')
                            ->relationship('cabang', 'nama')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->kode} - {$record->nama}")
                            ->searchable(['kode', 'nama'])
                            ->preload(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->required(),

                        // Journal Entries Repeater - must have at least 2 entries and balance
                        Forms\Components\Repeater::make('journal_entries')
                            ->label('Journal Entries')
                            ->schema([
                                Forms\Components\Select::make('coa_id')
                                    ->label('Chart of Account')
                                    ->relationship('coa', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('debit')
                                    ->label('Debit')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->indonesianMoney()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set, $context) {
                                        if ($context === 'create' && $state > 0) {
                                            $set('credit', 0);
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('credit')
                                    ->label('Credit')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->indonesianMoney()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set, $context) {
                                        if ($context === 'create' && $state > 0) {
                                            $set('debit', 0);
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('description')
                                    ->label('Line Description')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ])
                            ->columns(6)
                            ->defaultItems(2)
                            ->minItems(2)
                            ->addActionLabel('Add Journal Line')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['coa_id'] ? \App\Models\ChartOfAccount::find($state['coa_id'])?->name : 'New Line')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if (!is_array($value) || count($value) < 2) {
                                        $fail('Minimal 2 journal entries diperlukan.');
                                        return;
                                    }

                                    $totalDebit = 0;
                                    $totalCredit = 0;

                                    foreach ($value as $entry) {
                                        $totalDebit += (float) ($entry['debit'] ?? 0);
                                        $totalCredit += (float) ($entry['credit'] ?? 0);
                                    }

                                    if (abs($totalDebit - $totalCredit) > 0.01) {
                                        $fail("Journal entries tidak balance. Total Debit: Rp" . number_format($totalDebit, 2) . ", Total Credit: Rp" . number_format($totalCredit, 2));
                                    }
                                },
                            ])
                            ->columnSpanFull(),

                        // Balance Summary
                        Forms\Components\Placeholder::make('balance_summary')
                            ->label('')
                            ->content(function (callable $get) {
                                $entries = $get('journal_entries') ?? [];
                                $totalDebit = 0;
                                $totalCredit = 0;

                                foreach ($entries as $entry) {
                                    $totalDebit += (float) ($entry['debit'] ?? 0);
                                    $totalCredit += (float) ($entry['credit'] ?? 0);
                                }

                                $balance = $totalDebit - $totalCredit;
                                $status = abs($balance) < 0.01 ? '✅ Balance' : '❌ Not Balance';
                                $color = abs($balance) < 0.01 ? 'text-green-600' : 'text-red-600';

                                return new \Illuminate\Support\HtmlString(
                                    "<div class='{$color} font-semibold'>
                                        Total Debit: Rp" . number_format($totalDebit, 2) . " | 
                                        Total Credit: Rp" . number_format($totalCredit, 2) . " | 
                                        Difference: Rp" . number_format($balance, 2) . " | 
                                        Status: {$status}
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),

                        // Hidden field for balance validation
                        Forms\Components\Hidden::make('balance_validation')
                            ->rules([
                                function ($get) {
                                    return function ($attribute, $value, $fail) use ($get) {
                                        $entries = $get('journal_entries') ?? [];
                                        
                                        if (!is_array($entries) || count($entries) < 2) {
                                            $fail('Minimal 2 journal entries diperlukan.');
                                            return;
                                        }

                                        $totalDebit = 0;
                                        $totalCredit = 0;

                                        foreach ($entries as $entry) {
                                            $totalDebit += (float) ($entry['debit'] ?? 0);
                                            $totalCredit += (float) ($entry['credit'] ?? 0);
                                        }

                                        if (abs($totalDebit - $totalCredit) > 0.01) {
                                            $fail("Journal entries tidak balance. Total Debit: Rp" . number_format($totalDebit, 2) . ", Total Credit: Rp" . number_format($totalCredit, 2) . ". Selisih: Rp" . number_format(abs($totalDebit - $totalCredit), 2));
                                        }
                                    };
                                },
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coa.code')
                    ->label('COA Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('coa.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->money('IDR')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->money('IDR')
                    ->sortable()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'App\\Models\\PurchaseOrder' => 'success',
                        'App\\Models\\SaleOrder' => 'info',
                        'App\\Models\\ManufacturingOrder' => 'warning',
                        'App\\Models\\DeliveryOrder' => 'primary',
                        'App\\Models\\MaterialIssue' => 'purple',
                        'App\\Models\\VendorPayment' => 'danger',
                        'App\\Models\\CustomerReceipt' => 'success',
                        'App\\Models\\CashBankTransaction' => 'gray',
                        'App\\Models\\CustomerReceiptItem' => 'secondary',
                        'App\\Models\\StockTransfer' => 'secondary',
                        'App\\Models\\Asset' => 'warning',
                        'App\\Models\\Deposit' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'App\\Models\\PurchaseOrder' => 'Purchase Order',
                            'App\\Models\\SaleOrder' => 'Sales Order',
                            'App\\Models\\ManufacturingOrder' => 'Manufacturing Order',
                            'App\\Models\\DeliveryOrder' => 'Delivery Order',
                            'App\\Models\\MaterialIssue' => 'Material Issue',
                            'App\\Models\\VendorPayment' => 'Vendor Payment',
                            'App\\Models\\CustomerReceipt' => 'Customer Receipt',
                            'App\\Models\\CashBankTransaction' => 'Cash/Bank Transaction',
                            'App\\Models\\CustomerReceiptItem' => 'Customer Receipt Item',
                            'App\\Models\\StockTransfer' => 'Stock Transfer',
                            'App\\Models\\Asset' => 'Asset',
                            'App\\Models\\Deposit' => 'Deposit',
                            null => '-',
                            default => $state
                        };
                    })
                    ->placeholder('-')
                    ->wrap(),

                Tables\Columns\TextColumn::make('source_reference')
                    ->label('Source Reference')
                    ->getStateUsing(function ($record) {
                        if (!$record->source_type || !$record->source_id) return '-';

                        try {
                            $model = $record->source;
                            if (!$model) return '-';

                            $displayField = match($record->source_type) {
                                'App\\Models\\PurchaseOrder' => 'po_number',
                                'App\\Models\\SaleOrder' => 'so_number',
                                'App\\Models\\ManufacturingOrder' => 'mo_number',
                                'App\\Models\\DeliveryOrder' => 'do_number',
                                'App\\Models\\MaterialIssue' => 'issue_number',
                                'App\\Models\\VendorPayment' => 'reference',
                                'App\\Models\\CustomerReceipt' => 'id',
                                'App\\Models\\CashBankTransaction' => 'reference',
                                'App\\Models\\CustomerReceiptItem' => 'id',
                                'App\\Models\\StockTransfer' => 'transfer_number',
                                'App\\Models\\Asset' => 'asset_code',
                                'App\\Models\\Deposit' => 'deposit_number',
                                default => 'id'
                            };

                            return $model->$displayField ?? $record->source_id;
                        } catch (\Exception $e) {
                            return $record->source_id;
                        }
                    })
                    ->placeholder('-')
                    ->searchable(query: function ($query, $data) {
                        // This is a complex search, might need custom implementation
                        return $query;
                    }),

                Tables\Columns\TextColumn::make('cabang.nama')
                    ->label('Cabang')
                    ->formatStateUsing(fn ($state, $record) => $record->cabang ? ($record->cabang->kode . ' - ' . $record->cabang->nama) : '-')
                    ->searchable(['cabang.kode', 'cabang.nama']),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('journal_type')
                    ->label('Journal Type')
                    ->searchable()
                    ->options([
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                        'depreciation' => 'Depreciation',
                        'manual' => 'Manual',
                        'transfer' => 'Transfer',
                        'payment' => 'Payment',
                        'receipt' => 'Receipt',
                        'manufacturing' => 'Manufacturing',
                        'manufacturing_issue' => 'Manufacturing Issue',
                        'manufacturing_return' => 'Manufacturing Return',
                        'manufacturing_completion' => 'Manufacturing Completion',
                        'manufacturing_allocation' => 'Manufacturing Allocation',
                        'inventory' => 'Inventory',
                        'adjustment' => 'Adjustment',
                    ]),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Tables\Filters\SelectFilter::make('cabang_id')
                    ->label('Branch')
                    ->searchable()
                    ->relationship('cabang', 'nama'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->groups([
                Tables\Grouping\Group::make('reference')
                    ->label('Reference')
                    ->collapsible(),
                Tables\Grouping\Group::make('date')
                    ->label('Date')
                    ->date()
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static function mutateFormDataBeforeFill(array $data): array
    {
        // Parse existing reference to populate form fields
        if (!empty($data['reference'])) {
            if (preg_match('/^([A-Z]+)-(.+)$/', $data['reference'], $matches)) {
                $data['reference_prefix'] = $matches[1];
                $data['reference_number'] = $matches[2];
            } else {
                $data['reference_prefix'] = 'MANUAL';
                $data['reference_number'] = '001';
            }
        } else {
            $data['reference_prefix'] = 'MANUAL';
            $data['reference_number'] = '001';
        }

        // For non-manual entries, set source_type and source_id
        if (!empty($data['source_type']) && !empty($data['source_id'])) {
            // Keep source_type and source_id as they are
        }

        return $data;
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate reference if not provided
        if (empty($data['reference']) && !empty($data['reference_prefix'])) {
            $prefix = $data['reference_prefix'];
            $number = $data['reference_number'] ?? '001';
            $data['reference'] = $prefix . '-' . $number;
        }

        // Remove temporary fields that are not in the model
        unset($data['reference_prefix'], $data['reference_number'], $data['balance_validation']);

        return $data;
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        // Generate reference if not provided
        if (empty($data['reference']) && !empty($data['reference_prefix'])) {
            $prefix = $data['reference_prefix'];
            $number = $data['reference_number'] ?? '001';
            $data['reference'] = $prefix . '-' . $number;
        }

        // Remove temporary fields that are not in the model
        unset($data['reference_prefix'], $data['reference_number'], $data['balance_validation']);

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
            'grouped' => Pages\GroupedJournalEntries::route('/grouped'),
        ];
    }
}
