<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Batalkan Penerimaan customer (T3.3, D29): koreksi resmi untuk penerimaan yang sudah berjurnal.
 * Jurnal dibalik (entri cermin, dokumen asli tetap ada), piutang & status invoice dikembalikan, status penerimaan `Cancelled`
 * dengan alasan/pelaku/waktu. Atomik; tidak dapat dibatalkan dua kali.
 *
 * Batasan (sengaja, ditolak dengan pesan jelas): penerimaan yang memakai/menghasilkan DEPOSIT dan penerimaan tanpa rincian item
 * — pembalikannya melibatkan saldo deposit/alokasi yang tidak dapat ditebak; koreksi lewat akuntansi (T5).
 */
class CustomerReceiptCancellation
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    /** Alasan mengapa penerimaan ini tidak boleh dibatalkan otomatis; null = boleh. */
    public function blocker(CustomerReceipt $receipt): ?string
    {
        $status = strtolower((string) $receipt->status);
        if ($status === 'cancelled') {
            return 'Penerimaan ini sudah dibatalkan.';
        }
        if (! in_array($status, ['partial', 'paid'], true)) {
            return 'Hanya penerimaan yang sudah berjurnal (Sebagian/Lunas) yang dapat dibatalkan; penerimaan Draft cukup dihapus.';
        }

        $items = $receipt->customerReceiptItem()->get();
        if ($items->isEmpty()) {
            return 'Penerimaan tanpa rincian item tidak dapat dibatalkan otomatis; hubungi akuntansi.';
        }
        if ($items->contains(fn ($item) => strtolower((string) $item->method) === 'deposit')
            || strtolower((string) $receipt->payment_method) === 'deposit'
            || (float) $receipt->overpayment_amount > 0
            || $receipt->deposit_id) {
            return 'Penerimaan yang memakai atau menghasilkan deposit tidak dapat dibatalkan otomatis; koreksi lewat akuntansi.';
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    public function cancel(CustomerReceipt $receipt, string $reason, ?User $actor = null): CustomerReceipt
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi (minimal 10 karakter).']);
        }

        DB::transaction(function () use ($receipt, $reason, $actor) {
            $locked = CustomerReceipt::withoutGlobalScopes()->lockForUpdate()->findOrFail($receipt->getKey());

            if ($message = $this->blocker($locked)) {
                throw ValidationException::withMessages(['receipt' => $message]);
            }

            // 1. Jurnal dibalik (entri cermin; asli ditandai)
            $this->reverseJournals($locked);

            // 2. Piutang & status invoice dikembalikan (cermin dari CustomerReceiptObserver::updateAccountReceivables)
            $this->restoreAccountReceivables($locked);

            // 3. Penerimaan ditandai dibatalkan (status Cancelled tidak memicu posting ulang oleh observer)
            $actorId = ($actor ?? Auth::user())?->getKey();
            $locked->forceFill([
                'status' => 'Cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancel_reason' => $reason,
            ])->save();
        });

        return $receipt->refresh();
    }

    /** Entri cermin (debit↔kredit) untuk semua jurnal penerimaan yang belum dibalik; yang asli ditandai sudah dibalik. */
    private function reverseJournals(CustomerReceipt $receipt): void
    {
        $originals = JournalEntry::withoutGlobalScopes()
            ->where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->where('is_reversal', false)
            ->whereNull('reversal_of_transaction_id')
            ->get();

        if ($originals->isEmpty()) {
            return;
        }

        $reversalTransactionId = 'REV-RECEIPT-'.$receipt->id.'-'.now()->format('YmdHis');
        $date = now()->toDateString();

        foreach ($originals as $entry) {
            JournalEntry::create([
                'coa_id' => $entry->coa_id,
                'date' => $date,
                'reference' => 'REVERSAL: '.($entry->reference ?? 'REC-'.$receipt->id),
                'description' => 'Pembalikan Penerimaan Customer: '.($entry->description ?? ''),
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'currency_id' => $entry->currency_id,
                'exchange_rate' => $entry->exchange_rate,
                'amount_original_currency' => $entry->amount_original_currency,
                'journal_type' => $entry->journal_type,
                'cabang_id' => $entry->cabang_id,
                'department_id' => $entry->department_id,
                'project_id' => $entry->project_id,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'transaction_id' => $reversalTransactionId,
                'is_reversal' => true,
                'reversal_of_transaction_id' => (string) $receipt->id,
            ]);
        }

        JournalEntry::withoutGlobalScopes()
            ->where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->where('is_reversal', false)
            ->whereNull('reversal_of_transaction_id')
            ->update(['reversal_of_transaction_id' => $reversalTransactionId]);
    }

    private function restoreAccountReceivables(CustomerReceipt $receipt): void
    {
        foreach ($receipt->customerReceiptItem()->get() as $item) {
            $selected = $item->selected_invoices;
            $invoiceIds = ! empty($selected)
                ? array_values(array_unique(array_filter(array_map('intval', is_array($selected) ? $selected : (json_decode($selected, true) ?? [])))))
                : array_filter([(int) ($item->invoice_id ?? $receipt->invoice_id)]);

            foreach ($invoiceIds as $invoiceId) {
                $ar = AccountReceivable::where('invoice_id', $invoiceId)->lockForUpdate()->first();
                if (! $ar) {
                    continue;
                }

                $amountIdr = (float) ($item->amount_idr ?: $item->amount);
                $rate = (float) ($ar->exchange_rate ?? $item->exchange_rate ?? 1);
                $rate = $rate > 0 ? $rate : 1.0;

                $ar->paid = max(0.0, (float) $ar->paid - $amountIdr);
                $ar->remaining = (float) $ar->remaining + $amountIdr;
                $ar->paid_original = round((float) $ar->paid / $rate, 4);
                $ar->remaining_original = round(max(0, (float) $ar->remaining) / $rate, 4);
                $ar->status = $ar->remaining > 0 ? PaymentStatus::UNPAID->value : PaymentStatus::PAID->value;
                $ar->save();

                $invoice = $ar->invoice;
                if ($invoice) {
                    $invoice->update(['status' => $ar->paid > 0 ? 'partially_paid' : 'unpaid']);

                    if ($ar->remaining > 0 && ! $ar->ageingSchedule()->exists()) {
                        $days = ($invoice->invoice_date && $invoice->due_date)
                            ? Carbon::parse($invoice->invoice_date)->diffInDays(Carbon::parse($invoice->due_date))
                            : 0;
                        $ar->ageingSchedule()->create([
                            'invoice_date' => $invoice->invoice_date, 'due_date' => $invoice->due_date, 'days_outstanding' => $days, 'bucket' => 'Current',
                        ]);
                    }
                }
            }
        }
    }
}
