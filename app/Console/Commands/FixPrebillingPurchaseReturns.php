<?php

namespace App\Console\Commands;

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi data lama: retur gudang yang disetujui SEBELUM GRN ditagih dijurnal Dr 2100.10, tetapi faktur sesudahnya tetap
 * menagih qty penuh (qty_accepted tanpa dikurangi retur). Akibatnya Hutang (2110) & PPN Masukan lebih saji dan 2100.10
 * bersaldo debit, sedangkan subledger AP dulu dipotong retur tanpa jurnal (subledger ≠ GL).
 *
 * Koreksi per retur (bagian qty yang ikut tertagih): Dr Hutang Dagang (DPP+PPN) / Cr 2100.10 (DPP) / Cr PPN Masukan (PPN),
 * bertanggal hari ini & bersumber PurchaseReturn — sehingga perhitungan subledger (jurnal Dr Hutang per retur) = GL.
 * Default dry-run; --apply untuk menulis.
 */
class FixPrebillingPurchaseReturns extends Command
{
    protected $signature = 'purchase-return:fix-prebilling {--apply : Tulis jurnal koreksi & sinkron saldo AP (default: hanya laporan)} {--return-id= : Batasi ke satu retur}';

    protected $description = 'Koreksi retur pembelian pra-faktur yang ikut tertagih di faktur (jurnal koreksi hutang/PPN/2100.10), dry-run default';

