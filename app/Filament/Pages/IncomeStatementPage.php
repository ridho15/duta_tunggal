<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\Action;
use App\Services\IncomeStatementService;
use App\Models\Cabang;
use App\Models\IncomeStatementItem;
use Filament\Notifications\Notification;
use App\Exports\IncomeStatementExport;
use App\Exports\IncomeStatementPdfExport;
use Maatwebsite\Excel\Facades\Excel;

class IncomeStatementPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.income-statement-page';

    protected static ?string $navigationLabel = 'Laba Rugi';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 6;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;
    public bool $show_comparison = false;
    public ?string $comparison_start_date = null;
    public ?string $comparison_end_date = null;

    // Display options
    public bool $show_only_totals = false;
    public bool $show_parent_accounts = true;
    public bool $show_child_accounts = true;
    public bool $show_zero_balance = false;    public function mount(): void
    {
        // Default to current year for better data visibility
        $this->form->fill([
            'start_date' => request()->query('start', now()->startOfYear()->format('Y-m-d')),
            'end_date' => request()->query('end', now()->endOfYear()->format('Y-m-d')),
            'cabang_id' => request()->query('cabang_id'),
            'show_comparison' => false,
            'comparison_start_date' => now()->subYear()->startOfYear()->format('Y-m-d'),
            'comparison_end_date' => now()->subYear()->endOfYear()->format('Y-m-d'),
            'show_only_totals' => false,
            'show_parent_accounts' => true,
            'show_child_accounts' => true,
            'show_zero_balance' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Laporan')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required(),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                return Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return Cabang::where('nama', 'like', "%{$search}%")
                                    ->orWhere('kode', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($cabang) {
                                        return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                    });
                            }),
                        Toggle::make('show_comparison')
                            ->label('Tampilkan Perbandingan')
                            ->live(),
                        DatePicker::make('comparison_start_date')
                            ->label('Tanggal Mulai Perbandingan')
                            ->visible(fn ($get) => $get('show_comparison')),
                        DatePicker::make('comparison_end_date')
                            ->label('Tanggal Akhir Perbandingan')
                            ->visible(fn ($get) => $get('show_comparison')),
                    ])->columns(2),

                Section::make('Opsi Tampilan')
                    ->schema([
                        Toggle::make('show_only_totals')
                            ->label('Tampilkan Hanya Total'),
                        Toggle::make('show_parent_accounts')
                            ->label('Tampilkan Akun Induk'),
                        Toggle::make('show_child_accounts')
                            ->label('Tampilkan Akun Anak'),
                        Toggle::make('show_zero_balance')
                            ->label('Tampilkan Saldo Nol'),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(IncomeStatementItem::query())
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Saldo')
                    ->formatStateUsing(function ($state) {
                        $amount = (float) $state;
                        if ($amount < 0) {
                            $formatted = '(' . number_format(abs($amount), 0, ',', '.') . ')';
                            return new \Illuminate\Support\HtmlString('<span style="color: #D9534F; font-weight: bold;">' . $formatted . '</span>');
                        }
                        return number_format($amount, 0, ',', '.');
                    })
                    ->sortable()
                    ->alignRight(),
            ])
            ->recordClasses(function ($record) {
                // Apply different styling based on row_type
                return match($record->row_type) {
                    'section_total' => 'bg-red-100 font-bold',
                    'computed' => 'bg-green-100 font-bold',
                    'subtotal' => 'bg-yellow-50 font-bold',
                    'child' => 'text-gray-600',
                    'parent' => 'font-semibold',
                    default => '',
                };
            })
            ->headerActions([
                Action::make('generate_report')
                    ->label('Generate Laporan')
                    ->icon('heroicon-o-document-chart-bar')
                    ->action(function () {
                        $this->generateReport();
                    }),
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => IncomeStatementItem::count() > 0)
                    ->action(function () {
                        $data = $this->form->getState();
                        return Excel::download(
                            new IncomeStatementExport(
                                $data['start_date'],
                                $data['end_date'],
                                $data['cabang_id'] ?? null
                            ),
                            'laporan-laba-rugi-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->color('danger')
                    ->visible(fn () => IncomeStatementItem::count() > 0)
                    ->action(function () {
                        $data = $this->form->getState();
                        $pdfExport = new IncomeStatementPdfExport(
                            $data['start_date'],
                            $data['end_date'],
                            $data['cabang_id'] ?? null
                        );
                        $pdf = $pdfExport->generatePdf();
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'laporan-laba-rugi-' . now()->format('Y-m-d') . '.pdf');
                    }),
            ])
            ->paginated(false);
    }

    public function generateReport(): void
    {
        $data = $this->form->getState();

        // Validate dates
        if (!$data['start_date'] || !$data['end_date']) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai dan akhir harus diisi.')
                ->send();
            return;
        }

        if ($data['start_date'] > $data['end_date']) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
                ->send();
            return;
        }

        Notification::make()
            ->title('Laporan diperbarui')
            ->success()
            ->body('Laporan Laba Rugi telah diperbarui.')
            ->send();

        // Clear existing data and populate with new data
        IncomeStatementItem::truncate();

        // Use grouped data (by parent COA) so we can show classification and totals
        $groupedData = app(IncomeStatementService::class)->getGroupedByParent([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'cabang_id' => $data['cabang_id'] ?? null,
        ]);

        $flatData = [];

        // Local helper to push a row with code/description/amount and row_type
        $pushRow = function ($code, $description, $amount, $rowType = null) use (&$flatData) {
            $flatData[] = [
                'code' => $code,
                'description' => $description,
                'amount' => $amount,
                'row_type' => $rowType,
            ];
        };

        // Process a section that uses grouped data
        $processSection = function ($sectionGrouped, $sectionLabel) use (&$pushRow) {
            // $sectionGrouped is an array with keys 'grouped' => Collection, 'total' => float
            if (!isset($sectionGrouped['grouped'])) {
                return;
            }

            $groups = $sectionGrouped['grouped'];
            $sectionTotal = $sectionGrouped['total'] ?? 0;

            foreach ($groups as $group) {
                $parent = $group['account'] ?? null;
                $children = $group['children'] ?? collect();

                // Parent row
                if ($this->show_parent_accounts && $parent) {
                    $pushRow($parent['code'] ?? '', $parent['name'] ?? '', $parent['balance'] ?? 0, 'parent');
                }

                // Children rows
                if ($this->show_child_accounts && $children instanceof \Illuminate\Support\Collection) {
                    foreach ($children as $child) {
                        $pushRow($child['code'] ?? '', $child['name'] ?? '', $child['balance'] ?? 0, 'child');
                    }
                }

                // No subtotal per parent - balance already includes children
            }

            // Section total row
            $pushRow('', 'Total ' . $sectionLabel, $sectionTotal, 'section_total');
        };

        // Map sections and labels
        $sections = [
            'sales_revenue' => ['data' => $groupedData['sales_revenue'] ?? null, 'label' => 'Pendapatan'],
            'cogs' => ['data' => $groupedData['cogs'] ?? null, 'label' => 'HPP'],
            'operating_expenses' => ['data' => $groupedData['operating_expenses'] ?? null, 'label' => 'Biaya Operasional'],
            'other_income' => ['data' => $groupedData['other_income'] ?? null, 'label' => 'Pendapatan Lain'],
            'other_expense' => ['data' => $groupedData['other_expense'] ?? null, 'label' => 'Biaya Lain'],
            'tax_expense' => ['data' => $groupedData['tax_expense'] ?? null, 'label' => 'Pajak'],
        ];

        foreach ($sections as $key => $section) {
            if ($section['data']) {
                $processSection($section['data'], $section['label']);

                // Insert computed rows in the right positions
                if ($key === 'cogs') {
                    // After COGS, add Gross Profit
                    $gross = $groupedData['gross_profit'] ?? 0;
                    $pushRow('', 'LABA KOTOR (Gross Profit)', $gross, 'computed');
                }

                if ($key === 'operating_expenses') {
                    // After operating expenses, add Operating Profit
                    $operatingProfit = $groupedData['operating_profit'] ?? 0;
                    $pushRow('', 'LABA OPERASIONAL (Operating Profit)', $operatingProfit, 'computed');
                }

                if ($key === 'tax_expense') {
                    // After tax, add Net Profit
                    $net = $groupedData['net_profit'] ?? 0;
                    $pushRow('', 'LABA BERSIH (Net Profit)', $net, 'computed');
                }
            }
        }

        // Save to database
        foreach ($flatData as $item) {
            IncomeStatementItem::create([
                'account_name' => $item['description'] ?? $item['code'] ?? null,
                'debit' => 0,
                'credit' => 0,
                'balance' => $item['amount'] ?? 0,
                'code' => $item['code'] ?? null,
                'description' => $item['description'] ?? null,
                'amount' => $item['amount'] ?? 0,
                'row_type' => $item['row_type'] ?? null,
            ]);
        }

        // Refresh table data
        $this->resetTable();
    }

    public function getIncomeStatementData(): array
    {
        $data = $this->form->getState();

        return app(IncomeStatementService::class)->generate([
            'start_date' => $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d'),
            'end_date' => $data['end_date'] ?? now()->endOfMonth()->format('Y-m-d'),
            'cabang_id' => $data['cabang_id'] ?? null,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'Laporan Laba Rugi (Income Statement)';
    }

    public function getHeading(): string
    {
        return 'Laporan Laba Rugi';
    }
}
