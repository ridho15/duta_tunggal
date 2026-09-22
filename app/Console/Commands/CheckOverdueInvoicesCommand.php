<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\OverdueInvoiceNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:check-overdue {--dry-run : Only inspect invoices without updating their database status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa dan perbarui status invoice yang melewati tanggal jatuh tempo menjadi Overdue (Terlambat)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = Carbon::today()->toDateString();

        $this->info("Menjalankan pemeriksaan invoice jatuh tempo per tanggal: {$today}" . ($isDryRun ? " [DRY-RUN]" : ""));

        // 1. Cari invoice non-draft dan non-paid yang due_date < today
        $invoicesToCheck = Invoice::query()
            ->with(['accountPayable', 'accountReceivable'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereNotIn(DB::raw('LOWER(status)'), [
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_PAID,
                'cancelled',
                'canceled',
            ])
            ->get();

        $updatedToOverdue = 0;
        $restoredFromOverdue = 0;
        $newlyOverdue = collect();

        foreach ($invoicesToCheck as $invoice) {
            $remaining = $invoice->getRemainingAmount();

            if ($remaining > 0.01) {
                if (strtolower((string) $invoice->status) !== Invoice::STATUS_OVERDUE) {
                    $this->line(" - Invoice [{$invoice->invoice_number}] (Due: {$invoice->due_date?->format('Y-m-d')}, Sisa: {$remaining}) -> OVERDUE");
                    if (!$isDryRun) {
                        $invoice->update(['status' => Invoice::STATUS_OVERDUE]);
                        Log::info("Invoice status updated to overdue", [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'due_date' => $invoice->due_date?->format('Y-m-d'),
                            'remaining' => $remaining,
                        ]);
                        $newlyOverdue->push($invoice);
                    }
                    $updatedToOverdue++;
                }
            } else {
                // Sisa sudah <= 0.01 tapi status belum Paid
                if (strtolower((string) $invoice->status) !== Invoice::STATUS_PAID) {
                    $this->line(" - Invoice [{$invoice->invoice_number}] (Sisa lunas) -> PAID");
                    if (!$isDryRun) {
                        $invoice->update(['status' => Invoice::STATUS_PAID]);
                    }
                }
            }
        }

        // 2. Cari invoice berstatus Overdue yang ternyata tanggal temponya >= today (misal diperpanjang)
        $overdueInvoices = Invoice::query()
            ->with(['accountPayable', 'accountReceivable'])
            ->where(DB::raw('LOWER(status)'), Invoice::STATUS_OVERDUE)
            ->where(function ($query) use ($today) {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '>=', $today);
            })
            ->get();

        foreach ($overdueInvoices as $inv) {
            $remaining = $inv->getRemainingAmount();
            $newStatus = $remaining <= 0.01
                ? Invoice::STATUS_PAID
                : (abs($remaining - (float)$inv->total) > 0.01 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_SENT);

            $this->line(" - Invoice [{$inv->invoice_number}] tanggal tempo diperpanjang -> {$newStatus}");
            if (!$isDryRun) {
                $inv->update(['status' => $newStatus]);
            }
            $restoredFromOverdue++;
        }

        // Notifikasi ke finance (Isu 8): hanya invoice yang BENAR-BENAR baru berubah pada jalankan ini (bukan dry-run).
        if ($newlyOverdue->isNotEmpty()) {
            app(OverdueInvoiceNotifier::class)->notify($newlyOverdue);
        }

        $this->info("Pemeriksaan selesai.");
        $this->info("Total diubah ke Overdue : {$updatedToOverdue}");
        $this->info("Total dipulihkan        : {$restoredFromOverdue}");

        return Command::SUCCESS;
    }
}