    public function handle(PurchaseReturnService $service): int
    {
        $apply = (bool) $this->option('apply');
        $apCoa = ChartOfAccount::where('code', config('coa.accounts_payable', '2110'))->first() ?? ChartOfAccount::where('code', '2110')->first();
        $unbilledCoa = ChartOfAccount::where('code', config('coa.unbilled_purchase', '2100.10'))->first();
        $vatCoa = ChartOfAccount::where('code', config('coa.ppn_masukan', '1170.06'))->first();
        if (! $apCoa || ! $unbilledCoa || ! $vatCoa) {
            $this->error('COA Hutang Dagang / Pembelian Belum Ditagih / PPN Masukan tidak ditemukan. Solusi: periksa config/coa.php & Chart of Accounts.');

            return self::FAILURE;
        }

        $returns = PurchaseReturn::withoutGlobalScopes()->with('purchaseReturnItem')
            ->where('status', 'approved')->whereNull('quality_control_id')->whereNull('failed_qc_action')->whereNotNull('purchase_receipt_id')
            ->when($this->option('return-id'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn (PurchaseReturn $r) => $service->accountsPayableDebitForReturn($r) <= 0);

        $rows = [];
        foreach ($returns as $return) {
            $invoice = Invoice::withoutGlobalScopes()->whereNotIn('status', [Invoice::STATUS_CANCELLED, 'canceled'])
                ->where(fn ($q) => $q->whereJsonContains('purchase_receipts', (int) $return->purchase_receipt_id)
                    ->orWhereJsonContains('purchase_receipts', (string) $return->purchase_receipt_id))
                ->with('invoiceItem')->first();
            if (! $invoice || ! AccountPayable::withoutGlobalScopes()->where('invoice_id', $invoice->id)->exists()) {
                continue; // belum ditagih: tidak ada yang perlu dikoreksi
            }

            $receipt = PurchaseReceipt::withoutGlobalScopes()->with('purchaseReceiptItem')->find($return->purchase_receipt_id);
            $rate = (float) ($invoice->exchange_rate ?: 1);
            $dpp = 0.0;
            $vat = 0.0;
            foreach ($return->purchaseReturnItem as $item) {
                $accepted = (float) ($receipt?->purchaseReceiptItem->firstWhere('id', $item->purchase_receipt_item_id)?->qty_accepted ?? 0);
                $invoiceLine = $invoice->invoiceItem->firstWhere('product_id', $item->product_id);
                if (! $invoiceLine) {
                    continue;
                }
                // Bagian qty retur yang ikut tertagih = qty faktur − (qty diterima − qty retur), maksimal qty retur.
                $overBilled = min((float) $item->qty_returned, max(0.0, (float) $invoiceLine->quantity - ($accepted - (float) $item->qty_returned)));
                if ($overBilled <= 0) {
                    continue;
                }
                $lineDpp = round($overBilled * (float) $invoiceLine->price * $rate, 2);
                $dpp += $lineDpp;
                $vat += round($lineDpp * (float) $invoiceLine->tax_rate / 100, 2);
            }
            if ($dpp <= 0) {
                continue;
            }
            $rows[] = ['return' => $return, 'invoice' => $invoice, 'dpp' => round($dpp, 2), 'vat' => round($vat, 2)];
        }

        $this->table(['Retur', 'Faktur', 'DPP koreksi', 'PPN koreksi', 'Dr Hutang'], collect($rows)->map(fn ($r) => [
            $r['return']->nota_retur, $r['invoice']->invoice_number, number_format($r['dpp'], 2), number_format($r['vat'], 2), number_format($r['dpp'] + $r['vat'], 2),
        ])->all());

        if (! $apply) {
            $this->info(count($rows) . ' retur perlu koreksi. Dry-run: tidak ada data yang diubah. Jalankan dengan --apply untuk menulis jurnal koreksi.');

            return self::SUCCESS;
        }

        foreach ($rows as $r) {
            DB::transaction(function () use ($r, $apCoa, $unbilledCoa, $vatCoa) {
                $return = $r['return'];
                $base = [
                    'date' => now()->toDateString(), 'reference' => $return->nota_retur, 'journal_type' => 'purchase_return',
                    'source_type' => PurchaseReturn::class, 'source_id' => $return->id, 'cabang_id' => $return->cabang_id,
                    'description' => 'Koreksi retur pra-faktur yang ikut tertagih di ' . $r['invoice']->invoice_number,
                ];
                JournalEntry::create($base + ['coa_id' => $apCoa->id, 'debit' => round($r['dpp'] + $r['vat'], 2), 'credit' => 0]);
                JournalEntry::create($base + ['coa_id' => $unbilledCoa->id, 'debit' => 0, 'credit' => $r['dpp']]);
                if ($r['vat'] > 0) {
                    JournalEntry::create($base + ['coa_id' => $vatCoa->id, 'debit' => 0, 'credit' => $r['vat']]);
                }
                $this->syncAccountPayable($r['invoice']);
            });
        }

        $this->info(count($rows) . ' jurnal koreksi dibuat dan saldo AP faktur terkait disinkronkan dengan GL.');

        return self::SUCCESS;
    }

    /** Saldo AP = total − pembayaran terposting − retur yang dijurnal ke Hutang − penyesuaian (mata uang faktur & IDR). */
    private function syncAccountPayable(Invoice $invoice): void
    {
        $ap = AccountPayable::withoutGlobalScopes()->where('invoice_id', $invoice->id)->lockForUpdate()->first();
        $rate = (float) ($ap->exchange_rate ?: 1);
        $details = \App\Models\VendorPaymentDetail::where('invoice_id', $invoice->id)
            ->whereHas('vendorPayment', fn ($q) => $q->whereIn('status', ['partial', 'paid']));
        $paidOriginal = (float) (clone $details)->sum('amount')
            + round(app(PurchaseReturnService::class)->calculateApprovedReturnsTotalForInvoice($invoice) / $rate, 4);
        $totalOriginal = (float) ($ap->total_original ?: ((float) $ap->total / $rate));
        $remainingOriginal = round($totalOriginal - $paidOriginal - (float) (clone $details)->sum('adjustment_amount'), 4);

        $ap->forceFill([
            'paid_original' => $paidOriginal, 'remaining_original' => $remainingOriginal,
            'paid' => round($paidOriginal * $rate, 2), 'remaining' => round($remainingOriginal * $rate, 2),
            'status' => $remainingOriginal <= 1.0 ? \App\Enums\PaymentStatus::PAID->value : \App\Enums\PaymentStatus::UNPAID->value,
        ])->save();
    }
}
