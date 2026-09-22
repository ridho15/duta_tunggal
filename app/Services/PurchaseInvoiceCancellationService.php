<?php

namespace App\Services;

use App\Helpers\MoneyHelper;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\VendorPaymentDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Pembatalan invoice pembelian yang sudah diposting: jurnal dibalik (bukan dihapus), hutang usaha dikeluarkan,
 * dan invoice ditandai "Dibatalkan" beserta alasan, pelaku, dan waktunya. Receipt yang tertagih di invoice ini
 * kembali bisa ditagihkan, dan nomor invoice supplier / faktur pajaknya bebas dipakai ulang oleh invoice pengganti.
 *
 * Invoice yang sudah dibayar (sebagian maupun lunas) atau masih tercakup Permintaan Pembayaran aktif tidak dapat
 * dibatalkan; pembayaran/permintaannya harus dikoreksi lebih dulu supaya jurnal kas/bank tidak menggantung.
 */
class PurchaseInvoiceCancellationService
{
    /** Status invoice yang sudah diposting dan dapat dibatalkan (bila memenuhi syarat lain). */
    public const POSTED_STATUSES = [
        Invoice::STATUS_SENT,
        Invoice::STATUS_OVERDUE,
        Invoice::STATUS_PARTIALLY_PAID,
        Invoice::STATUS_PAID,
    ];

    public function __construct(private readonly LedgerPostingService $ledger)
    {
    }

    /**
     * Alasan invoice belum boleh dibatalkan, atau null bila boleh.
     */
    public function blockReason(Invoice $invoice): ?string
    {
        if ($invoice->from_model_type !== PurchaseOrder::class) {
            return 'Hanya invoice pembelian yang dapat dibatalkan lewat fitur ini.';
        }

        $status = strtolower((string) $invoice->status);

        if ($status === Invoice::STATUS_CANCELLED) {
            return 'Invoice ini sudah dibatalkan.';
        }

        if ($status === Invoice::STATUS_DRAFT) {
            return 'Invoice draft belum dijurnal dan belum membentuk hutang. Gunakan Hapus, bukan Batalkan.';
        }

        if (! in_array($status, self::POSTED_STATUSES, true)) {
            return "Invoice berstatus '{$status}' tidak dapat dibatalkan.";
        }

        // Status Lunas/Dibayar Sebagian sendiri sudah berarti ada pembayaran, walau catatannya tidak lengkap (data lama).
        $paid = $this->paidAmount($invoice);
        $hasPaymentStatus = in_array($status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true);
        if ($hasPaymentStatus || $paid > 0.01) {
            return 'Invoice sudah memiliki pembayaran vendor'
                . ($paid > 0.01 ? ' sebesar Rp ' . number_format($paid, 2, ',', '.') : '')
                . '. Koreksi pembayaran tersebut lebih dahulu sebelum membatalkan invoice.';
        }

        $activeRequests = $this->activePaymentRequests($invoice);
        if ($activeRequests !== []) {
            return 'Invoice masih tercakup Permintaan Pembayaran aktif (' . implode(', ', $activeRequests)
                . '). Tolak atau selesaikan permintaan tersebut lebih dahulu.';
        }

        return null;
    }

    /**
     * @param  \Carbon\Carbon|string|null  $reversalDate  Tanggal jurnal balik (default hari ini; tidak boleh sebelum tanggal invoice).
     *
     * @throws \DomainException  bila invoice belum boleh dibatalkan (pesan siap ditampilkan ke pengguna)
     * @throws ValidationException  bila alasan kosong atau tanggal tidak valid
     */
    public function cancel(Invoice $invoice, string $reason, $reversalDate = null, ?int $userId = null): Invoice
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        return DB::transaction(function () use ($invoice, $reason, $reversalDate, $userId): Invoice {
            $locked = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);

            $blocked = $this->blockReason($locked);
            if ($blocked !== null) {
                throw new \DomainException($blocked);
            }

            $date = $reversalDate ? \Carbon\Carbon::parse($reversalDate)->startOfDay() : now()->startOfDay();
            if ($locked->invoice_date && $date->lt($locked->invoice_date->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'reversal_date' => 'Tanggal pembatalan tidak boleh sebelum tanggal invoice (' . $locked->invoice_date->format('d/m/Y') . ').',
                ]);
            }

            $hasOpenJournals = JournalEntry::withoutGlobalScopes()
                ->where('source_type', Invoice::class)
                ->where('source_id', $locked->id)
                ->where('is_reversal', false)
                ->whereNull('reversal_of_transaction_id')
                ->exists();

            if ($hasOpenJournals) {
                $this->ledger->reverseInvoiceJournalEntries($locked, $date->toDateString());
            } else {
                Log::warning('PurchaseInvoiceCancellationService: invoice diposting tanpa jurnal terbuka, hanya status yang dibatalkan', [
                    'invoice_id' => $locked->id,
                ]);
            }

            AccountPayable::query()->where('invoice_id', $locked->id)->get()->each->delete();

            $locked->forceFill([
                'status' => Invoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
            ])->save();

            Log::info('PurchaseInvoiceCancellationService: invoice dibatalkan', [
                'invoice_id' => $locked->id,
                'invoice_number' => $locked->invoice_number,
                'reversal_date' => $date->toDateString(),
                'user_id' => $userId,
            ]);

            return $locked->fresh();
        });
    }

    private function paidAmount(Invoice $invoice): float
    {
        $apPaid = (float) AccountPayable::query()->where('invoice_id', $invoice->id)->sum('paid');

        $detailPaid = (float) VendorPaymentDetail::query()
            ->where('invoice_id', $invoice->id)
            ->selectRaw('COALESCE(SUM(amount + COALESCE(adjustment_amount, 0)), 0) as paid_total')
            ->value('paid_total');

        return max($apPaid, $detailPaid);
    }

    /**
     * Nomor Permintaan Pembayaran aktif (menunggu persetujuan / disetujui / dibayar sebagian) yang memuat invoice ini.
     *
     * @return array<int, string>
     */
    private function activePaymentRequests(Invoice $invoice): array
    {
        return PaymentRequest::query()
            ->whereIn('status', [PaymentRequest::STATUS_PENDING, PaymentRequest::STATUS_APPROVED, PaymentRequest::STATUS_PARTIAL])
            ->get()
            ->filter(function (PaymentRequest $request) use ($invoice) {
                $selected = $request->selected_invoices;
                if (is_string($selected)) {
                    $selected = json_decode($selected, true);
                }

                return is_array($selected)
                    && in_array($invoice->id, array_map('intval', $selected), true)
                    && (float) MoneyHelper::safeParse($request->remaining_amount ?? 0) > 0;
            })
            ->pluck('request_number')
            ->values()
            ->all();
    }
}
