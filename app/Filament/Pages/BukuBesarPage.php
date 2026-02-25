<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class BukuBesarPage extends Page
{
    protected static string $view = 'filament.pages.buku-besar-page';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Finance - Akuntansi';

    protected static ?string $navigationLabel = 'Buku Besar (General Ledger)';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'buku-besar-page';

    public $coa_ids = [];
    public $start_date = null;
    public $end_date = null;
    public $view_mode = 'by_coa'; // 'by_coa', 'all'

    public function mount(Request $request): void
    {
        // default dates to current month
        $this->start_date = $request->query('start', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = $request->query('end', now()->endOfMonth()->format('Y-m-d'));

        // optional preselected coa
        $coaId = $request->query('coa_id');
        if ($coaId) {
            $this->coa_ids = [$coaId];
            $this->view_mode = 'by_coa';
        }
    }

    public function getCoaOptionsProperty(): array
    {
        return ChartOfAccount::query()
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(function ($account) {
                $label = sprintf('%s - %s', $account->code, $account->name);

                return [$account->id => $this->safeText($label, 'coa_options')];
            })
            ->toArray();
    }

    public function getSelectedCoasProperty(): Collection
    {
        return $this->coa_ids ? ChartOfAccount::whereIn('id', $this->coa_ids)->get() : collect();
    }

    /**
     * Livewire hook when coa_ids is updated from the frontend.
     * We log it to help debugging whether Livewire receives the change.
     */
    public function updatedCoaIds($value): void
    {
        Log::info('[BukuBesarPage] updatedCoaIds called with value: ' . var_export($value, true));
        $this->view_mode = 'by_coa';
    }

    /**
     * Method to be called from Alpine when COA selection changes.
     * This sets the view mode and updates the selected COAs.
     */
    public function selectCoas($coaIds): void
    {
        Log::info('[BukuBesarPage] selectCoas called with coaIds: ' . var_export($coaIds, true));
        $this->coa_ids = $coaIds;
        $this->view_mode = 'by_coa';
    }

    public function showAll(): void
    {
        Log::info('[BukuBesarPage] showAll() called');
        $this->coa_ids = [];
        $this->view_mode = 'all';
    }

    public function showByJournalEntry(): void
    {
        Log::info('[BukuBesarPage] showByJournalEntry() called');
        $this->coa_ids = [];
        $this->view_mode = 'by_journal_entry';
    }

    public function safeText(?string $value, ?string $context = null): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = $this->normalizeUtf8($value);

        if ($normalized !== $value) {
            Log::warning('[BukuBesarPage] Normalized text due to invalid UTF-8 characters', [
                'context' => $context,
                'sample_base64' => base64_encode($value),
            ]);
        }

        return $normalized;
    }

    protected function normalizeUtf8(string $value): string
    {
        $normalized = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        if (! mb_detect_encoding($normalized, 'UTF-8', true)) {
            $fallback = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            if ($fallback !== false && $fallback !== '') {
                $normalized = $fallback;
            }
        }

        if (! mb_detect_encoding($normalized, 'UTF-8', true)) {
            $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
            if (is_string($stripped) && $stripped !== '') {
                $normalized = $stripped;
            }
        }

        return $normalized;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
