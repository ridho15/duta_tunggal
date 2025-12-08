<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\JournalEntry;
use App\Services\JournalEntryAggregationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Tables\Grouping;
use Filament\Tables\Filters\TextFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Tables\Actions\ActionGroup;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['source', 'coa']);
    }

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
                            ->validationMessages([
                                'required' => 'Tipe referensi harus dipilih.',
                            ])
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
                            ->validationMessages([
                                'required' => 'Nomor referensi harus diisi.',
                                'max' => 'Nomor referensi maksimal 10 karakter.',
                            ])
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
                            ->validationMessages([
                                'required' => 'Tanggal journal entry harus diisi.',
                            ])
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
                            ->validationMessages([
                                'required' => 'Tipe journal harus dipilih.',
                            ])
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
                                'App\\Models\\CashBankTransfer' => 'Cash/Bank Transfer',
                                'App\\Models\\CustomerReceiptItem' => 'Customer Receipt Item',
                                'App\\Models\\StockTransfer' => 'Stock Transfer',
                                'App\\Models\\Asset' => 'Asset',
                                'App\\Models\\Deposit' => 'Deposit',
                                'App\\Models\\OtherSale' => 'Other Sale',
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
                                        'App\\Models\\Asset' => 'code',
                                        'App\\Models\\Deposit' => 'deposit_number',
                                        'App\\Models\\OtherSale' => 'reference_number',
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
                                        case 'App\\Models\\OtherSale':
                                            $query->where('status', 'posted');
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
                                                         'App\\Models\\OtherSale' => 'OS',
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
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    return \App\Models\Cabang::where('id', $user?->cabang_id)
                                        ->get()
                                        ->mapWithKeys(function ($cabang) {
                                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                        });
                                }
                                
                                return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                            })
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->kode} - {$record->nama}")
                            ->searchable(['kode', 'nama'])
                            ->preload(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->required()
                            ->validationMessages([
                                'required' => 'Deskripsi journal entry harus diisi.',
                                'max' => 'Deskripsi maksimal 500 karakter.',
                            ]),

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
                                    ->validationMessages([
                                        'required' => 'Chart of Account harus dipilih.',
                                    ])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('debit')
                                    ->label('Debit')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->indonesianMoney()
                                    ->validationMessages([
                                        'required' => 'Jumlah debit harus diisi.',
                                        'numeric' => 'Jumlah debit harus berupa angka.',
                                    ])
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
                                    ->validationMessages([
                                        'required' => 'Jumlah credit harus diisi.',
                                        'numeric' => 'Jumlah credit harus berupa angka.',
                                    ])
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
                            ->validationMessages([
                                'minItems' => 'Minimal 2 baris journal entry diperlukan.',
                            ])
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Journal Entry Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('reference')
                                    ->label('Reference')
                                    ->copyable()
                                    ->copyMessage('Reference copied')
                                    ->copyMessageDuration(1500),

                                Infolists\Components\TextEntry::make('date')
                                    ->label('Date')
                                    ->dateTime('d F Y'),

                                Infolists\Components\TextEntry::make('coa.name')
                                    ->label('Chart of Account')
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('coa.code')
                                    ->label('COA Code')
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('journal_type')
                                    ->label('Journal Type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'sales' => 'success',
                                        'purchase' => 'warning',
                                        'depreciation' => 'info',
                                        'manual' => 'gray',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('cabang')
                                    ->label('Branch')
                                    ->formatStateUsing(fn ($state) => $state ? "({$state->kode}) {$state->nama}" : '-')
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('debit')
                                    ->label('Debit')
                                    ->money('IDR')
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('credit')
                                    ->label('Credit')
                                    ->money('IDR')
                                    ->color('danger'),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('No description'),

                        Infolists\Components\Section::make('Source Information')
                            ->schema([
                                Infolists\Components\TextEntry::make('source_type')
                                    ->label('Source Type')
                                    ->formatStateUsing(function ($record) {
                                        if (!$record->source_type) return 'Manual Entry';

                                        // Special handling for Quality Control to differentiate Manufacture vs Purchase
                                        if ($record->source_type === 'App\\Models\\QualityControl' && $record->source) {
                                            if ($record->source->from_model_type === 'App\\Models\\Production') {
                                                return 'QC Manufacture';
                                            } elseif ($record->source->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                                return 'QC Purchase';
                                            }
                                        }

                                        return match ($record->source_type) {
                                            'App\\Models\\PurchaseOrder' => 'Purchase Order',
                                            'App\\Models\\SaleOrder' => 'Sales Order',
                                            'App\\Models\\ManufacturingOrder' => 'Manufacturing Order',
                                            'App\\Models\\DeliveryOrder' => 'Delivery Order',
                                            'App\\Models\\MaterialIssue' => 'Material Issue',
                                            'App\\Models\\VendorPayment' => 'Vendor Payment',
                                            'App\\Models\\CustomerReceipt' => 'Customer Receipt',
                                            'App\\Models\\CashBankTransaction' => 'Cash/Bank Transaction',
                                            'App\\Models\\CashBankTransfer' => 'Cash/Bank Transfer',
                                            'App\\Models\\CustomerReceiptItem' => 'Customer Receipt Item',
                                            'App\\Models\\StockTransfer' => 'Stock Transfer',
                                            'App\\Models\\Asset' => 'Asset',
                                            'App\\Models\\Deposit' => 'Deposit',
                                            'App\\Models\\Production' => 'Production',
                                            'App\\Models\\QualityControl' => 'Quality Control',
                                            'App\\Models\\OtherSale' => 'Other Sale',
                                            'App\\Models\\StockOpname' => 'Stock Opname',
                                            default => $record->source_type,
                                        };
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if (!$record->source_type) return 'primary';

                                        // Special handling for Quality Control to differentiate Manufacture vs Purchase
                                        if ($record->source_type === 'App\\Models\\QualityControl' && $record->source) {
                                            if ($record->source->from_model_type === 'App\\Models\\Production') {
                                                return 'warning'; // Manufacture
                                            } elseif ($record->source->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                                return 'purple'; // Purchase
                                            }
                                        }

                                        // Special handling for StockOpname
                                        if ($record->source_type === 'App\\Models\\StockOpname') {
                                            return 'orange';
                                        }

                                        return 'primary'; // Default color for other types
                                    })
                                    ->placeholder('Manual Entry'),

                                Infolists\Components\TextEntry::make('source_id')
                                    ->label('Source Number')
                                    ->formatStateUsing(function ($record) {
                                        if (!$record->source_type || !$record->source_id) {
                                            return 'Manual Entry';
                                        }

                                        $source = $record->source;
                                        if (!$source) {
                                            return 'Source not found';
                                        }

                                        // Get source number based on type
                                        switch ($record->source_type) {
                                            case 'App\\Models\\PurchaseOrder':
                                                return $source->po_number ?: 'N/A';
                                            case 'App\\Models\\SaleOrder':
                                                return $source->so_number ?: 'N/A';
                                            case 'App\\Models\\ManufacturingOrder':
                                                return $source->mo_number ?: 'N/A';
                                            case 'App\\Models\\DeliveryOrder':
                                                return $source->do_number ?: 'N/A';
                                            case 'App\\Models\\MaterialIssue':
                                                return $source->issue_number ?: 'N/A';
                                            case 'App\\Models\\VendorPayment':
                                                return $source->payment_number ?: 'N/A';
                                            case 'App\\Models\\CustomerReceipt':
                                                return $source->receipt_number ?: 'N/A';
                                            case 'App\\Models\\CashBankTransaction':
                                                return $source->transaction_number ?: 'N/A';
                                            case 'App\\Models\\StockTransfer':
                                                return $source->transfer_number ?: 'N/A';
                                            case 'App\\Models\\Asset':
                                                return $source->asset_number ?: 'N/A';
                                            case 'App\\Models\\Deposit':
                                                return $source->deposit_number ?: 'N/A';
                                            case 'App\\Models\\Production':
                                                return $source->production_number ?: 'N/A';
                                            case 'App\\Models\\QualityControl':
                                                $qcNumber = $source->qc_number ?: 'N/A';
                                                if ($source->from_model_type === 'App\\Models\\Production') {
                                                    return "MFG-{$qcNumber}"; // Manufacture QC
                                                } elseif ($source->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                                    return "PUR-{$qcNumber}"; // Purchase QC
                                                }
                                                return $qcNumber; // Fallback
                                            case 'App\\Models\\OtherSale':
                                                return $source->reference_number ?: 'N/A';
                                            case 'App\\Models\\StockOpname':
                                                return $source->opname_number ?: 'N/A';
                                            default:
                                                return 'N/A';
                                        }
                                    })
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('source_id')
                                    ->label('Source Details')
                                    ->formatStateUsing(function ($record) {
                                        if (!$record->source_type || !$record->source_id) {
                                            return 'Manual Entry';
                                        }

                                        $source = $record->source;
                                        if (!$source) {
                                            return 'Source not found';
                                        }

                                        // Get source information based on type
                                        switch ($record->source_type) {
                                            case 'App\\Models\\PurchaseOrder':
                                                $supplierName = $source->supplier ? $source->supplier->name : 'N/A';
                                                return "PO: {$source->po_number} - {$supplierName}";
                                            case 'App\\Models\\SaleOrder':
                                                $customerName = $source->customer ? $source->customer->name : 'N/A';
                                                return "SO: {$source->so_number} - {$customerName}";
                                            case 'App\\Models\\ManufacturingOrder':
                                                $productName = $source->product ? $source->product->name : 'N/A';
                                                return "MO: {$source->mo_number} - {$productName}";
                                            case 'App\\Models\\DeliveryOrder':
                                                $customerName = $source->customer ? $source->customer->name : 'N/A';
                                                return "DO: {$source->do_number} - {$customerName}";
                                            case 'App\\Models\\MaterialIssue':
                                                $moNumber = $source->manufacturingOrder ? $source->manufacturingOrder->mo_number : 'N/A';
                                                return "MI: {$source->issue_number} - {$moNumber}";
                                            case 'App\\Models\\VendorPayment':
                                                $supplierName = $source->supplier ? $source->supplier->name : 'N/A';
                                                return "VP: {$source->payment_number} - {$supplierName}";
                                            case 'App\\Models\\CustomerReceipt':
                                                $customerName = $source->customer ? $source->customer->name : 'N/A';
                                                return "CR: {$source->receipt_number} - {$customerName}";
                                            case 'App\\Models\\CashBankTransaction':
                                                $description = $source->description ?: 'N/A';
                                                return "CBT: {$source->transaction_number} - {$description}";
                                            case 'App\\Models\\CashBankTransfer':
                                                $description = $source->description ?: 'N/A';
                                                return "CBT: {$source->transfer_number} - {$description}";
                                            case 'App\\Models\\StockTransfer':
                                                $fromWarehouse = $source->from_warehouse ? $source->from_warehouse->name : 'N/A';
                                                $toWarehouse = $source->to_warehouse ? $source->to_warehouse->name : 'N/A';
                                                return "ST: {$source->transfer_number} - {$fromWarehouse} to {$toWarehouse}";
                                            case 'App\\Models\\Asset':
                                                return "Asset: {$source->asset_number} - {$source->name}";
                                            case 'App\\Models\\Deposit':
                                                $customerName = $source->customer ? $source->customer->name : 'N/A';
                                                return "Deposit: {$source->deposit_number} - {$customerName}";
                                            case 'App\\Models\\Production':
                                                $productName = $source->manufacturingOrder && $source->manufacturingOrder->product
                                                    ? $source->manufacturingOrder->product->name : 'N/A';
                                                return "Production: {$source->production_number} - {$productName}";
                                            case 'App\\Models\\QualityControl':
                                                $qcNumber = $source->qc_number ?: 'N/A';
                                                $productName = $source->product ? $source->product->name : 'N/A';
                                                $qcType = '';
                                                if ($source->from_model_type === 'App\\Models\\Production') {
                                                    $qcType = 'Manufacture';
                                                } elseif ($source->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                                    $qcType = 'Purchase';
                                                }
                                                return "QC {$qcType}: {$qcNumber} - {$productName}";
                                            case 'App\\Models\\OtherSale':
                                                $customerName = $source->customer ? $source->customer->name : 'N/A';
                                                return "Other Sale: {$source->reference_number} - {$customerName}";
                                            case 'App\\Models\\StockOpname':
                                                $warehouseName = $source->warehouse ? $source->warehouse->name : 'N/A';
                                                return "Stock Opname: {$source->opname_number} - {$warehouseName}";
                                            default:
                                                return 'Unknown Source';
                                        }
                                    })
                                    ->placeholder('N/A')
                                    ->columnSpanFull(),

                                Infolists\Components\Actions::make([
                                    Infolists\Components\Actions\Action::make('view_source')
                                        ->label('View Source Data')
                                        ->icon('heroicon-o-eye')
                                        ->color('primary')
                                        ->url(function ($record) {
                                            if (!$record->source_type || !$record->source_id) {
                                                return null;
                                            }

                                            // Generate URL based on source type using named routes
                                            try {
                                                return match($record->source_type) {
                                                    'App\\Models\\PurchaseOrder' => route('filament.admin.resources.purchase-orders.view', $record->source_id),
                                                    'App\\Models\\SaleOrder' => route('filament.admin.resources.sale-orders.view', $record->source_id),
                                                    'App\\Models\\ManufacturingOrder' => route('filament.admin.resources.manufacturing-orders.view', $record->source_id),
                                                    'App\\Models\\DeliveryOrder' => route('filament.admin.resources.delivery-orders.view', $record->source_id),
                                                    'App\\Models\\MaterialIssue' => route('filament.admin.resources.material-issues.view', $record->source_id),
                                                    'App\\Models\\CustomerReceipt' => route('filament.admin.resources.customer-receipts.view', $record->source_id),
                                                    'App\\Models\\Asset' => route('filament.admin.resources.assets.view', $record->source_id),
                                                    'App\\Models\\Deposit' => route('filament.admin.resources.deposits.view', $record->source_id),
                                                    'App\\Models\\QualityControl' => self::getQualityControlViewUrl($record->source),
                                                    'App\\Models\\StockTransfer' => route('filament.admin.resources.stock-transfers.view', $record->source_id),
                                                    'App\\Models\\Invoice' => self::getInvoiceViewUrl($record->source),
                                                    // 'App\\Models\\OtherSale' => route('filament.admin.resources.other-sales.view', $record->source_id), // No view route available
                                                    default => null,
                                                };
                                            } catch (\Exception $e) {
                                                // Route not found, return null to hide the action
                                                return null;
                                            }
                                        })
                                        ->openUrlInNewTab()
                                        ->visible(function ($record) {
                                            if (!$record->source_type || !$record->source_id) {
                                                return false;
                                            }

                                            // Check if route exists for this source type
                                            try {
                                                $url = match($record->source_type) {
                                                    'App\\Models\\PurchaseOrder' => route('filament.admin.resources.purchase-orders.view', $record->source_id),
                                                    'App\\Models\\SaleOrder' => route('filament.admin.resources.sale-orders.view', $record->source_id),
                                                    'App\\Models\\ManufacturingOrder' => route('filament.admin.resources.manufacturing-orders.view', $record->source_id),
                                                    'App\\Models\\DeliveryOrder' => route('filament.admin.resources.delivery-orders.view', $record->source_id),
                                                    'App\\Models\\MaterialIssue' => route('filament.admin.resources.material-issues.view', $record->source_id),
                                                    'App\\Models\\CustomerReceipt' => route('filament.admin.resources.customer-receipts.view', $record->source_id),
                                                    'App\\Models\\Asset' => route('filament.admin.resources.assets.view', $record->source_id),
                                                    'App\\Models\\Deposit' => route('filament.admin.resources.deposits.view', $record->source_id),
                                                    'App\\Models\\QualityControl' => self::getQualityControlViewUrl($record->source),
                                                    'App\\Models\\StockTransfer' => route('filament.admin.resources.stock-transfers.view', $record->source_id),
                                                    'App\\Models\\Invoice' => self::getInvoiceViewUrl($record->source),
                                                    default => null,
                                                };
                                                return $url !== null;
                                            } catch (\Exception $e) {
                                                return false;
                                            }
                                        }),
                                ])
                                ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(),
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
                    ->color(function ($record) {
                        if (!$record->source_type) return 'gray';

                        // Special handling for Quality Control to differentiate Manufacture vs Purchase
                        if ($record->source_type === 'App\\Models\\QualityControl' && $record->source) {
                            if ($record->source->from_model_type === 'App\\Models\\Production') {
                                return 'warning'; // Manufacture
                            } elseif ($record->source->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                return 'purple'; // Purchase
                            }
                        }

                        // Special handling for Invoice to differentiate Purchase vs Sales
                        if ($record->source_type === 'App\\Models\\Invoice' && $record->source) {
                            if ($record->source->from_model_type === 'App\\Models\\PurchaseOrder') {
                                return 'success'; // Purchase Invoice
                            } elseif ($record->source->from_model_type === 'App\\Models\\SaleOrder') {
                                return 'info'; // Sales Invoice
                            }
                        }

                        return match ($record->source_type) {
                            'App\\Models\\PurchaseOrder' => 'success',
                            'App\\Models\\SaleOrder' => 'info',
                            'App\\Models\\ManufacturingOrder' => 'warning',
                            'App\\Models\\DeliveryOrder' => 'primary',
                            'App\\Models\\MaterialIssue' => 'purple',
                            'App\\Models\\VendorPayment' => 'danger',
                            'App\\Models\\CustomerReceipt' => 'success',
                            'App\\Models\\CashBankTransaction' => 'gray',
                            'App\\Models\\CashBankTransfer' => 'gray',
                            'App\\Models\\CustomerReceiptItem' => 'secondary',
                            'App\\Models\\StockTransfer' => 'secondary',
                            'App\\Models\\Asset' => 'warning',
                            'App\\Models\\Deposit' => 'primary',
                            'App\\Models\\OtherSale' => 'info',
                            'App\\Models\\StockOpname' => 'orange',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($record) {
                        if (!$record->source_type) return '-';
                        // if($record->id == 13){
                        //     dd($record->source_type, $record->source->from_model_type, $record->id);
                        // }
                        // Special handling for Quality Control to differentiate Manufacture vs Purchase
                        if ($record->source_type === 'App\\Models\\QualityControl' && $record->source) {
                            if ($record->source->from_model_type === 'App\\Models\\Production') {
                                return 'QC Manufacture';
                            } elseif ($record->source->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                return 'QC Purchase';
                            }
                        }

                        // Special handling for Invoice to differentiate Purchase vs Sales
                        if ($record->source_type === 'App\\Models\\Invoice' && $record->source) {
                            if ($record->source->from_model_type === 'App\\Models\\PurchaseOrder') {
                                return 'Purchase Invoice';
                            } elseif ($record->source->from_model_type === 'App\\Models\\SaleOrder') {
                                return 'Sales Invoice';
                            }
                        }

                        return match($record->source_type) {
                            'App\\Models\\PurchaseOrder' => 'Purchase Order',
                            'App\\Models\\SaleOrder' => 'Sales Order',
                            'App\\Models\\ManufacturingOrder' => 'Manufacturing Order',
                            'App\\Models\\DeliveryOrder' => 'Delivery Order',
                            'App\\Models\\MaterialIssue' => 'Material Issue',
                            'App\\Models\\VendorPayment' => 'Vendor Payment',
                            'App\\Models\\CustomerReceipt' => 'Customer Receipt',
                            'App\\Models\\PurchaseReceipt' => 'Purchase Receipt',
                            'App\\Models\\PurchaseReceiptItem' => 'Purchase Receipt Item',
                            'App\\Models\\CashBankTransaction' => 'Cash/Bank Transaction',
                            'App\\Models\\CashBankTransfer' => 'Cash/Bank Transfer',
                            'App\\Models\\CustomerReceiptItem' => 'Customer Receipt Item',
                            'App\\Models\\StockTransfer' => 'Stock Transfer',
                            'App\\Models\\Asset' => 'Asset',
                            'App\\Models\\Deposit' => 'Deposit',
                            'App\\Models\\QualityControl' => 'Quality Control',
                            'App\\Models\\OtherSale' => 'Other Sale',
                            'App\\Models\\StockOpname' => 'Stock Opname',
                            null => '-',
                            default => $record->source_type
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

                            // Special handling for Quality Control to differentiate Manufacture vs Purchase
                            if ($record->source_type === 'App\\Models\\QualityControl') {
                                $qcNumber = $model->qc_number ?: 'N/A';
                                if ($model->from_model_type === 'App\\Models\\Production') {
                                    return "MFG-{$qcNumber}"; // Manufacture QC
                                } elseif ($model->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                                    return "PUR-{$qcNumber}"; // Purchase QC
                                }
                                return $qcNumber; // Fallback
                            }

                            $displayField = match($record->source_type) {
                                'App\\Models\\PurchaseOrder' => 'po_number',
                                'App\\Models\\SaleOrder' => 'so_number',
                                'App\\Models\\ManufacturingOrder' => 'mo_number',
                                'App\\Models\\DeliveryOrder' => 'do_number',
                                'App\\Models\\MaterialIssue' => 'issue_number',
                                'App\\Models\\VendorPayment' => 'reference',
                                'App\\Models\\CustomerReceipt' => 'id',
                                'App\\Models\\PurchaseReceipt' => 'receipt_number',
                                'App\\Models\\PurchaseReceiptItem' => 'id',
                                'App\\Models\\CashBankTransaction' => 'reference',
                                'App\\Models\\CashBankTransfer' => 'reference',
                                'App\\Models\\CustomerReceiptItem' => 'id',
                                'App\\Models\\StockTransfer' => 'transfer_number',
                                'App\\Models\\Asset' => 'code',
                                'App\\Models\\Deposit' => 'deposit_number',
                                'App\\Models\\Invoice' => 'invoice_number',
                                'App\\Models\\OtherSale' => 'reference_number',
                                'App\\Models\\StockOpname' => 'opname_number',
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

                Tables\Columns\TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(fn ($state, $record) => $record->cabang ? ($record->cabang->kode . ' - ' . $record->cabang->nama) : '-')
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),

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
                    ->options(function () {
                        $user = Auth::user();
                        $manageType = $user?->manage_type ?? [];
                        
                        if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                            return \App\Models\Cabang::where('id', $user?->cabang_id)
                                ->get()
                                ->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                        }
                        
                        return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                        });
                    })
                    ->searchable(),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Source Type')
                    ->searchable()
                    ->options([
                        'App\\Models\\SaleOrder' => 'Sale Order',
                        'App\\Models\\PurchaseOrder' => 'Purchase Order',
                        'App\\Models\\Invoice' => 'Invoice',
                        'App\\Models\\DeliveryOrder' => 'Delivery Order',
                        'App\\Models\\CustomerReceipt' => 'Customer Receipt',
                        'App\\Models\\VendorPayment' => 'Vendor Payment',
                        'App\\Models\\MaterialIssue' => 'Material Issue',
                        'App\\Models\\ManufacturingOrder' => 'Manufacturing Order',
                        'App\\Models\\QualityControl' => 'Quality Control',
                        'App\\Models\\Asset' => 'Asset',
                    ]),

                Tables\Filters\Filter::make('source_id')
                    ->label('Source ID')
                    ->form([
                        Forms\Components\TextInput::make('source_id')
                            ->label('Source ID')
                            ->placeholder('Enter Source ID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['source_id'],
                            fn(Builder $query, $sourceId): Builder => $query->where('source_id', $sourceId),
                        );
                    })
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_source')
                    ->label('Lihat Detail Source')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn ($record) => !empty($record->source_type) && !empty($record->source_id))
                    ->action(function ($record) {
                        $sourceType = $record->source_type;
                        $sourceId = $record->source_id;

                        if (!$sourceType || !$sourceId) {
                            return;
                        }

                        try {
                            // Map source_type to resource URL
                            $url = match($sourceType) {
                                'App\\Models\\SaleOrder' => route('filament.admin.resources.sale-orders.view', $sourceId),
                                'App\\Models\\DeliveryOrder' => route('filament.admin.resources.delivery-orders.view', $sourceId),
                                'App\\Models\\CustomerReceipt' => route('filament.admin.resources.customer-receipts.view', $sourceId),
                                'App\\Models\\Asset' => route('filament.admin.resources.assets.view', $sourceId),
                                'App\\Models\\QualityControl' => self::getQualityControlViewUrl($record->source),
                                'App\\Models\\Invoice' => self::getInvoiceViewUrl($record->source),
                                default => null
                            };

                            if ($url) {
                                return redirect($url);
                            }
                        } catch (\Exception $e) {
                            // Route not found, show notification instead
                        }

                        // Fallback: show notification with source info
                        \Filament\Notifications\Notification::make()
                            ->title('Source Detail')
                            ->body("Source type: {$sourceType}, ID: {$sourceId}")
                            ->info()
                            ->send();
                    }),
                ])
                ], position: ActionsPosition::BeforeColumns)
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
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Journal Entry</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Journal Entry adalah pencatatan transaksi keuangan dalam sistem akuntansi ganda, mencatat debit dan kredit pada akun yang relevan.</li>' .
                            '<li><strong>Validasi:</strong> Setiap entri harus balance (total debit = total kredit). Terkait dengan COA (Chart of Account) dan dapat memiliki reference ke transaksi lain.</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah entri), <em>Delete</em> (hapus), <em>Go to Source</em> (ke transaksi asal).</li>' .
                            '<li><strong>Grouping:</strong> Berdasarkan Reference dan Date.</li>' .
                            '<li><strong>Filters:</strong> COA, Date Range, Amount Range, Reference, dll.</li>' .
                            '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan entri dari cabang tersebut jika tidak memiliki akses all.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan berbagai modul seperti penjualan, pembelian, inventory, dll. untuk otomatisasi pencatatan.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
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

    protected static function getInvoiceViewUrl($invoice)
    {
        if (!$invoice) return null;

        // Determine if it's sales or purchase invoice based on from_model_type
        if ($invoice->from_model_type === 'App\\Models\\SaleOrder') {
            return route('filament.admin.resources.sales-invoices.view', $invoice->id);
        } elseif ($invoice->from_model_type === 'App\\Models\\PurchaseOrder') {
            return route('filament.admin.resources.purchase-invoices.view', $invoice->id);
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'grouped' => Pages\GroupedJournalEntries::route('/grouped'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }

    /**
     * Get the appropriate view URL for Quality Control based on its from_model_type
     */
    protected static function getQualityControlViewUrl($qualityControl): ?string
    {
        if (!$qualityControl) {
            return null;
        }

        try {
            // Check the from_model_type to determine which resource to use
            if ($qualityControl->from_model_type === 'App\\Models\\Production') {
                // Quality Control Manufacture
                return route('filament.admin.resources.quality-control-manufactures.view', $qualityControl->id);
            } elseif ($qualityControl->from_model_type === 'App\\Models\\PurchaseReceiptItem') {
                // Quality Control Purchase
                return route('filament.admin.resources.quality-control-purchases.view', $qualityControl->id);
            }

            // Fallback to manufacture if type is unknown
            return route('filament.admin.resources.quality-control-manufactures.view', $qualityControl->id);
        } catch (\Exception $e) {
            return null;
        }
    }
}
