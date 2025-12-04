<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Exports\GenericViewExport;
use App\Filament\Resources\JournalEntryResource;
use App\Models\Cabang;
use App\Services\JournalEntryAggregationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class GroupedJournalEntries extends Page
{

    protected static string $resource = JournalEntryResource::class;

    protected static string $view = 'filament.resources.journal-entry-resource.pages.grouped-journal-entries';

    protected static ?string $slug = 'grouped';

    protected static ?string $title = 'Journal Entries - Grouped by Parent COA';
    // Register this page for navigation so it can be accessed
    protected static bool $shouldRegisterNavigation = true;
    // Make the page title/navigation label explicit (useful in breadcrumbs and
    // for clarity if the page is ever exposed in sub-navigation). Also set the
    // parent item so, if registered in the future, it will be grouped under
    // the main Journal Entries item.
    protected static ?string $navigationLabel = 'Journal Entries (Grouped)';
    protected static ?string $navigationParentItem = 'Journal Entries';

    public ?array $data = [];
    public $groupedData = [];
    public $summary = [];

    public function mount(): void
    {
        $this->loadData();
    }

    // public function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([
    //             Forms\Components\Section::make('Filters')
    //             ->schema([
    //                 Forms\Components\DatePicker::make('start_date')
    //                     ->label('Start Date')
    //                     ->default(now()->startOfMonth())
    //                     ->reactive(),
                
    //                 Forms\Components\DatePicker::make('end_date')
    //                     ->label('End Date')
    //                     ->default(now()->endOfMonth())
    //                     ->reactive(),
                
    //                 Forms\Components\Select::make('journal_type')
    //                     ->label('Journal Type')
    //                     ->options([
    //                         'sales' => 'Sales',
    //                         'purchase' => 'Purchase',
    //                         'depreciation' => 'Depreciation',
    //                         'manual' => 'Manual',
    //                         'transfer' => 'Transfer',
    //                         'payment' => 'Payment',
    //                         'receipt' => 'Receipt',
    //                     ])
    //                     ->placeholder('All Types')
    //                     ->reactive(),
                
    //                 Forms\Components\Select::make('cabang_id')
    //                     ->label('Branch')
    //                     ->options(Cabang::pluck('nama', 'id')->toArray())
    //                     ->searchable()
    //                     ->placeholder('All Branches')
    //                     ->reactive(),
    //             ])
    //             ->columns(4),
    //         ])
    //         ->statePath('data');
    // }

    // public function applyFilters(): void
    // {
    //     $this->loadData();
    // }

    protected function loadData(): void
    {
        $service = app(JournalEntryAggregationService::class);
        $filters = $this->resolveFilters();

        $this->groupedData = $service->getGroupedByParent($filters);
        $this->summary = $service->getSummary($filters);
    }

    /**
     * Resolve current filter state for re-use between the grid and exports.
     */
    protected function resolveFilters(): array
    {
        $filters = [
            'start_date' => $this->data['start_date'] ?? null,
            'end_date' => $this->data['end_date'] ?? null,
            'journal_type' => $this->data['journal_type'] ?? null,
            'cabang_id' => $this->data['cabang_id'] ?? null,
        ];

        return array_filter($filters, fn ($value) => filled($value));
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => JournalEntryResource::getUrl('index'))
                ->color('gray'),
            
            \Filament\Actions\Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportToExcel')
                ->color('success'),
        ];
    }

    // public function exportToExcel(): ?BinaryFileResponse
    // {
    //     $service = app(JournalEntryAggregationService::class);
    //     $filters = $this->resolveFilters();

    //     try {
    //         $groupedData = $service->getGroupedByParent($filters);
    //         $summary = $service->getSummary($filters);

    //         if ($groupedData->isEmpty()) {
    //             Notification::make()
    //                 ->title('Data tidak ditemukan')
    //                 ->warning()
    //                 ->body('Tidak ada data jurnal sesuai filter saat ini untuk di-export.')
    //                 ->send();

    //             return null;
    //         }

    //         $filename = 'journal-entries-grouped-' . now()->format('Ymd_His') . '.xlsx';
    //         $view = view('filament.exports.journal-entries-grouped-excel', [
    //             'groupedData' => $groupedData,
    //             'summary' => $summary,
    //             'filters' => $filters,
    //         ]);

    //         return Excel::download(new GenericViewExport($view), $filename);
    //     } catch (\Throwable $exception) {
    //         report($exception);

    //         Notification::make()
    //             ->title('Export gagal')
    //             ->danger()
    //             ->body('Terjadi kesalahan saat melakukan export: ' . $exception->getMessage())
    //             ->send();

    //         return null;
    //     }
    // }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getTitle(): string
    {
        return 'Journal Entries - Grouped View';
    }
}
