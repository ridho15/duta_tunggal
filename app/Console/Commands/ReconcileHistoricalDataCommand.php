<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\StockAdjustment;
use App\Models\StockReservation;
use App\Models\StockTransfer;
use App\Models\UnitOfMeasure;
use App\Observers\InvoiceObserver;
use App\Services\CustomerMerger;
use App\Services\LedgerPostingService;
use App\Services\StockAdjustmentService;
use App\Services\StockReservationLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileHistoricalDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:reconcile-data 
                            {--dry-run : Jalankan simulasi pengecekan tanpa melakukan perubahan ke basis data}
                            {--task=all : Pilihan tugas spesifik: all, journals, adjustments, transfers, reserved-stock, units, customers}
                            {--force : Lewati konfirmasi interaktif untuk eksekusi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pembersihan data anomali dan rekonsiliasi data historis (Sprint 4 Data Maintenance)';

    /**
     * Execute the console command.
     */
    public function handle(
        StockAdjustmentService $adjustmentService,
        CustomerMerger $customerMerger,
        StockReservationLedger $reservationLedger
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $task = strtolower((string) ($this->option('task') ?: 'all'));
        $force = (bool) $this->option('force');

        $this->info('===============================================================');
        $this->info('    SPRINT 4: REKONSILIASI HISTORIS & PEMBERSIHAN DATA ERP     ');
        $this->info('===============================================================');

        if ($dryRun) {
            $this->warn('MODE DRY-RUN DIAKTIFKAN: Tidak ada perubahan permanen pada basis data.');
        } else {
            $this->alert('PERINGATAN: Perubahan akan disimpan secara permanen ke basis data.');
            if (! $force && ! $this->confirm('Apakah Anda yakin ingin melanjutkan eksekusi rekonsiliasi data ini?')) {
                $this->warn('Eksekusi dibatalkan oleh pengguna.');
                return self::SUCCESS;
            }
        }

        $summary = [
            'orphan_invoices' => 0,
            'stock_adjustments' => 0,
            'empty_transfers' => 0,
            'orphan_reservations' => 0,
            'reserved_stock_resets' => 0,
            'sanitized_units' => 0,
            'merged_customers' => 0,
        ];

        DB::beginTransaction();

        try {
            // Task 1: Orphan Invoices (Jurnal Faktur)
            if ($task === 'all' || $task === 'journals') {
                $summary['orphan_invoices'] = $this->reconcileOrphanInvoices($dryRun);
            }

            // Task 2: Stock Adjustments Without GL
            if ($task === 'all' || $task === 'adjustments') {
                $summary['stock_adjustments'] = $this->reconcileStockAdjustments($adjustmentService, $dryRun);
            }

            // Task 3: Empty Stock Transfers
            if ($task === 'all' || $task === 'transfers') {
                $summary['empty_transfers'] = $this->cleanEmptyStockTransfers($dryRun);
            }

            // Task 4: Reserved Stock & Orphan Reservations
            if ($task === 'all' || $task === 'reserved-stock') {
                $resResult = $this->reconcileReservedStock($reservationLedger, $dryRun);
                $summary['orphan_reservations'] = $resResult['orphans'];
                $summary['reserved_stock_resets'] = $resResult['resets'];
            }

            // Task 5: Master Units Sanitation
            if ($task === 'all' || $task === 'units') {
                $summary['sanitized_units'] = $this->sanitizeMasterUnits($dryRun);
            }

            // Task 6: Customer Duplicates Merge
            if ($task === 'all' || $task === 'customers') {
                $summary['merged_customers'] = $this->consolidateDuplicateCustomers($customerMerger, $dryRun);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->line('');
                $this->warn('Simulasi dry-run selesai. Seluruh perubahan transaksi telah di-rollback.');
            } else {
                DB::commit();
                $this->line('');
                $this->info('Seluruh perubahan berhasil di-commit secara permanen ke basis data.');
            }

            $this->displaySummaryTable($summary, $dryRun);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Terjadi kesalahan fatal selama rekonsiliasi: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            Log::error('ReconcileHistoricalDataCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Task 1: Reconcile orphan invoices that lack GL journals.
     */
    protected function reconcileOrphanInvoices(bool $dryRun): int
    {
        $this->info("\n--- [1/6] Memeriksa Jurnal Faktur (Orphan Invoices) ---");

        $invoices = Invoice::whereIn('status', [
            Invoice::STATUS_SENT,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_OVERDUE,
        ])
        ->whereDoesntHave('journalEntries')
        ->with('invoiceItem')
        ->get();

        $count = $invoices->count();
        if ($count === 0) {
            $this->line('✓ Tidak ditemukan invoice berstatus aktif yang kehilangan jurnal GL.');
            return 0;
        }

        $this->warn("Ditemukan {$count} invoice tanpa jurnal GL:");
        $tableData = [];

        $processed = 0;
        foreach ($invoices as $invoice) {
            $tableData[] = [
                $invoice->id,
                $invoice->invoice_number,
                $invoice->status,
                number_format((float) $invoice->total, 2, ',', '.'),
                $dryRun ? 'Akan Diposting Ulang' : 'Diposting Ulang',
            ];

            if (! $dryRun) {
                try {
                    $isPurchase = str_starts_with((string) $invoice->invoice_number, 'PINV')
                        || in_array($invoice->from_model_type, [\App\Models\PurchaseOrder::class, \App\Models\PurchaseReceipt::class], true)
                        || $invoice->supplier_id !== null;

                    if ($isPurchase) {
                        app(LedgerPostingService::class)->postInvoice($invoice);
                    } else {
                        $observer = new InvoiceObserver();
                        $observer->postSalesInvoice($invoice);
                    }
                    $processed++;
                } catch (\Throwable $e) {
                    $this->error("Gagal posting jurnal invoice {$invoice->invoice_number}: {$e->getMessage()}");
                    Log::warning("ReconcileHistoricalDataCommand: failed posting invoice {$invoice->invoice_number}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $processed++;
            }
        }

        $this->table(['ID', 'No Invoice', 'Status', 'Total (Rp)', 'Tindakan'], $tableData);

        return $processed;
    }

    /**
     * Task 2: Reconcile stock adjustments without GL.
     */
    protected function reconcileStockAdjustments(StockAdjustmentService $service, bool $dryRun): int
    {
        $this->info("\n--- [2/6] Memeriksa Jurnal Penyesuaian Stok (Stock Adjustment GL) ---");

        $adjustments = StockAdjustment::where('status', 'approved')
            ->whereDoesntHave('journalEntries')
            ->with(['items.product', 'warehouse'])
            ->get();

        $count = $adjustments->count();
        if ($count === 0) {
            $this->line('✓ Seluruh dokumen penyesuaian stok yang disetujui telah memiliki jurnal GL.');
            return 0;
        }

        $this->warn("Ditemukan {$count} stock adjustment disetujui tanpa jurnal GL:");
        $tableData = [];

        foreach ($adjustments as $adj) {
            $totalVariance = $adj->items->sum('difference_value');
            $adjDate = $adj->adjustment_date ? ($adj->adjustment_date instanceof \DateTimeInterface ? $adj->adjustment_date->format('Y-m-d') : (string) $adj->adjustment_date) : '-';

            $tableData[] = [
                $adj->id,
                $adj->adjustment_number,
                $adj->status,
                $adjDate,
                number_format((float) $totalVariance, 2, ',', '.'),
                $dryRun ? 'Akan Diterbitkan Jurnal' : 'Diterbitkan Jurnal',
            ];

            if (! $dryRun) {
                $service->syncJournalEntries($adj);
            }
        }

        $this->table(['ID', 'No Penyesuaian', 'Status', 'Tanggal', 'Nilai Selisih (Rp)', 'Tindakan'], $tableData);

        return $count;
    }

    /**
     * Task 3: Clean empty stock transfers without items.
     */
    protected function cleanEmptyStockTransfers(bool $dryRun): int
    {
        $this->info("\n--- [3/6] Memeriksa Transfer Stok Kosong Tanpa Item ---");

        $emptyTransfers = StockTransfer::doesntHave('stockTransferItem')->get();
        $count = $emptyTransfers->count();

        if ($count === 0) {
            $this->line('✓ Tidak ditemukan transfer stok kosong di basis data.');
            return 0;
        }

        $this->warn("Ditemukan {$count} transfer stok kosong tanpa item:");
        $tableData = [];

        foreach ($emptyTransfers as $transfer) {
            $transferDate = $transfer->transfer_date ? ($transfer->transfer_date instanceof \DateTimeInterface ? $transfer->transfer_date->format('Y-m-d') : (string) $transfer->transfer_date) : '-';

            $tableData[] = [
                $transfer->id,
                $transfer->transfer_number,
                $transfer->status,
                $transferDate,
                $dryRun ? 'Akan Dihapus' : 'Dihapus',
            ];

            if (! $dryRun) {
                $transfer->forceDelete();
            }
        }

        $this->table(['ID', 'No Transfer', 'Status', 'Tanggal', 'Tindakan'], $tableData);

        return $count;
    }

    /**
     * Task 4: Reconcile reserved stock & orphan reservations.
     *
     * @return array{orphans: int, resets: int}
     */
    protected function reconcileReservedStock(StockReservationLedger $ledger, bool $dryRun): array
    {
        $this->info("\n--- [4/6] Memeriksa Cadangan Stok (Reserved Stock Recalculation) ---");

        // A. Cek reservasi yatim (SO/DO yang sudah batal/selesai/tertutup)
        $endedSoStatuses = ['canceled', 'closed', 'completed', 'reject'];
        $endedDoStatuses = ['sent', 'received', 'completed', 'closed', 'reject'];

        $orphanReservations = StockReservation::query()
            ->where(function ($q) use ($endedSoStatuses, $endedDoStatuses) {
                $q->whereHas('saleOrder', fn ($sq) => $sq->whereIn('status', $endedSoStatuses))
                  ->orWhereHas('deliveryOrder', fn ($dq) => $dq->whereIn('status', $endedDoStatuses))
                  ->orWhere(fn ($sub) => $sub->whereNotNull('sale_order_id')->whereDoesntHave('saleOrder'))
                  ->orWhere(fn ($sub) => $sub->whereNotNull('delivery_order_id')->whereDoesntHave('deliveryOrder'))
                  ->orWhere(fn ($sub) => $sub->whereNull('sale_order_id')->whereNull('delivery_order_id')->whereNull('material_issue_id'));
            })
            ->get();

        $orphanCount = $orphanReservations->count();
        if ($orphanCount > 0) {
            $this->warn("Ditemukan {$orphanCount} baris reservasi yatim dari dokumen yang sudah selesai/dibatalkan:");
            foreach ($orphanReservations as $res) {
                if (! $dryRun) {
                    $ledger->release($res, 'Pembersihan reservasi yatim oleh rekonsiliasi data Sprint 4');
                }
            }
        } else {
            $this->line('✓ Tidak ditemukan baris reservasi stok yatim.');
        }

        // B. Cek inventory_stocks yang memiliki qty_reserved padahal tidak ada reservasi aktif
        $orphanIds = $orphanReservations->pluck('id')->all();
        $activeReservationTotals = StockReservation::query()
            ->when(! empty($orphanIds), fn ($q) => $q->whereNotIn('id', $orphanIds))
            ->selectRaw('product_id, warehouse_id, COALESCE(rak_id, 0) as rak_key, SUM(quantity) as expected_reserved')
            ->groupBy('product_id', 'warehouse_id', DB::raw('COALESCE(rak_id, 0)'))
            ->get()
            ->keyBy(fn ($r) => "{$r->product_id}-{$r->warehouse_id}-{$r->rak_key}");

        $stocksWithReserved = InventoryStock::where('qty_reserved', '!=', 0)->get();
        $resetCount = 0;
        $tableData = [];

        foreach ($stocksWithReserved as $stock) {
            $rakKey = $stock->rak_id ?: 0;
            $key = "{$stock->product_id}-{$stock->warehouse_id}-{$rakKey}";
            $expected = (float) ($activeReservationTotals->get($key)?->expected_reserved ?? 0);
            $current = (float) $stock->qty_reserved;

            if (abs($current - $expected) > 0.0001) {
                $productName = Product::find($stock->product_id)?->name ?? "Produk #{$stock->product_id}";
                $tableData[] = [
                    $stock->id,
                    $productName,
                    $stock->warehouse_id,
                    $current,
                    $expected,
                    $dryRun ? 'Akan Disesuaikan' : 'Disesuaikan',
                ];

                if (! $dryRun) {
                    $stock->update(['qty_reserved' => $expected]);
                }
                $resetCount++;
            }
        }

        if ($resetCount > 0) {
            $this->warn("Ditemukan {$resetCount} saldo reserved stock di gudang yang tidak sesuai dengan reservasi aktif:");
            $this->table(['ID Stok', 'Produk', 'Gudang', 'Reserved Lama', 'Reserved Seharusnya', 'Tindakan'], $tableData);
        } else {
            $this->line('✓ Seluruh saldo reserved stock di gudang sinkron 100% dengan reservasi aktif.');
        }

        return ['orphans' => $orphanCount, 'resets' => $resetCount];
    }

    /**
     * Task 5: Sanitize unit of measures containing ']'.
     */
    protected function sanitizeMasterUnits(bool $dryRun): int
    {
        $this->info("\n--- [5/6] Memeriksa Master Satuan Barang (Sanitasi Karakter ']') ---");

        $uoms = UnitOfMeasure::where('name', 'like', '%]%')
            ->orWhere('abbreviation', 'like', '%]%')
            ->get();

        $count = $uoms->count();
        if ($count === 0) {
            $this->line('✓ Master satuan barang bersih dari karakter kurung siku penutup.');
            return 0;
        }

        $this->warn("Ditemukan {$count} master satuan mengandung karakter ']':");
        $tableData = [];

        foreach ($uoms as $uom) {
            $cleanName = trim(str_replace(']', '', $uom->name));
            $cleanAbbr = trim(str_replace(']', '', $uom->abbreviation));

            $tableData[] = [
                $uom->id,
                $uom->name,
                $cleanName,
                $uom->abbreviation,
                $cleanAbbr,
                $dryRun ? 'Akan Disanitasi' : 'Disanitasi',
            ];

            if (! $dryRun) {
                $uom->update([
                    'name' => $cleanName,
                    'abbreviation' => $cleanAbbr,
                ]);
            }
        }

        $this->table(['ID', 'Nama Lama', 'Nama Bersih', 'Singkatan Lama', 'Singkatan Bersih', 'Tindakan'], $tableData);

        return $count;
    }

    /**
     * Task 6: Consolidate duplicate customers.
     */
    protected function consolidateDuplicateCustomers(CustomerMerger $merger, bool $dryRun): int
    {
        $this->info("\n--- [6/6] Memeriksa Master Customer Duplikat ---");

        // Cari customer dengan nama/perusahaan yang serupa
        $duplicates = Customer::query()
            ->select('perusahaan')
            ->whereNotNull('perusahaan')
            ->where('perusahaan', '!=', '')
            ->whereNull('merged_into')
            ->groupBy('perusahaan')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('perusahaan');

        if ($duplicates->isEmpty()) {
            $this->line('✓ Tidak ditemukan master customer duplikat aktif dengan nama perusahaan sama.');
            return 0;
        }

        $mergedPairs = 0;
        foreach ($duplicates as $perusahaan) {
            $records = Customer::where('perusahaan', $perusahaan)
                ->whereNull('merged_into')
                ->orderBy('id', 'asc')
                ->get();

            if ($records->count() < 2) {
                continue;
            }

            $survivor = $records->first();
            $mergedCandidates = $records->slice(1);

            foreach ($mergedCandidates as $candidate) {
                $this->warn("Ditemukan pasangan customer duplikat: #{$survivor->id} (Survivor: {$survivor->name}) & #{$candidate->id} (Merged: {$candidate->name})");

                $blocker = $merger->blocker($survivor, $candidate);
                if ($blocker) {
                    $this->error("Penggabungan dibatalkan: {$blocker}");
                    continue;
                }

                if (! $dryRun) {
                    $candidate->forceFill([
                        'keterangan' => trim(($candidate->keterangan ?? '') . " (DUPLIKAT - MERGED KE ID {$survivor->id})"),
                    ])->saveQuietly();
                    $merger->merge($survivor, $candidate);
                }
                $mergedPairs++;
            }
        }

        return $mergedPairs;
    }

    /**
     * Display execution summary table.
     *
     * @param  array<string, int>  $summary
     */
    protected function displaySummaryTable(array $summary, bool $dryRun): void
    {
        $this->line('');
        $this->info('===============================================================');
        $this->info('                  RINGKASAN HASIL REKONSILIASI                 ');
        $this->info('===============================================================');

        $statusLabel = $dryRun ? 'Terdeteksi (Simulasi)' : 'Terekonsiliasi / Diperbaiki';

        $this->table(
            ['Kategori Pekerjaan', 'Jumlah Item', 'Status Eksekusi'],
            [
                ['Orphan Invoices (Jurnal Faktur)', $summary['orphan_invoices'], $statusLabel],
                ['Stock Adjustments GL (Jurnal Penyesuaian)', $summary['stock_adjustments'], $statusLabel],
                ['Empty Stock Transfers (Transfer Kosong)', $summary['empty_transfers'], $statusLabel],
                ['Orphan Stock Reservations (Reservasi Yatim)', $summary['orphan_reservations'], $statusLabel],
                ['Reserved Stock Discrepancies (Reset Cadangan)', $summary['reserved_stock_resets'], $statusLabel],
                ['Sanitized Unit of Measures (Sanitasi Satuan)', $summary['sanitized_units'], $statusLabel],
                ['Merged Duplicate Customers (Konsolidasi Pelanggan)', $summary['merged_customers'], $statusLabel],
            ]
        );
    }
}
