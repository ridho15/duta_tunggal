<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Services\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi data lama (catatan akunting 24 Nov 2026): faktur pembelian dulu SELALU mendebit 2100.10, padahal penerimaan barang
 * mengkredit akun belum-tertagih MILIK PRODUK (mis. 2190.10 / 1180.01). Akibatnya 2100.10 bersaldo debit (terbaca aset) dan
 * akun produk tidak pernah nol.
 *
 * Per faktur: bagian yang seharusnya ke akun produk dipindah dengan jurnal koreksi Dr akun produk / Cr 2100.10, tanggal faktur
 * (bila periodenya sudah ditutup: hari ini), bersumber faktur yang sama. Idempoten (referensi KOREKSI-BBT-{nomor faktur}).
 * Default hanya laporan; --apply untuk menulis.
 */
class FixUnbilledPurchaseProductAccounts extends Command
{
    protected $signature = 'purchase:fix-unbilled-account {--apply : Tulis jurnal koreksi (default: hanya laporan)}';

    protected $description = 'Pindahkan debit 2100.10 faktur pembelian lama ke akun barang-belum-tertagih milik produk (dry-run default)';

    public function handle(LedgerPostingService $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $unbilled = ChartOfAccount::where('code', config('coa.unbilled_purchase', '2100.10'))->first();
        if (! $unbilled) {
            $this->error('Akun ' . config('coa.unbilled_purchase', '2100.10') . ' (Penerimaan Barang Belum Tertagih) tidak ditemukan. Solusi: periksa Bagan Akun & config/coa.php.');

            return self::FAILURE;
        }

        $rows = [];
        $invoices = Invoice::withoutGlobalScopes()
            ->whereIn('from_model_type', [PurchaseOrder::class, PurchaseReceipt::class])
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, 'canceled'])
            ->with('invoiceItem.product')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $debited = (float) JournalEntry::withoutGlobalScopes()->where('source_type', Invoice::class)->where('source_id', $invoice->id)
                ->where('coa_id', $unbilled->id)->where('is_reversal', false)->sum('debit');
            if ($debited <= 0.005) {
                continue;
            }
            $reference = 'KOREKSI-BBT-' . $invoice->invoice_number;
            if (JournalEntry::withoutGlobalScopes()->where('reference', $reference)->exists()) {
                continue;
            }

            foreach ($ledger->splitUnbilledByProductCoa($invoice, $debited, $unbilled) as [$coa, $amount]) {
                if ($coa->id === $unbilled->id || $amount <= 0.005) {
                    continue;
                }
                $rows[] = ['invoice' => $invoice, 'coa' => $coa, 'amount' => round($amount, 2), 'reference' => $reference];
            }
        }

        if ($rows === []) {
            $this->info('Tidak ada faktur pembelian yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        $this->table(['Faktur', 'Akun produk', 'Nilai dipindah dari ' . $unbilled->code], array_map(fn ($r) => [
            $r['invoice']->invoice_number, $r['coa']->code . ' ' . $r['coa']->name, number_format($r['amount'], 2, ',', '.'),
        ], $rows));

        if (! $apply) {
            $this->warn('Dry-run: belum ada yang ditulis. Jalankan ulang dengan --apply untuk membuat jurnal koreksi.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows, $unbilled) {
            foreach ($rows as $row) {
                $invoice = $row['invoice'];
                $date = Carbon::parse($invoice->invoice_date ?? now())->toDateString();
                if (AccountingPeriod::closedPeriodCovering(Carbon::parse($date), $invoice->cabang_id) !== null) {
                    $date = now()->toDateString();
                }
                foreach ([[$row['coa']->id, $row['amount'], 0], [$unbilled->id, 0, $row['amount']]] as [$coaId, $debit, $credit]) {
                    JournalEntry::create([
                        'coa_id' => $coaId, 'date' => $date, 'reference' => $row['reference'],
                        'description' => "Koreksi akun barang belum tertagih faktur {$invoice->invoice_number} (ikut akun produk)",
                        'debit' => $debit, 'credit' => $credit, 'journal_type' => 'purchase', 'cabang_id' => $invoice->cabang_id,
                        'source_type' => Invoice::class, 'source_id' => $invoice->id,
                    ]);
                }
            }
        });

        $this->info(count($rows) . ' jurnal koreksi dibuat.');

        return self::SUCCESS;
    }
}
