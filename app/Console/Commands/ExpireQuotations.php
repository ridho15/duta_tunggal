<?php

namespace App\Console\Commands;

use App\Services\QuotationService;
use Illuminate\Console\Command;

/**
 * Menandai quotation Approved yang valid_until-nya sudah lewat sebagai "Kedaluwarsa".
 *
 * Dijadwalkan harian (routes: app/Console/Kernel.php). Guard berbasis tanggal di server
 * (Quotation::unusableReasonForSaleOrder) tetap memblokir pembuatan SO dari quotation
 * kedaluwarsa walaupun job ini belum berjalan.
 *
 * Mengikuti pola invoices:check-overdue: dijalankan sungguhan secara default,
 * --dry-run hanya melaporkan.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire {--dry-run : Hanya laporkan quotation yang akan ditandai kedaluwarsa}';

    protected $description = 'Tandai quotation Approved yang sudah melewati Valid Until sebagai Kedaluwarsa';

    public function handle(QuotationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Pemeriksaan quotation kedaluwarsa per ' . now()->toDateString() . ($dryRun ? ' [DRY-RUN]' : ''));

        $overdue = $service->expireOverdue($dryRun);

        foreach ($overdue as $quotation) {
            $this->line(" - {$quotation->quotation_number} (berlaku sampai {$quotation->valid_until?->format('Y-m-d')}) -> KEDALUWARSA");
        }

        $this->info(($dryRun ? 'Akan ditandai' : 'Ditandai') . ' kedaluwarsa: ' . $overdue->count());

        return self::SUCCESS;
    }
}
