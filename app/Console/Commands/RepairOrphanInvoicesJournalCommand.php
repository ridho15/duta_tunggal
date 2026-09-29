<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Observers\InvoiceObserver;
use App\Services\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairOrphanInvoicesJournalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:repair-journals 
                            {--invoice= : Nomor invoice spesifik, misal INV-20260928-0001} 
                            {--dry-run : Simulasi pengecekan tanpa melakukan posting jurnal}
                            {--all : Periksa semua invoice termasuk yang berstatus draft}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deteksi dan pulihkan invoice yang kehilangan jurnal buku besar (orphan invoices)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificNumber = $this->option('invoice');
        $dryRun = (bool) $this->option('dry-run');
        $checkAll = (bool) $this->option('all');

        $this->info('Memulai audit dan reparasi jurnal invoice...');
        if ($dryRun) {
            $this->warn('MODE DRY-RUN: Tidak ada jurnal yang akan dibuat ke database.');
        }

        $query = Invoice::query()->with('invoiceItem');

        if ($specificNumber) {
            $query->where('invoice_number', $specificNumber);
        } elseif (! $checkAll) {
            $query->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PAID,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_OVERDUE,
            ]);
        }

        $invoices = $query->orderBy('id', 'asc')->get();

        if ($invoices->isEmpty()) {
            $this->info('Tidak ada invoice yang memenuhi kriteria pencarian.');
            return 0;
        }

        $this->info("Menemukan {$invoices->count()} invoice untuk diperiksa.");

        $tableRows = [];
        $repairedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        $invoiceObserver = new InvoiceObserver();
        $ledgerService = new LedgerPostingService();

        foreach ($invoices as $invoice) {
            $hasJournal = JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->exists();

            $isSales = ($invoice->from_model_type === 'App\\Models\\SaleOrder') 
                || !empty($invoice->customer_name)
                || !empty($invoice->customer_id);

            $typeLabel = $isSales ? 'Sales' : 'Purchase';

            if ($hasJournal) {
                $skippedCount++;
                if ($specificNumber) {
                    $journalCount = JournalEntry::where('source_type', Invoice::class)
                        ->where('source_id', $invoice->id)
                        ->count();

                    $tableRows[] = [
                        $invoice->id,
                        $invoice->invoice_number,
                        $typeLabel,
                        $invoice->status,
                        number_format((float) $invoice->total, 2, ',', '.'),
                        'Sudah Ada Jurnal (' . $journalCount . ' baris)',
                        'Seimbang',
                    ];
                }
                continue;
            }

            // Invoice tidak memiliki jurnal!
            $statusCol = 'YATIM (Tanpa Jurnal)';
            $balanceCol = '-';

            if ($dryRun) {
                $statusCol = 'Akan Direparasi [DRY-RUN]';
                $repairedCount++;
            } else {
                try {
                    DB::transaction(function () use ($invoice, $isSales, $invoiceObserver, $ledgerService) {
                        if ($isSales) {
                            $invoiceObserver->postSalesInvoice($invoice);
                        } else {
                            $ledgerService->postInvoice($invoice);
                        }
                    });

                    // Verifikasi hasil pembuatan jurnal
                    $newEntries = JournalEntry::where('source_type', Invoice::class)
                        ->where('source_id', $invoice->id)
                        ->get();

                    if ($newEntries->isNotEmpty()) {
                        $totalDebit = (float) $newEntries->sum('debit');
                        $totalCredit = (float) $newEntries->sum('credit');
                        $diff = abs($totalDebit - $totalCredit);

                        if ($diff <= 0.05) {
                            $statusCol = 'BERHASIL DIREPARASI (' . $newEntries->count() . ' baris)';
                            $balanceCol = 'Seimbang (Rp ' . number_format($totalDebit, 2, ',', '.') . ')';
                            $repairedCount++;
                            Log::info("RepairOrphanInvoicesJournal: Invoice {$invoice->invoice_number} successfully repaired with {$newEntries->count()} journal entries.");
                        } else {
                            $statusCol = 'GAGAL: Tidak Seimbang';
                            $balanceCol = "Selisih: Rp " . number_format($diff, 2, ',', '.');
                            $failedCount++;
                        }
                    } else {
                        $statusCol = 'GAGAL: Tidak Ada Jurnal Dibuat';
                        $failedCount++;
                    }
                } catch (\Throwable $e) {
                    $statusCol = 'ERROR: ' . substr($e->getMessage(), 0, 40) . '...';
                    $failedCount++;
                    Log::error("RepairOrphanInvoicesJournal: Failed for {$invoice->invoice_number}: " . $e->getMessage());
                }
            }

            $tableRows[] = [
                $invoice->id,
                $invoice->invoice_number,
                $typeLabel,
                $invoice->status,
                number_format((float) $invoice->total, 2, ',', '.'),
                $statusCol,
                $balanceCol,
            ];
        }

        $this->table(
            ['ID', 'No Invoice', 'Tipe', 'Status', 'Total (Rp)', 'Hasil Audit / Reparasi', 'Keseimbangan GL'],
            $tableRows
        );

        $this->newLine();
        $this->info("Ringkasan Hasil:");
        $this->line("- Total Diperiksa : {$invoices->count()}");
        $this->line("- Sudah Lengkap   : {$skippedCount}");
        $this->line("- Berhasil Reparasi: {$repairedCount}");
        if ($failedCount > 0) {
            $this->error("- Gagal / Error    : {$failedCount}");
            return 1;
        }

        $this->info('Audit dan reparasi jurnal selesai.');
        return 0;
    }
}
