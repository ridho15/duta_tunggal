<?php

namespace App\Console\Commands;

use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Services\Reports\SalesReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi snapshot HPP (invoice_items.cost_price / cogs_amount / cogs_source) untuk invoice penjualan LAMA.
 *
 *  - DEFAULT dry-run; perubahan hanya dengan --apply; --apply menulis CSV;
 *  - hanya mengisi baris yang cogs_amount-nya masih NULL; jurnal, harga, DPP, PPN tidak disentuh;
 *  - sumber, urut prioritas: (1) 'jurnal' — invoice satu baris dengan jurnal HPP: angka jurnal persis;
 *    (2) 'stok' — nilai pergerakan stok Delivery Order untuk produk & kuantitas yang sama;
 *    (3) 'estimasi' — qty × cost_price master SAAT INI (ditandai estimasi di laporan; bisa berbeda dari jurnal
 *    bila cost_price berubah setelah invoice diposting);
 *  - melaporkan selisih Σ baris vs jurnal HPP per invoice supaya dapat ditinjau.
 */
class BackfillInvoiceCogs extends Command
{
    protected $signature = 'invoices:backfill-cogs
        {--apply : Terapkan perubahan (default: dry-run, hanya melaporkan)}
        {--limit=0 : Batasi jumlah invoice yang diperiksa (0 = semua)}';

    protected $description = 'Lengkapi snapshot HPP invoice penjualan lama (dry-run secara default)';

