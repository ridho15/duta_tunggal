<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class RekonsiliasiBankPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Rekonsiliasi Bank';
    protected static string $view = 'filament.pages.rekonsiliasi-bank-page';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'rekonsiliasi-bank-page';

    public $selectedCoaId = null;
    public $startDate;
    public $endDate;
    public $showConfirmed = true; // Toggle untuk show/hide data yang sudah dikonfirmasi
    public $entries = [];
    public $coaOptions = [];

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->loadCoaOptions();
    }

    public function loadCoaOptions(): void
    {
        $this->coaOptions = ChartOfAccount::query()
            ->where(function ($q) {
                $q->where('code', 'like', '111%') // Kas & Bank umumnya 111xxx
                    ->orWhere('code', 'like', '112%')
                    ->orWhere('name', 'like', '%Bank%')
                    ->orWhere('name', 'like', '%Kas%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn($coa) => [
                'id' => $coa->id,
                'label' => $coa->code . ' - ' . $coa->name,
            ])
            ->toArray();
    }

    public function loadEntries(): void
    {
        if (!$this->selectedCoaId || !$this->startDate || !$this->endDate) {
            $this->entries = [];
            return;
        }

        $query = JournalEntry::where('coa_id', $this->selectedCoaId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where(function ($q) {
                $q->where('debit', '>', 0)->orWhere('credit', '>', 0);
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // Jika showConfirmed = false, maka hide data yang sudah dikonfirmasi (ada_di_rekening)
        if (!$this->showConfirmed) {
            $query->where(function($q) {
                $q->whereNull('bank_recon_status')
                  ->orWhere('bank_recon_status', '!=', 'confirmed');
            });
        }

        $this->entries = $query->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'date' => $entry->date,
                'reference' => $entry->reference ?? '-',
                'description' => $entry->description ?? '-',
                'debit' => $entry->debit,
                'credit' => $entry->credit,
                'is_confirmed' => $entry->bank_recon_status === 'confirmed',
            ];
        })->toArray();
    }

    public function toggleConfirmation(int $entryId): void
    {
        try {
            $entry = JournalEntry::findOrFail($entryId);
            
            if ($entry->bank_recon_status === 'confirmed') {
                // Unmark as confirmed
                $entry->update([
                    'bank_recon_status' => null,
                    'bank_recon_date' => null,
                ]);
                $message = 'Transaksi berhasil di-unmark dari "Ada di Rekening"';
            } else {
                // Mark as confirmed (Ada di Rekening)
                $entry->update([
                    'bank_recon_status' => 'confirmed',
                    'bank_recon_date' => now()->toDateString(),
                ]);
                $message = 'Transaksi berhasil dikonfirmasi "Ada di Rekening"';
            }

            Notification::make()
                ->title('Berhasil')
                ->body($message)
                ->success()
                ->send();

            $this->loadEntries();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Gagal mengupdate status: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleShowConfirmed(): void
    {
        $this->showConfirmed = !$this->showConfirmed;
        $this->loadEntries();
    }

    public function updatedSelectedCoaId(): void
    {
        $this->loadEntries();
    }

    public function updatedStartDate(): void
    {
        $this->loadEntries();
    }

    public function updatedEndDate(): void
    {
        $this->loadEntries();
    }

    public function updatedShowConfirmed(): void
    {
        $this->loadEntries();
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getCoaOptions(): array
    {
        return $this->coaOptions;
    }
}
