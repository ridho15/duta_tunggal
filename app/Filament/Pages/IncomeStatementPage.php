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
                TextColumn::make('account_name')
                    ->label('Akun'),
                TextColumn::make('debit')
                    ->label('Debit')
                    ->money('IDR'),
                TextColumn::make('credit')
                    ->label('Kredit')
                    ->money('IDR'),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('IDR'),
            ])
            ->headerActions([
                Action::make('generate_report')
                    ->label('Generate Laporan')
                    ->icon('heroicon-o-document-chart-bar')
                    ->action(function () {
                        $this->generateReport();
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
        $incomeData = $this->getIncomeStatementData();
        
        // Process the complex income statement data into flat array
        $flatData = [];
        
        // Helper function to add accounts from a section
        $addAccounts = function($accounts, $sectionName = '') use (&$flatData) {
            if ($accounts instanceof \Illuminate\Support\Collection) {
                foreach ($accounts as $account) {
                    $flatData[] = [
                        'account_name' => ($sectionName ? $sectionName . ' - ' : '') . ($account['name'] ?? ''),
                        'debit' => $account['total_debit'] ?? 0,
                        'credit' => $account['total_credit'] ?? 0,
                        'balance' => $account['balance'] ?? 0,
                    ];
                }
            }
        };
        
        // Add accounts from each section
        if (isset($incomeData['sales_revenue']['accounts'])) {
            $addAccounts($incomeData['sales_revenue']['accounts'], 'Pendapatan');
        }
        if (isset($incomeData['cogs']['accounts'])) {
            $addAccounts($incomeData['cogs']['accounts'], 'HPP');
        }
        if (isset($incomeData['operating_expenses']['accounts'])) {
            $addAccounts($incomeData['operating_expenses']['accounts'], 'Biaya Operasional');
        }
        if (isset($incomeData['other_income']['accounts'])) {
            $addAccounts($incomeData['other_income']['accounts'], 'Pendapatan Lain');
        }
        if (isset($incomeData['other_expense']['accounts'])) {
            $addAccounts($incomeData['other_expense']['accounts'], 'Biaya Lain');
        }
        if (isset($incomeData['tax_expense']['accounts'])) {
            $addAccounts($incomeData['tax_expense']['accounts'], 'Pajak');
        }
        
        // Save to database
        foreach ($flatData as $item) {
            IncomeStatementItem::create([
                'account_name' => $item['account_name'],
                'debit' => $item['debit'],
                'credit' => $item['credit'],
                'balance' => $item['balance'],
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