    public function handle(): int
    {
        if (! Schema::hasColumn('invoice_items', 'cogs_amount')) {
            $this->error('Kolom invoice_items.cogs_amount belum ada. Jalankan dulu: php artisan migrate');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info('Backfill snapshot HPP invoice penjualan' . ($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $updates = [];
        $mismatches = [];
        $sources = [InvoiceItem::COGS_SOURCE_JOURNAL => 0, InvoiceItem::COGS_SOURCE_STOCK => 0, InvoiceItem::COGS_SOURCE_ESTIMATE => 0];
        $checked = 0;

        Invoice::query()
            ->where('from_model_type', SaleOrder::class)
            ->whereHas('invoiceItem', fn ($q) => $q->whereNull('cogs_amount'))
            ->with(['invoiceItem.product'])
            ->orderBy('id')
            ->when((int) $this->option('limit') > 0, fn ($q) => $q->limit((int) $this->option('limit')))
            ->each(function (Invoice $invoice) use (&$updates, &$mismatches, &$sources, &$checked) {
                $checked++;
                $pending = $invoice->invoiceItem->whereNull('cogs_amount');
                $journalCogs = $this->journalCogs($invoice);
                $assigned = 0.0;

                foreach ($pending as $item) {
                    [$cost, $amount, $source] = $this->resolve($invoice, $item, $journalCogs, $invoice->invoiceItem->count());
                    $sources[$source]++;
                    $assigned += $amount;
                    $updates[] = ['id' => $item->id, 'invoice' => $invoice->invoice_number, 'cost_price' => $cost, 'cogs_amount' => $amount, 'source' => $source];
                }

                $assigned += (float) $invoice->invoiceItem->whereNotNull('cogs_amount')->sum('cogs_amount');
                if ($journalCogs !== null && abs($assigned - $journalCogs) > 0.01) {
                    $mismatches[] = [$invoice->invoice_number, number_format($assigned, 2, ',', '.'), number_format($journalCogs, 2, ',', '.'), number_format($assigned - $journalCogs, 2, ',', '.')];
                }
            });

        $this->table(['Sumber snapshot', 'Baris'], [
            ['jurnal (angka jurnal persis)', $sources[InvoiceItem::COGS_SOURCE_JOURNAL]],
            ['stok (nilai pergerakan stok DO)', $sources[InvoiceItem::COGS_SOURCE_STOCK]],
            ['estimasi (cost_price master saat ini)', $sources[InvoiceItem::COGS_SOURCE_ESTIMATE]],
            ['invoice diperiksa', $checked],
        ]);

        if ($mismatches !== []) {
            $this->warn('Selisih Σ baris vs jurnal HPP (perlu ditinjau: cost_price master berubah sejak invoice diposting?):');
            $this->table(['Invoice', 'Σ baris', 'Jurnal HPP', 'Selisih'], array_slice($mismatches, 0, 40));
        }

        if ($updates === []) {
            $this->info('Tidak ada baris yang perlu dilengkapi.');

            return self::SUCCESS;
        }

        $this->info(count($updates) . ' baris dapat dilengkapi.');

        if (! $apply) {
            $this->warn('Dry-run selesai. Jalankan ulang dengan --apply setelah laporan di atas ditinjau.');

            return self::SUCCESS;
        }

        $path = storage_path('app/backfill/invoice-cogs-' . now()->format('Ymd_His') . '.csv');
        File::ensureDirectoryExists(dirname($path));
        $fh = fopen($path, 'w');
        fputcsv($fh, ['invoice_item_id', 'invoice', 'cost_price_baru', 'cogs_amount_baru', 'sumber']);

        DB::transaction(function () use ($updates, $fh) {
            foreach ($updates as $row) {
                fputcsv($fh, [$row['id'], $row['invoice'], $row['cost_price'], $row['cogs_amount'], $row['source']]);
                // Query builder: tanpa observer/event; hanya tiga kolom snapshot dan hanya bila masih NULL.
                DB::table('invoice_items')->where('id', $row['id'])->whereNull('cogs_amount')->update([
                    'cost_price' => $row['cost_price'],
                    'cogs_amount' => $row['cogs_amount'],
                    'cogs_source' => $row['source'],
                ]);
            }
        });
        fclose($fh);

        $this->info(count($updates) . " baris dilengkapi. CSV: {$path}");

        return self::SUCCESS;
    }

    private function journalCogs(Invoice $invoice): ?float
    {
        $total = JournalEntry::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('description', 'like', SalesReportService::COGS_DESCRIPTION_PREFIX . '%')
            ->sum('debit');

        return $total > 0 ? round((float) $total, 2) : null;
    }

    /**
     * @return array{0: float, 1: float, 2: string} [cost_price, cogs_amount, sumber]
     */
    private function resolve(Invoice $invoice, InvoiceItem $item, ?float $journalCogs, int $lineCount): array
    {
        $qty = (float) $item->quantity;

        // (1) invoice satu baris dengan jurnal HPP: angka jurnal persis
        if ($journalCogs !== null && $lineCount === 1 && $qty > 0) {
            return [round($journalCogs / $qty, 4), $journalCogs, InvoiceItem::COGS_SOURCE_JOURNAL];
        }

        // (2) nilai pergerakan stok Delivery Order untuk produk & kuantitas yang sama
        $doIds = array_filter((array) $invoice->delivery_orders);
        if ($doIds !== [] && $qty > 0) {
            $itemIds = DeliveryOrderItem::withoutGlobalScopes()->whereIn('delivery_order_id', $doIds)->where('product_id', $item->product_id)->pluck('id');
            if ($itemIds->isNotEmpty()) {
                $movement = StockMovement::query()
                    ->where('from_model_type', DeliveryOrderItem::class)
                    ->whereIn('from_model_id', $itemIds)
                    ->selectRaw('COALESCE(SUM(ABS(quantity)), 0) as qty, COALESCE(SUM(ABS(value)), 0) as val')
                    ->first();

                if ($movement && (float) $movement->val > 0 && abs((float) $movement->qty - $qty) < 0.001) {
                    return [round((float) $movement->val / $qty, 4), round((float) $movement->val, 2), InvoiceItem::COGS_SOURCE_STOCK];
                }
            }
        }

        // (3) estimasi dari cost_price master saat ini
        $cost = (float) ($item->product?->cost_price ?? 0);

        return [$cost, round($qty * $cost, 2), InvoiceItem::COGS_SOURCE_ESTIMATE];
    }
}
